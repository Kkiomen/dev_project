<?php

namespace App\Jobs;

use App\Models\Template;
use App\Services\TemplateRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateThumbnailJob implements ShouldQueue
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
    public int $backoff = 10;

    /**
     * Thumbnail width.
     */
    protected int $thumbnailWidth = 400;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Template $template
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TemplateRenderService $renderService): void
    {
        Log::info('GenerateThumbnailJob: Starting', [
            'template_id' => $this->template->id,
            'template_name' => $this->template->name,
        ]);

        try {
            // Render the template as thumbnail using Vue renderer
            $imageData = $renderService->renderThumbnail($this->template, $this->thumbnailWidth);

            // Generate thumbnail path
            $thumbnailPath = 'thumbnails/' . $this->template->id . '/' . Str::uuid() . '.png';

            // Delete old thumbnail if exists
            if ($this->template->thumbnail_path) {
                Storage::disk('public')->delete($this->template->thumbnail_path);
            }

            // Save to storage
            Storage::disk('public')->put($thumbnailPath, $imageData);

            // Update template with thumbnail path
            $this->template->update([
                'thumbnail_path' => $thumbnailPath,
            ]);

            Log::info('GenerateThumbnailJob: Thumbnail generated', [
                'template_id' => $this->template->id,
                'thumbnail_path' => $thumbnailPath,
            ]);

        } catch (Throwable $e) {
            Log::error('GenerateThumbnailJob: Failed to generate thumbnail', [
                'template_id' => $this->template->id,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the job permanently - thumbnail is not critical
            if ($this->attempts() >= $this->tries) {
                Log::warning('GenerateThumbnailJob: Max attempts reached, giving up', [
                    'template_id' => $this->template->id,
                ]);
                return;
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('GenerateThumbnailJob: Job failed permanently', [
            'template_id' => $this->template->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
