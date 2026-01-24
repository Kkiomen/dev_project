<?php

namespace App\Services\Publishing;

use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\PlatformPost;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublishingService
{
    use LogsApiUsage;

    private string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('services.facebook.graph_version', 'v18.0');
    }

    /**
     * Publish a post to Facebook.
     */
    public function publishToFacebook(PlatformPost $platformPost): array
    {
        $post = $platformPost->socialPost;
        $credential = $post->brand->platformCredentials()
            ->where('platform', 'facebook')
            ->first();

        if (!$credential) {
            throw new \RuntimeException('Facebook not connected for this brand');
        }

        if ($credential->isExpired()) {
            throw new \RuntimeException('Facebook token has expired');
        }

        $pageId = $credential->getPageId();
        $accessToken = $credential->access_token;

        if (!$pageId) {
            throw new \RuntimeException('Facebook Page ID not found in credentials');
        }

        // Determine post type (with or without media)
        if ($post->media->isEmpty()) {
            return $this->publishTextPost($pageId, $accessToken, $platformPost);
        }

        $firstMedia = $post->media->first();
        $isVideo = str_starts_with($firstMedia->mime_type ?? '', 'video/');

        if ($isVideo) {
            return $this->publishVideoPost($pageId, $accessToken, $platformPost);
        }

        if ($post->media->count() > 1) {
            return $this->publishMultiPhotoPost($pageId, $accessToken, $platformPost);
        }

        return $this->publishPhotoPost($pageId, $accessToken, $platformPost);
    }

    /**
     * Publish a post to Instagram.
     */
    public function publishToInstagram(PlatformPost $platformPost): array
    {
        $post = $platformPost->socialPost;
        $credential = $post->brand->platformCredentials()
            ->where('platform', 'instagram')
            ->first();

        if (!$credential) {
            throw new \RuntimeException('Instagram not connected for this brand');
        }

        if ($credential->isExpired()) {
            throw new \RuntimeException('Instagram token has expired');
        }

        $igUserId = $credential->getInstagramBusinessId();
        $accessToken = $credential->access_token;

        if (!$igUserId) {
            throw new \RuntimeException('Instagram Business Account ID not found in credentials');
        }

        // Instagram requires media
        if ($post->media->isEmpty()) {
            throw new \RuntimeException('Instagram requires at least one image or video');
        }

        $firstMedia = $post->media->first();
        $isVideo = str_starts_with($firstMedia->mime_type ?? '', 'video/');

        if ($post->media->count() > 1) {
            return $this->publishInstagramCarousel($igUserId, $accessToken, $platformPost);
        }

        if ($isVideo) {
            return $this->publishInstagramReel($igUserId, $accessToken, $platformPost);
        }

        return $this->publishInstagramPhoto($igUserId, $accessToken, $platformPost);
    }

    /**
     * Publish text-only post to Facebook.
     */
    private function publishTextPost(string $pageId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $brand = $platformPost->socialPost->brand;
        $endpoint = "/{$pageId}/feed";

        $log = $this->logExternalStart(
            $brand,
            'facebook_text_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id]
        );

        try {
            $response = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/feed",
                [
                    'message' => $platformPost->getCaption(),
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                Log::error('Facebook text post failed', [
                    'response' => $response->body(),
                    'platform_post_id' => $platformPost->id,
                ]);
                $this->failLog($log, 'Facebook text post failed: ' . $response->body(), $durationMs, $response->status());
                throw new \RuntimeException('Facebook text post failed: ' . $response->body());
            }

            $result = $response->json();
            $this->completeExternalLog($log, ['post_id' => $result['id'] ?? null], $response->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Publish single photo to Facebook.
     */
    private function publishPhotoPost(string $pageId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $post = $platformPost->socialPost;
        $brand = $post->brand;
        $media = $post->media->first();
        $endpoint = "/{$pageId}/photos";

        $log = $this->logExternalStart(
            $brand,
            'facebook_photo_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id]
        );

        try {
            $response = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/photos",
                [
                    'url' => $media->url,
                    'caption' => $platformPost->getCaption(),
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                Log::error('Facebook photo post failed', [
                    'response' => $response->body(),
                    'platform_post_id' => $platformPost->id,
                ]);
                $this->failLog($log, 'Facebook photo post failed: ' . $response->body(), $durationMs, $response->status());
                throw new \RuntimeException('Facebook photo post failed: ' . $response->body());
            }

            $result = $response->json();
            $this->completeExternalLog($log, ['post_id' => $result['id'] ?? null], $response->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Publish multiple photos to Facebook.
     */
    private function publishMultiPhotoPost(string $pageId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $post = $platformPost->socialPost;
        $brand = $post->brand;
        $endpoint = "/{$pageId}/feed";
        $photoIds = [];

        $log = $this->logExternalStart(
            $brand,
            'facebook_multi_photo_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id, 'media_count' => $post->media->count()]
        );

        try {
            // Upload photos without publishing
            foreach ($post->media as $media) {
                $response = Http::post(
                    "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/photos",
                    [
                        'url' => $media->url,
                        'published' => false,
                        'access_token' => $token,
                    ]
                );

                if (!$response->successful()) {
                    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                    $this->failLog($log, 'Failed to upload photo: ' . $response->body(), $durationMs, $response->status());
                    throw new \RuntimeException('Failed to upload photo: ' . $response->body());
                }

                $photoIds[] = ['media_fbid' => $response->json()['id']];
            }

            // Create post with attached photos
            $response = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/feed",
                [
                    'message' => $platformPost->getCaption(),
                    'attached_media' => json_encode($photoIds),
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $this->failLog($log, 'Facebook multi-photo post failed: ' . $response->body(), $durationMs, $response->status());
                throw new \RuntimeException('Facebook multi-photo post failed: ' . $response->body());
            }

            $result = $response->json();
            $this->completeExternalLog($log, ['post_id' => $result['id'] ?? null], $response->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Publish video to Facebook.
     */
    private function publishVideoPost(string $pageId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $post = $platformPost->socialPost;
        $brand = $post->brand;
        $media = $post->media->first();
        $endpoint = "/{$pageId}/videos";

        $log = $this->logExternalStart(
            $brand,
            'facebook_video_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id]
        );

        try {
            $response = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/videos",
                [
                    'file_url' => $media->url,
                    'description' => $platformPost->getCaption(),
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $this->failLog($log, 'Facebook video post failed: ' . $response->body(), $durationMs, $response->status());
                throw new \RuntimeException('Facebook video post failed: ' . $response->body());
            }

            $result = $response->json();
            $this->completeExternalLog($log, ['video_id' => $result['id'] ?? null], $response->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Publish single photo to Instagram.
     */
    private function publishInstagramPhoto(string $igUserId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $post = $platformPost->socialPost;
        $brand = $post->brand;
        $media = $post->media->first();
        $endpoint = "/{$igUserId}/media";

        $log = $this->logExternalStart(
            $brand,
            'instagram_photo_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id]
        );

        try {
            // Step 1: Create media container
            $containerResponse = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media",
                [
                    'image_url' => $media->url,
                    'caption' => $platformPost->getCaption(),
                    'access_token' => $token,
                ]
            );

            if (!$containerResponse->successful()) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->failLog($log, 'Instagram container creation failed: ' . $containerResponse->body(), $durationMs, $containerResponse->status());
                throw new \RuntimeException('Instagram container creation failed: ' . $containerResponse->body());
            }

            $containerId = $containerResponse->json()['id'];

            // Wait for processing
            $this->waitForContainerReady($containerId, $token);

            // Step 2: Publish the container
            $publishResponse = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media_publish",
                [
                    'creation_id' => $containerId,
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$publishResponse->successful()) {
                $this->failLog($log, 'Instagram publish failed: ' . $publishResponse->body(), $durationMs, $publishResponse->status());
                throw new \RuntimeException('Instagram publish failed: ' . $publishResponse->body());
            }

            $result = $publishResponse->json();
            $this->completeExternalLog($log, ['media_id' => $result['id'] ?? null], $publishResponse->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Publish carousel to Instagram.
     */
    private function publishInstagramCarousel(string $igUserId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $post = $platformPost->socialPost;
        $brand = $post->brand;
        $endpoint = "/{$igUserId}/media";
        $childrenIds = [];

        $log = $this->logExternalStart(
            $brand,
            'instagram_carousel_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id, 'media_count' => $post->media->count()]
        );

        try {
            // Create containers for each media item
            foreach ($post->media as $media) {
                $isVideo = str_starts_with($media->mime_type ?? '', 'video/');

                $containerData = [
                    'is_carousel_item' => true,
                    'access_token' => $token,
                ];

                if ($isVideo) {
                    $containerData['media_type'] = 'VIDEO';
                    $containerData['video_url'] = $media->url;
                } else {
                    $containerData['image_url'] = $media->url;
                }

                $response = Http::post(
                    "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media",
                    $containerData
                );

                if (!$response->successful()) {
                    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                    $this->failLog($log, 'Instagram carousel item creation failed: ' . $response->body(), $durationMs, $response->status());
                    throw new \RuntimeException('Instagram carousel item creation failed: ' . $response->body());
                }

                $containerId = $response->json()['id'];
                $this->waitForContainerReady($containerId, $token);
                $childrenIds[] = $containerId;
            }

            // Create carousel container
            $carouselResponse = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media",
                [
                    'media_type' => 'CAROUSEL',
                    'children' => implode(',', $childrenIds),
                    'caption' => $platformPost->getCaption(),
                    'access_token' => $token,
                ]
            );

            if (!$carouselResponse->successful()) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->failLog($log, 'Instagram carousel creation failed: ' . $carouselResponse->body(), $durationMs, $carouselResponse->status());
                throw new \RuntimeException('Instagram carousel creation failed: ' . $carouselResponse->body());
            }

            $carouselId = $carouselResponse->json()['id'];
            $this->waitForContainerReady($carouselId, $token);

            // Publish carousel
            $publishResponse = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media_publish",
                [
                    'creation_id' => $carouselId,
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$publishResponse->successful()) {
                $this->failLog($log, 'Instagram carousel publish failed: ' . $publishResponse->body(), $durationMs, $publishResponse->status());
                throw new \RuntimeException('Instagram carousel publish failed: ' . $publishResponse->body());
            }

            $result = $publishResponse->json();
            $this->completeExternalLog($log, ['media_id' => $result['id'] ?? null], $publishResponse->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Publish Reel to Instagram.
     */
    private function publishInstagramReel(string $igUserId, string $token, PlatformPost $platformPost): array
    {
        $startTime = microtime(true);
        $post = $platformPost->socialPost;
        $brand = $post->brand;
        $media = $post->media->first();
        $endpoint = "/{$igUserId}/media";

        $log = $this->logExternalStart(
            $brand,
            'instagram_reel_post',
            ApiProvider::FACEBOOK,
            $endpoint,
            ['platform_post_id' => $platformPost->id]
        );

        try {
            // Create reel container
            $containerResponse = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media",
                [
                    'media_type' => 'REELS',
                    'video_url' => $media->url,
                    'caption' => $platformPost->getCaption(),
                    'access_token' => $token,
                ]
            );

            if (!$containerResponse->successful()) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->failLog($log, 'Instagram reel container creation failed: ' . $containerResponse->body(), $durationMs, $containerResponse->status());
                throw new \RuntimeException('Instagram reel container creation failed: ' . $containerResponse->body());
            }

            $containerId = $containerResponse->json()['id'];

            // Wait for processing (reels take longer)
            $this->waitForContainerReady($containerId, $token, maxAttempts: 30);

            // Publish
            $publishResponse = Http::post(
                "https://graph.facebook.com/{$this->graphVersion}/{$igUserId}/media_publish",
                [
                    'creation_id' => $containerId,
                    'access_token' => $token,
                ]
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$publishResponse->successful()) {
                $this->failLog($log, 'Instagram reel publish failed: ' . $publishResponse->body(), $durationMs, $publishResponse->status());
                throw new \RuntimeException('Instagram reel publish failed: ' . $publishResponse->body());
            }

            $result = $publishResponse->json();
            $this->completeExternalLog($log, ['media_id' => $result['id'] ?? null], $publishResponse->status(), $durationMs);

            return $result;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Wait for Instagram media container to be ready for publishing.
     */
    private function waitForContainerReady(string $containerId, string $token, int $maxAttempts = 10): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::get(
                "https://graph.facebook.com/{$this->graphVersion}/{$containerId}",
                [
                    'fields' => 'status_code',
                    'access_token' => $token,
                ]
            );

            if ($response->successful()) {
                $status = $response->json()['status_code'] ?? null;

                if ($status === 'FINISHED') {
                    return;
                }

                if ($status === 'ERROR') {
                    throw new \RuntimeException('Instagram container processing failed');
                }
            }

            sleep(2);
        }

        throw new \RuntimeException('Instagram container processing timeout');
    }
}
