<?php

namespace App\Jobs;

use App\Models\PsdImport;
use App\Models\User;
use App\Services\BulkPsdImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportPsdFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected PsdImport $import,
        protected User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BulkPsdImportService $bulkImportService): void
    {
        Log::info('ImportPsdFileJob: Starting', [
            'import_id' => $this->import->id,
            'filename' => $this->import->filename,
        ]);

        // Skip if not pending
        if (!$this->import->isPending()) {
            Log::info('ImportPsdFileJob: Skipping non-pending import', [
                'import_id' => $this->import->id,
                'status' => $this->import->status,
            ]);
            return;
        }

        // Mark as processing
        $this->import->markAsProcessing();

        try {
            // Import the PSD file
            $result = $bulkImportService->importFromPath($this->import, $this->user);

            $templateCount = count($result['templates']);
            $groupId = $result['group']?->id;

            Log::info('ImportPsdFileJob: Import completed', [
                'import_id' => $this->import->id,
                'template_count' => $templateCount,
                'group_id' => $groupId,
            ]);

            // Update import metadata
            $this->import->update([
                'metadata' => array_merge($this->import->metadata ?? [], [
                    'template_count' => $templateCount,
                    'group_id' => $groupId,
                    'imported_at' => now()->toISOString(),
                ]),
            ]);

            // Mark as AI classifying and dispatch classification + thumbnail jobs
            $this->import->markAsAiClassifying();

            foreach ($result['templates'] as $template) {
                // Dispatch AI classification job
                ClassifyTemplateWithAiJob::dispatch($template, $this->import);

                // Dispatch thumbnail generation job
                GenerateThumbnailJob::dispatch($template);

                Log::info('ImportPsdFileJob: Jobs dispatched for template', [
                    'import_id' => $this->import->id,
                    'template_id' => $template->id,
                ]);
            }

        } catch (Throwable $e) {
            Log::error('ImportPsdFileJob: Import failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->import->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ImportPsdFileJob: Job failed permanently', [
            'import_id' => $this->import->id,
            'filename' => $this->import->filename,
            'error' => $exception->getMessage(),
        ]);

        $this->import->markAsFailed($exception->getMessage());
    }
}
