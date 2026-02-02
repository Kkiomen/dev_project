<?php

namespace App\Jobs;

use App\Models\PsdImport;
use App\Models\Template;
use App\Services\AI\TemplateClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyTemplateWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Template $template,
        protected PsdImport $import
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TemplateClassificationService $classificationService): void
    {
        Log::info('ClassifyTemplateWithAiJob: Starting', [
            'template_id' => $this->template->id,
            'import_id' => $this->import->id,
        ]);

        try {
            // Classify layers by semantic role
            $classificationService->classifyLayers($this->template);

            // Generate style tags
            $classificationService->generateStyleTags($this->template);

            Log::info('ClassifyTemplateWithAiJob: Classification completed', [
                'template_id' => $this->template->id,
                'import_id' => $this->import->id,
            ]);

            // Check if all templates from this import have been classified
            $this->checkImportCompletion();

        } catch (Throwable $e) {
            Log::error('ClassifyTemplateWithAiJob: Classification failed', [
                'template_id' => $this->template->id,
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the import if classification fails
            // The template is still usable, just without AI classification
            if ($this->attempts() >= $this->tries) {
                Log::warning('ClassifyTemplateWithAiJob: Max attempts reached, marking classification as incomplete', [
                    'template_id' => $this->template->id,
                    'import_id' => $this->import->id,
                ]);

                $this->checkImportCompletion();
            } else {
                throw $e;
            }
        }
    }

    /**
     * Check if all templates from the import have been processed.
     * If so, mark the import as completed.
     */
    protected function checkImportCompletion(): void
    {
        // Refresh import from database
        $this->import->refresh();

        // Only check if still in ai_classifying status
        if ($this->import->status !== PsdImport::STATUS_AI_CLASSIFYING) {
            return;
        }

        // Get all template IDs from this import
        $templateIds = Template::where('psd_import_id', $this->import->id)
            ->pluck('id')
            ->toArray();

        if (empty($templateIds)) {
            $this->import->markAsCompleted();
            return;
        }

        // Check how many templates have been classified (have at least one layer with semantic_role)
        $classifiedCount = Template::where('psd_import_id', $this->import->id)
            ->whereHas('layers', function ($query) {
                $query->whereNotNull('semantic_role');
            })
            ->count();

        // For simplicity, consider import complete after this job finishes
        // since we're processing serially
        $totalTemplates = count($templateIds);
        $metadata = $this->import->metadata ?? [];
        $processedTemplates = ($metadata['processed_templates'] ?? 0) + 1;

        $this->import->update([
            'metadata' => array_merge($metadata, [
                'processed_templates' => $processedTemplates,
                'classified_count' => $classifiedCount,
            ]),
        ]);

        // If all templates processed, mark as completed
        if ($processedTemplates >= $totalTemplates) {
            $this->import->markAsCompleted();

            Log::info('ClassifyTemplateWithAiJob: Import fully completed', [
                'import_id' => $this->import->id,
                'total_templates' => $totalTemplates,
                'classified_templates' => $classifiedCount,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ClassifyTemplateWithAiJob: Job failed permanently', [
            'template_id' => $this->template->id,
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        // Still check completion - the import can complete even if some classifications fail
        $this->checkImportCompletion();
    }
}
