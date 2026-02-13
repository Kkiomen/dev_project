<?php

namespace App\Jobs;

use App\Enums\VideoProjectStatus;
use App\Events\TaskCompleted;
use App\Events\TaskStarted;
use App\Models\VideoProject;
use App\Services\TranscriberService;
use App\Services\VideoEditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranscribeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(protected VideoProject $project) {}

    public function handle(TranscriberService $transcriber, VideoEditorService $editor): void
    {
        $taskId = 'video_transcribe_' . $this->project->id;

        broadcast(new TaskStarted(
            $this->project->user_id,
            $taskId,
            'video_transcription',
            ['project_id' => $this->project->public_id, 'title' => $this->project->title]
        ));

        try {
            // Update status
            $this->project->markAs(VideoProjectStatus::Transcribing);

            // Get video metadata
            $metadata = $editor->probe($this->project->video_path);
            $this->project->update([
                'width' => $metadata['width'] ?? null,
                'height' => $metadata['height'] ?? null,
                'duration' => $metadata['duration'] ?? null,
                'video_metadata' => $metadata,
            ]);

            // Transcribe
            $result = $transcriber->transcribe(
                $this->project->video_path,
                $this->project->language,
            );

            // Save transcription
            $this->project->update([
                'transcription' => $result,
                'language' => $result['language'] ?? $this->project->language,
                'language_probability' => $result['language_probability'] ?? null,
                'duration' => $result['duration'] ?? $this->project->duration,
                'status' => VideoProjectStatus::Transcribed,
            ]);

            Log::info('[TranscribeVideoJob] Completed', [
                'project_id' => $this->project->id,
                'segments' => count($result['segments'] ?? []),
                'language' => $result['language'] ?? 'unknown',
            ]);

            broadcast(new TaskCompleted(
                $this->project->user_id,
                $taskId,
                'video_transcription',
                true,
                null,
                ['project_id' => $this->project->public_id]
            ));
        } catch (\Throwable $e) {
            $this->project->markAsFailed($e->getMessage());

            broadcast(new TaskCompleted(
                $this->project->user_id,
                $taskId,
                'video_transcription',
                false,
                $e->getMessage(),
            ));

            Log::error('[TranscribeVideoJob] Failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[TranscribeVideoJob] Failed permanently', [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);

        $this->project->markAsFailed($exception->getMessage());
    }
}
