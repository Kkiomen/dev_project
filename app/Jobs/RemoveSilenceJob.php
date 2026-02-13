<?php

namespace App\Jobs;

use App\Enums\VideoProjectStatus;
use App\Events\TaskCompleted;
use App\Events\TaskStarted;
use App\Models\VideoProject;
use App\Services\VideoEditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RemoveSilenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        protected VideoProject $project,
        protected float $minSilence = 0.5,
        protected float $padding = 0.1,
    ) {}

    public function handle(VideoEditorService $editor): void
    {
        $taskId = 'video_silence_' . $this->project->id;

        broadcast(new TaskStarted(
            $this->project->user_id,
            $taskId,
            'video_silence_removal',
            ['project_id' => $this->project->public_id, 'title' => $this->project->title]
        ));

        try {
            $this->project->markAs(VideoProjectStatus::Editing);

            if (!$this->project->hasTranscription()) {
                throw new \RuntimeException('No transcription available for silence removal');
            }

            $segments = $this->project->getSegments();

            // Filter segments: keep speech segments (those longer than minSilence gap between them)
            $speechSegments = [];
            foreach ($segments as $segment) {
                $speechSegments[] = [
                    'start' => $segment['start'],
                    'end' => $segment['end'],
                ];
            }

            $outputPath = 'video-projects/' . $this->project->user_id . '/silence_removed_' . time() . '.mp4';

            $editor->removeSilence(
                $this->project->video_path,
                $speechSegments,
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
            ]);

            broadcast(new TaskCompleted(
                $this->project->user_id,
                $taskId,
                'video_silence_removal',
                true,
                null,
                ['project_id' => $this->project->public_id]
            ));
        } catch (\Throwable $e) {
            $this->project->markAsFailed($e->getMessage());

            broadcast(new TaskCompleted(
                $this->project->user_id,
                $taskId,
                'video_silence_removal',
                false,
                $e->getMessage(),
            ));

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
