<?php

namespace App\Jobs;

use App\Models\PsdImport;
use App\Models\Template;
use App\Services\AI\TemplateClassificationService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyTemplateWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        protected Template $template,
        protected PsdImport $import
    ) {}

    protected function taskType(): string { return 'template_classification'; }
    protected function taskUserId(): int { return $this->import->user_id; }
    protected function taskModelId(): string|int { return $this->template->id; }

    public function handle(TemplateClassificationService $classificationService): void
    {
        $this->broadcastTaskStarted();

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

            $this->broadcastTaskCompleted(true);
        } catch (Throwable $e) {
            Log::error('ClassifyTemplateWithAiJob: Classification failed', [
                'template_id' => $this->template->id,
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the import if classification fails
            if ($this->attempts() >= $this->tries) {
                Log::warning('ClassifyTemplateWithAiJob: Max attempts reached, marking classification as incomplete', [
                    'template_id' => $this->template->id,
                    'import_id' => $this->import->id,
                ]);

                $this->checkImportCompletion();
                $this->broadcastTaskCompleted(false, $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    protected function checkImportCompletion(): void
    {
        $this->import->refresh();

        if ($this->import->status !== PsdImport::STATUS_AI_CLASSIFYING) {
            return;
        }

        $templateIds = Template::where('psd_import_id', $this->import->id)
            ->pluck('id')
            ->toArray();

        if (empty($templateIds)) {
            $this->import->markAsCompleted();
            return;
        }

        $classifiedCount = Template::where('psd_import_id', $this->import->id)
            ->whereHas('layers', function ($query) {
                $query->whereNotNull('semantic_role');
            })
            ->count();

        $totalTemplates = count($templateIds);
        $metadata = $this->import->metadata ?? [];
        $processedTemplates = ($metadata['processed_templates'] ?? 0) + 1;

        $this->import->update([
            'metadata' => array_merge($metadata, [
                'processed_templates' => $processedTemplates,
                'classified_count' => $classifiedCount,
            ]),
        ]);

        if ($processedTemplates >= $totalTemplates) {
            $this->import->markAsCompleted();

            Log::info('ClassifyTemplateWithAiJob: Import fully completed', [
                'import_id' => $this->import->id,
                'total_templates' => $totalTemplates,
                'classified_templates' => $classifiedCount,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ClassifyTemplateWithAiJob: Job failed permanently', [
            'template_id' => $this->template->id,
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->checkImportCompletion();
    }
}
