<?php

namespace App\Services\SmManager;

use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\SmPublishLog;
use App\Models\SmScheduledPost;
use App\Services\Concerns\LogsApiUsage;
use App\Services\Publishing\FacebookPublishingService;
use App\Services\Publishing\PublishingService;
use Illuminate\Support\Facades\Log;

class SmPublishOrchestratorService
{
    use LogsApiUsage;

    public function __construct(
        protected PublishingService $webhookService,
        protected FacebookPublishingService $facebookService
    ) {}

    /**
     * Publish a single scheduled post.
     *
     * 1. Validate post is approved and ready
     * 2. Try direct API publish (Facebook/Instagram)
     * 3. Fallback to n8n webhook for other platforms
     * 4. Log the attempt and update status
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

        // 2. Attempt direct API publish for supported platforms
        $directPublishResult = $this->tryDirectPublish($scheduledPost, $brand, $platform);

        if ($directPublishResult !== null) {
            return $directPublishResult;
        }

        // 3. Fallback to n8n webhook
        return $this->publishViaWebhook($scheduledPost, $brand, $socialPost, $platform, $startTime);
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
     * Try direct API publishing for platforms that support it (Facebook, Instagram).
     * Returns null if the platform does not support direct publishing.
     */
    protected function tryDirectPublish(SmScheduledPost $scheduledPost, Brand $brand, string $platform): ?array
    {
        $socialPost = $scheduledPost->socialPost;
        $startTime = microtime(true);

        // Only Facebook and Instagram support direct API publishing via our service
        if (!in_array($platform, ['facebook', 'instagram'], true)) {
            return null;
        }

        // Check if platform credentials exist
        $credential = $brand->platformCredentials()
            ->where('platform', $platform)
            ->first();

        if (!$credential || $credential->isExpired()) {
            Log::info('SmPublishOrchestrator: No valid credentials for direct publish, falling back to webhook', [
                'platform' => $platform,
                'brand_id' => $brand->id,
            ]);

            return null;
        }

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

        $log = $this->logExternalStart(
            $brand,
            "sm_publish_{$platform}",
            ApiProvider::FACEBOOK,
            "/{$platform}/publish",
            ['scheduled_post_id' => $scheduledPost->id]
        );

        try {
            $result = match ($platform) {
                'facebook' => $this->facebookService->publishToFacebook($platformPost),
                'instagram' => $this->facebookService->publishToInstagram($platformPost),
            };

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $externalPostId = $result['id'] ?? $result['post_id'] ?? $result['media_id'] ?? null;

            $this->completeExternalLog($log, $result, 200, $durationMs);

            // Mark as published
            $scheduledPost->markAsPublished($externalPostId, $result);

            $this->logPublishAttempt(
                $scheduledPost,
                "direct_api_{$platform}",
                200,
                null,
                $durationMs
            );

            Log::info('SmPublishOrchestrator: Direct API publish succeeded', [
                'scheduled_post_id' => $scheduledPost->id,
                'platform' => $platform,
                'external_post_id' => $externalPostId,
            ]);

            return [
                'success' => true,
                'external_post_id' => $externalPostId,
                'method' => 'direct_api',
                'platform_response' => $result,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->failLog($log, $e->getMessage(), $durationMs);

            $this->logPublishAttempt(
                $scheduledPost,
                "direct_api_{$platform}_failed",
                null,
                $e->getMessage(),
                $durationMs
            );

            Log::warning('SmPublishOrchestrator: Direct API publish failed, falling back to webhook', [
                'scheduled_post_id' => $scheduledPost->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            // Fall back to webhook instead of returning failure
            return null;
        }
    }

    /**
     * Publish via the n8n webhook as a fallback mechanism.
     */
    protected function publishViaWebhook(
        SmScheduledPost $scheduledPost,
        Brand $brand,
        $socialPost,
        string $platform,
        float $startTime
    ): array {
        if (!$this->webhookService->isConfigured()) {
            $scheduledPost->markAsFailed('No publishing method available: webhook not configured');

            $this->logPublishAttempt(
                $scheduledPost,
                'webhook_not_configured',
                null,
                'Webhook URL is not configured',
                0
            );

            return [
                'success' => false,
                'error' => 'No publishing method available. Configure n8n webhook or platform credentials.',
            ];
        }

        $webhookStartTime = microtime(true);

        $log = $this->logExternalStart(
            $brand,
            "sm_publish_webhook_{$platform}",
            ApiProvider::OPENAI, // n8n is internal, using generic provider
            config('services.n8n.webhook_url', '/n8n/webhook'),
            ['scheduled_post_id' => $scheduledPost->id, 'platform' => $platform]
        );

        try {
            $result = $this->webhookService->sendToWebhook($socialPost, $platform);
            $durationMs = (int) ((microtime(true) - $webhookStartTime) * 1000);

            if ($result['success']) {
                $this->completeExternalLog($log, $result, 200, $durationMs);

                // Webhook posts are pending until callback confirms
                $scheduledPost->update([
                    'status' => 'publishing',
                ]);

                $this->logPublishAttempt(
                    $scheduledPost,
                    "webhook_{$platform}",
                    200,
                    null,
                    $durationMs
                );

                Log::info('SmPublishOrchestrator: Webhook publish dispatched', [
                    'scheduled_post_id' => $scheduledPost->id,
                    'platform' => $platform,
                ]);

                return [
                    'success' => true,
                    'method' => 'webhook',
                    'webhook_response' => $result['webhook_response'] ?? null,
                ];
            }

            $errorMessage = $result['error'] ?? 'Webhook returned failure';
            $this->failLog($log, $errorMessage, $durationMs);
            $scheduledPost->markAsFailed($errorMessage);

            $this->logPublishAttempt(
                $scheduledPost,
                "webhook_{$platform}_failed",
                null,
                $errorMessage,
                $durationMs
            );

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $webhookStartTime) * 1000);

            $this->failLog($log, $e->getMessage(), $durationMs);
            $scheduledPost->markAsFailed($e->getMessage());

            $this->logPublishAttempt(
                $scheduledPost,
                "webhook_{$platform}_exception",
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
