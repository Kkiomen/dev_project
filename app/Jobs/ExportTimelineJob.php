<?php

namespace App\Jobs;

use App\Enums\VideoProjectStatus;
use App\Models\VideoProject;
use App\Services\VideoEditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExportTimelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        protected VideoProject $project,
        protected array $edl,
        protected ?array $renderPlan = null,
        protected array $mediaFiles = [],
    ) {}

    public function handle(VideoEditorService $editor): void
    {
        $useRenderPlan = $this->renderPlan !== null;

        Log::info('[ExportTimelineJob] Starting export', [
            'project_id' => $this->project->id,
            'mode' => $useRenderPlan ? 'composition' : 'edl',
        ]);

        try {
            $this->project->update(['status' => VideoProjectStatus::Rendering]);

            $outputPath = 'video-projects/' . $this->project->user_id . '/timeline_' . time() . '.mp4';

            if ($useRenderPlan) {
                $editor->renderComposition(
                    $this->project->video_path,
                    $this->renderPlan,
                    $this->mediaFiles,
                    $outputPath,
                );
            } else {
                $editor->exportTimeline(
                    $this->project->video_path,
                    $this->edl,
                    $outputPath,
                );
            }

            $this->project->update([
                'output_path' => $outputPath,
                'status' => VideoProjectStatus::Completed,
                'completed_at' => now(),
            ]);

            Log::info('[ExportTimelineJob] Export completed', [
                'project_id' => $this->project->id,
                'output' => $outputPath,
                'mode' => $useRenderPlan ? 'composition' : 'edl',
            ]);
        } catch (\Exception $e) {
            Log::error('[ExportTimelineJob] Failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            $this->project->update([
                'status' => VideoProjectStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
