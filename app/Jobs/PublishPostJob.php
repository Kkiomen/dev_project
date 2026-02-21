<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Enums\PublishStatus;
use App\Models\PlatformPost;
use App\Models\SocialPost;
use App\Services\Publishing\PublisherResolver;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected SocialPost $post,
        protected ?string $platform = null
    ) {}

    protected function taskType(): string { return 'post_publishing'; }
    protected function taskUserId(): int { return $this->post->user_id; }
    protected function taskModelId(): string|int { return $this->post->id; }

    public function handle(PublisherResolver $resolver): void
    {
        $this->broadcastTaskStarted();

        try {
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
                $this->publishToPlatform($this->platform, $resolver);
            } else {
                // Publish to all enabled platforms
                foreach ($this->post->platformPosts()->where('enabled', true)->get() as $platformPost) {
                    $this->publishToPlatform(
                        $platformPost->platform->value,
                        $resolver
                    );
                }
            }

            // Check if all platforms are published
            $this->updatePostStatusIfComplete();

            $this->broadcastTaskCompleted(true, null, [
                'post_id' => $this->post->public_id,
            ]);
        } catch (\Exception $e) {
            $this->broadcastTaskCompleted(false, $e->getMessage());
            throw $e;
        }
    }

    private function publishToPlatform(string $platform, PublisherResolver $resolver): void
    {
        $platformPost = $this->post->platformPosts()
            ->where('platform', $platform)
            ->first();

        if (!$platformPost || !$platformPost->enabled) {
            return;
        }

        try {
            $brand = $this->post->brand;
            $adapter = $resolver->resolve($brand, $platformPost);
            $result = $adapter->publish($platformPost);

            if ($result['success']) {
                $platformPost->update([
                    'publish_status' => PublishStatus::Published,
                    'external_id' => $result['external_id'] ?? null,
                    'external_url' => $result['external_url'] ?? null,
                    'published_at' => now(),
                ]);

                Log::info('Post published', [
                    'post_id' => $this->post->public_id,
                    'platform' => $platform,
                    'method' => $result['method'],
                    'external_id' => $result['external_id'] ?? null,
                ]);
            } else {
                throw new \RuntimeException($result['error'] ?? 'Publishing failed');
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
