<?php

namespace App\Jobs;

use App\Enums\VideoProjectStatus;
use App\Models\VideoProject;
use App\Services\VideoEditorService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenderCaptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 900;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(protected VideoProject $project) {}

    protected function taskType(): string { return 'video_render'; }
    protected function taskUserId(): int { return $this->project->user_id; }
    protected function taskModelId(): string|int { return $this->project->id; }
    protected function taskStartData(): array
    {
        return ['project_id' => $this->project->public_id, 'title' => $this->project->title];
    }

    public function handle(VideoEditorService $editor): void
    {
        $this->broadcastTaskStarted();

        try {
            $this->project->markAs(VideoProjectStatus::Rendering);

            if (!$this->project->hasTranscription()) {
                throw new \RuntimeException('No transcription available for rendering');
            }

            // Build caption data
            $captions = [
                'style' => $this->project->caption_style,
                'segments' => $this->project->getSegments(),
                'highlight_keywords' => $this->project->caption_settings['highlight_keywords'] ?? false,
                'font_size' => $this->project->caption_settings['font_size'] ?? null,
                'position' => $this->project->caption_settings['position'] ?? 'bottom',
            ];

            // Generate output path
            $outputPath = 'video-projects/' . $this->project->id . '/output_' . time() . '.mp4';

            // Render
            $editor->addCaptions(
                $this->project->video_path,
                $captions,
                $outputPath,
            );

            $this->project->update([
                'output_path' => $outputPath,
                'status' => VideoProjectStatus::Completed,
                'completed_at' => now(),
            ]);

            Log::info('[RenderCaptionsJob] Completed', [
                'project_id' => $this->project->id,
                'output' => $outputPath,
            ]);

            $this->broadcastTaskCompleted(true, null, [
                'project_id' => $this->project->public_id,
                'output_path' => $outputPath,
            ]);
        } catch (\Throwable $e) {
            $this->project->markAsFailed($e->getMessage());
            $this->broadcastTaskCompleted(false, $e->getMessage());

            Log::error('[RenderCaptionsJob] Failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[RenderCaptionsJob] Failed permanently', [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);

        $this->project->markAsFailed($exception->getMessage());
    }
}
