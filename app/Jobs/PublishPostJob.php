<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\SocialPost;
use App\Services\Publishing\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected SocialPost $post,
        protected ?string $platform = null
    ) {}

    public function handle(PublishingService $publishingService): void
    {
        // Verify post is still ready for publishing
        if (!in_array($this->post->status, [PostStatus::Approved, PostStatus::Scheduled])) {
            Log::warning('Post not ready for publishing', [
                'post_id' => $this->post->public_id,
                'status' => $this->post->status->value,
            ]);
            return;
        }

        // Update status to scheduled
        $this->post->update([
            'status' => PostStatus::Scheduled,
        ]);

        if ($this->platform) {
            // Publish to specific platform
            $result = $publishingService->sendToWebhook($this->post, $this->platform);

            if (!$result['success']) {
                Log::error('Failed to publish to platform', [
                    'post_id' => $this->post->public_id,
                    'platform' => $this->platform,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } else {
            // Publish to all enabled platforms
            $results = $publishingService->publishToAllPlatforms($this->post);

            foreach ($results as $platform => $result) {
                if (!$result['success']) {
                    Log::error('Failed to publish to platform', [
                        'post_id' => $this->post->public_id,
                        'platform' => $platform,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PublishPostJob failed', [
            'post_id' => $this->post->public_id,
            'platform' => $this->platform,
            'exception' => $exception->getMessage(),
        ]);

        // Mark post as failed
        $this->post->update([
            'status' => PostStatus::Failed,
        ]);
    }
}
