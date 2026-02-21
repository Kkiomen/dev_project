<?php

namespace App\Jobs\SmManager;

use App\Models\SmScheduledPost;
use App\Services\SmManager\SmPublishOrchestratorService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmPublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 60;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected SmScheduledPost $scheduledPost
    ) {}

    protected function taskType(): string { return 'sm_post_publish'; }
    protected function taskUserId(): int { return $this->scheduledPost->brand->user_id; }
    protected function taskModelId(): string|int { return $this->scheduledPost->id; }

    public function handle(SmPublishOrchestratorService $orchestrator): void
    {
        $this->broadcastTaskStarted();

        try {
            $result = $orchestrator->publish($this->scheduledPost);

            if ($result['success']) {
                Log::info('SmPublishScheduledPostJob: post published', [
                    'scheduled_post_id' => $this->scheduledPost->id,
                    'platform' => $this->scheduledPost->platform,
                    'method' => $result['method'] ?? 'unknown',
                    'external_post_id' => $result['external_post_id'] ?? null,
                ]);

                $this->broadcastTaskCompleted(true);
            } else {
                Log::warning('SmPublishScheduledPostJob: publish returned failure', [
                    'scheduled_post_id' => $this->scheduledPost->id,
                    'platform' => $this->scheduledPost->platform,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                throw new \RuntimeException($result['error'] ?? 'Publish failed');
            }
        } catch (\Exception $e) {
            Log::error('SmPublishScheduledPostJob: failed', [
                'scheduled_post_id' => $this->scheduledPost->id,
                'platform' => $this->scheduledPost->platform,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmPublishScheduledPostJob: job failed permanently', [
            'scheduled_post_id' => $this->scheduledPost->id,
            'platform' => $this->scheduledPost->platform,
            'error' => $exception->getMessage(),
        ]);

        if ($this->scheduledPost->canRetry()) {
            return;
        }

        $this->scheduledPost->markAsFailed($exception->getMessage());
    }
}
