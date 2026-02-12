<?php

namespace App\Services\SmManager;

use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\SmPublishLog;
use App\Models\SmScheduledPost;
use App\Services\Concerns\LogsApiUsage;
use App\Services\Publishing\PublisherResolver;
use Illuminate\Support\Facades\Log;

class SmPublishOrchestratorService
{
    use LogsApiUsage;

    public function __construct(
        protected PublisherResolver $resolver
    ) {}

    /**
     * Publish a single scheduled post.
     *
     * 1. Validate post is approved and ready
     * 2. Resolve the correct adapter via PublisherResolver
     * 3. Publish and log the attempt
     */
    public function publish(SmScheduledPost $scheduledPost): array
    {
        $this->resetRequestId();
        $startTime = microtime(true);

        // 1. Validate readiness
        if (!$scheduledPost->isApproved()) {
            return [
                'success' => false,
                'error' => 'Post is not approved. Current approval status: ' . $scheduledPost->approval_status,
            ];
        }

        if ($scheduledPost->isPublished()) {
            return [
                'success' => false,
                'error' => 'Post has already been published.',
            ];
        }

        $brand = $scheduledPost->brand;
        $socialPost = $scheduledPost->socialPost;
        $platform = $scheduledPost->platform;

        if (!$brand || !$socialPost) {
            return [
                'success' => false,
                'error' => 'Associated brand or social post not found.',
            ];
        }

        Log::info('SmPublishOrchestrator: Starting publish', [
            'scheduled_post_id' => $scheduledPost->id,
            'platform' => $platform,
            'brand_id' => $brand->id,
        ]);

        // 2. Find PlatformPost for this platform
        $platformPost = $socialPost->platformPosts()
            ->where('platform', $platform)
            ->where('enabled', true)
            ->first();

        if (!$platformPost) {
            return [
                'success' => false,
                'error' => "No enabled platform post found for platform: {$platform}",
            ];
        }

        // 3. Resolve adapter and publish
        try {
            $adapter = $this->resolver->resolve($brand, $platformPost);
        } catch (\RuntimeException $e) {
            $scheduledPost->markAsFailed($e->getMessage());

            $this->logPublishAttempt(
                $scheduledPost,
                'no_adapter_available',
                null,
                $e->getMessage(),
                0
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        $publishStartTime = microtime(true);

        try {
            $result = $adapter->publish($platformPost);
            $durationMs = (int) ((microtime(true) - $publishStartTime) * 1000);
            $method = $result['method'] ?? 'unknown';

            if ($result['success']) {
                $externalPostId = $result['external_id'] ?? null;

                $scheduledPost->markAsPublished($externalPostId, $result);

                $this->logPublishAttempt(
                    $scheduledPost,
                    "{$method}_{$platform}",
                    200,
                    null,
                    $durationMs
                );

                Log::info('SmPublishOrchestrator: Publish succeeded', [
                    'scheduled_post_id' => $scheduledPost->id,
                    'platform' => $platform,
                    'method' => $method,
                    'external_post_id' => $externalPostId,
                ]);

                return [
                    'success' => true,
                    'external_post_id' => $externalPostId,
                    'method' => $method,
                ];
            }

            $errorMessage = $result['error'] ?? 'Publishing returned failure';
            $scheduledPost->markAsFailed($errorMessage);

            $this->logPublishAttempt(
                $scheduledPost,
                "{$method}_{$platform}_failed",
                null,
                $errorMessage,
                $durationMs
            );

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $publishStartTime) * 1000);

            $scheduledPost->markAsFailed($e->getMessage());

            $this->logPublishAttempt(
                $scheduledPost,
                "publish_{$platform}_exception",
                null,
                $e->getMessage(),
                $durationMs
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find and publish all due posts (approved and past their scheduled_at time).
     * Returns the count of successfully published posts.
     */
    public function publishDuePosts(): int
    {
        $duePosts = SmScheduledPost::readyToPublish()->get();
        $publishedCount = 0;

        Log::info('SmPublishOrchestrator: Processing due posts', [
            'total_due' => $duePosts->count(),
        ]);

        foreach ($duePosts as $scheduledPost) {
            try {
                $result = $this->publish($scheduledPost);

                if ($result['success']) {
                    $publishedCount++;
                } else {
                    Log::warning('SmPublishOrchestrator: Failed to publish post', [
                        'scheduled_post_id' => $scheduledPost->id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('SmPublishOrchestrator: Exception during publish', [
                    'scheduled_post_id' => $scheduledPost->id,
                    'error' => $e->getMessage(),
                ]);

                $scheduledPost->markAsFailed($e->getMessage());
            }
        }

        Log::info('SmPublishOrchestrator: Finished processing due posts', [
            'published' => $publishedCount,
            'total_due' => $duePosts->count(),
        ]);

        return $publishedCount;
    }

    /**
     * Log a publish attempt to sm_publish_logs for auditing.
     */
    protected function logPublishAttempt(
        SmScheduledPost $post,
        string $action,
        ?int $httpStatus,
        ?string $error,
        ?int $durationMs
    ): SmPublishLog {
        return SmPublishLog::create([
            'sm_scheduled_post_id' => $post->id,
            'action' => $action,
            'http_status' => $httpStatus,
            'error_message' => $error,
            'duration_ms' => $durationMs,
        ]);
    }
}
