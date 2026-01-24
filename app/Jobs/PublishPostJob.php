<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Enums\PublishStatus;
use App\Models\PlatformPost;
use App\Models\SocialPost;
use App\Services\Publishing\FacebookPublishingService;
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

    public function handle(
        PublishingService $webhookService,
        FacebookPublishingService $facebookService
    ): void {
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
            $this->publishToPlatform($this->platform, $facebookService, $webhookService);
        } else {
            // Publish to all enabled platforms
            foreach ($this->post->platformPosts()->where('enabled', true)->get() as $platformPost) {
                $this->publishToPlatform(
                    $platformPost->platform->value,
                    $facebookService,
                    $webhookService
                );
            }
        }

        // Check if all platforms are published
        $this->updatePostStatusIfComplete();
    }

    /**
     * Publish to a specific platform, with direct API or fallback to webhook.
     */
    private function publishToPlatform(
        string $platform,
        FacebookPublishingService $facebookService,
        PublishingService $webhookService
    ): void {
        $platformPost = $this->post->platformPosts()
            ->where('platform', $platform)
            ->first();

        if (!$platformPost || !$platformPost->enabled) {
            return;
        }

        try {
            // Check if we have direct API credentials for this platform
            $credential = $this->post->brand->getPlatformCredential($platform);

            if ($credential && !$credential->isExpired()) {
                // Direct API publishing
                $result = $this->publishDirectly($platform, $platformPost, $facebookService);

                $platformPost->update([
                    'publish_status' => PublishStatus::Published,
                    'external_id' => $result['id'] ?? null,
                    'published_at' => now(),
                ]);

                Log::info('Post published directly via API', [
                    'post_id' => $this->post->public_id,
                    'platform' => $platform,
                    'external_id' => $result['id'] ?? null,
                ]);
            } else {
                // Fallback to n8n webhook
                $result = $webhookService->sendToWebhook($this->post, $platform);

                if (!$result['success']) {
                    throw new \RuntimeException($result['error'] ?? 'Webhook failed');
                }

                Log::info('Post sent to webhook', [
                    'post_id' => $this->post->public_id,
                    'platform' => $platform,
                ]);
            }
        } catch (\Exception $e) {
            $platformPost->update([
                'publish_status' => PublishStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Platform publish failed', [
                'post_id' => $this->post->public_id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish directly via platform API.
     */
    private function publishDirectly(
        string $platform,
        PlatformPost $platformPost,
        FacebookPublishingService $facebookService
    ): array {
        return match ($platform) {
            'facebook' => $facebookService->publishToFacebook($platformPost),
            'instagram' => $facebookService->publishToInstagram($platformPost),
            default => throw new \RuntimeException("Direct publishing not supported for: {$platform}"),
        };
    }

    /**
     * Update main post status if all platforms are done.
     */
    private function updatePostStatusIfComplete(): void
    {
        $enabledPlatforms = $this->post->platformPosts()->where('enabled', true)->get();

        $allPublished = $enabledPlatforms->every(
            fn ($p) => $p->publish_status === PublishStatus::Published
        );

        $anyFailed = $enabledPlatforms->contains(
            fn ($p) => $p->publish_status === PublishStatus::Failed
        );

        if ($allPublished) {
            $this->post->update(['status' => PostStatus::Published]);
        } elseif ($anyFailed) {
            $this->post->update(['status' => PostStatus::Failed]);
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
