<?php

namespace App\Services\Publishing;

use App\Enums\PostStatus;
use App\Enums\PublishStatus;
use App\Models\PlatformPost;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublishingService
{
    protected ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.webhook_url');
    }

    /**
     * Check if the publishing service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->webhookUrl);
    }

    /**
     * Send post to n8n for publishing.
     */
    public function sendToWebhook(SocialPost $post, string $platform): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Publishing webhook is not configured');
        }

        $platformPost = $post->platformPosts()
            ->where('platform', $platform)
            ->first();

        if (!$platformPost) {
            throw new \RuntimeException("Platform post not found for platform: {$platform}");
        }

        if (!$platformPost->enabled) {
            throw new \RuntimeException("Platform {$platform} is not enabled for this post");
        }

        $payload = $this->buildPayload($post, $platformPost, $platform);

        try {
            $response = Http::timeout(30)->post($this->webhookUrl, $payload);

            if (!$response->successful()) {
                Log::error('n8n webhook failed', [
                    'post_id' => $post->public_id,
                    'platform' => $platform,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Webhook request failed: ' . $response->status(),
                ];
            }

            // Update platform post status
            $platformPost->update([
                'publish_status' => PublishStatus::Pending,
            ]);

            return [
                'success' => true,
                'webhook_response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('n8n webhook exception', [
                'post_id' => $post->public_id,
                'platform' => $platform,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build payload for n8n webhook.
     */
    protected function buildPayload(SocialPost $post, PlatformPost $platformPost, string $platform): array
    {
        return [
            'post_id' => $post->public_id,
            'platform' => $platform,
            'title' => $post->title,
            'caption' => $platformPost->getCaption(),
            'media_urls' => $post->media->pluck('url')->toArray(),
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'brand' => $post->brand ? [
                'id' => $post->brand->public_id,
                'name' => $post->brand->name,
            ] : null,
            'platform_data' => $platformPost->platform_data ?? [],
            'callback_url' => route('webhooks.publish-result'),
        ];
    }

    /**
     * Handle callback from n8n after publishing.
     */
    public function handleCallback(array $data): array
    {
        $postId = $data['post_id'] ?? null;
        $platform = $data['platform'] ?? null;
        $success = $data['success'] ?? false;
        $externalId = $data['external_id'] ?? null;
        $error = $data['error'] ?? null;

        if (!$postId || !$platform) {
            return [
                'success' => false,
                'error' => 'Missing post_id or platform',
            ];
        }

        $post = SocialPost::findByPublicId($postId);

        if (!$post) {
            return [
                'success' => false,
                'error' => 'Post not found',
            ];
        }

        $platformPost = $post->platformPosts()
            ->where('platform', $platform)
            ->first();

        if (!$platformPost) {
            return [
                'success' => false,
                'error' => 'Platform post not found',
            ];
        }

        if ($success) {
            $platformPost->update([
                'publish_status' => PublishStatus::Published,
                'published_at' => now(),
                'external_id' => $externalId,
            ]);

            // Check if all enabled platforms are published
            $allPublished = $post->platformPosts()
                ->where('enabled', true)
                ->where('publish_status', '!=', PublishStatus::Published)
                ->doesntExist();

            if ($allPublished) {
                $post->update([
                    'status' => PostStatus::Published,
                ]);
            }

            Log::info('Post published successfully', [
                'post_id' => $postId,
                'platform' => $platform,
                'external_id' => $externalId,
            ]);
        } else {
            $platformPost->update([
                'publish_status' => PublishStatus::Failed,
                'error_message' => $error,
            ]);

            // Mark post as failed if any platform fails
            $post->update([
                'status' => PostStatus::Failed,
            ]);

            Log::error('Post publishing failed', [
                'post_id' => $postId,
                'platform' => $platform,
                'error' => $error,
            ]);
        }

        return [
            'success' => true,
            'post_status' => $post->fresh()->status->value,
            'platform_status' => $platformPost->fresh()->publish_status->value,
        ];
    }

    /**
     * Publish to all enabled platforms.
     */
    public function publishToAllPlatforms(SocialPost $post): array
    {
        $results = [];

        foreach ($post->platformPosts()->where('enabled', true)->get() as $platformPost) {
            $results[$platformPost->platform->value] = $this->sendToWebhook(
                $post,
                $platformPost->platform->value
            );
        }

        return $results;
    }
}
