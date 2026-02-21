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

class RemoveSilenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 900;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        protected VideoProject $project,
        protected float $minSilence = 0.5,
        protected float $padding = 0.1,
    ) {}

    protected function taskType(): string { return 'video_silence_removal'; }
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
            $this->project->markAs(VideoProjectStatus::Editing);

            // Detect silence using audio-level analysis (FFmpeg silencedetect)
            $detection = $editor->detectSilence(
                $this->project->video_path,
                $this->minSilence,
            );

            $speechRegions = $detection['speech_regions'] ?? [];
            $silenceRegions = $detection['silence_regions'] ?? [];

            if (empty($silenceRegions)) {
                Log::info('[RemoveSilenceJob] No silence detected, skipping', [
                    'project_id' => $this->project->id,
                ]);
                $this->project->markAs(VideoProjectStatus::Transcribed);
                $this->broadcastTaskCompleted(true, null, ['project_id' => $this->project->public_id]);
                return;
            }

            Log::info('[RemoveSilenceJob] Silence detected', [
                'project_id' => $this->project->id,
                'silence_regions' => count($silenceRegions),
                'speech_regions' => count($speechRegions),
            ]);

            $outputPath = 'video-projects/' . $this->project->user_id . '/silence_removed_' . time() . '.mp4';

            $editor->removeSilence(
                $this->project->video_path,
                $speechRegions,
                $outputPath,
                $this->padding,
            );

            $this->project->update([
                'video_path' => $outputPath,
                'status' => VideoProjectStatus::Transcribed,
            ]);

            Log::info('[RemoveSilenceJob] Completed', [
                'project_id' => $this->project->id,
                'output' => $outputPath,
                'silence_removed' => count($silenceRegions),
            ]);

            $this->broadcastTaskCompleted(true, null, ['project_id' => $this->project->public_id]);
        } catch (\Throwable $e) {
            $this->project->markAsFailed($e->getMessage());
            $this->broadcastTaskCompleted(false, $e->getMessage());

            Log::error('[RemoveSilenceJob] Failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[RemoveSilenceJob] Failed permanently', [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);

        $this->project->markAsFailed($exception->getMessage());
    }
}
