<?php

namespace App\Services\Publishing\Adapters;

use App\Contracts\SocialPublisherInterface;
use App\Enums\AiProvider;
use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\PlatformPost;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetLatePublishingAdapter implements SocialPublisherInterface
{
    use LogsApiUsage;

    private const API_BASE_URL = 'https://getlate.dev/api/v1';

    private const SUPPORTED_PLATFORMS = [
        'instagram',
        'facebook',
        'tiktok',
        'linkedin',
        'x',
        'youtube',
    ];

    public function publish(PlatformPost $platformPost): array
    {
        $socialPost = $platformPost->socialPost;
        $brand = $socialPost->brand;
        $platform = $platformPost->platform->value;

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::GetLate);

        if (!$apiKey) {
            return [
                'success' => false,
                'method' => 'getlate',
                'error' => 'GetLate API key not configured for this brand',
            ];
        }

        $payload = $this->buildPayload($platformPost, $socialPost, $platform);

        $this->resetRequestId();
        $startTime = microtime(true);

        $log = $this->logExternalStart(
            $brand,
            "getlate_publish_{$platform}",
            ApiProvider::GETLATE,
            '/v1/posts',
            ['platform_post_id' => $platformPost->id, 'platform' => $platform]
        );

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(self::API_BASE_URL . '/posts', $payload);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? $response->body();
                $this->failLog($log, "GetLate API error: {$errorMessage}", $durationMs, $response->status());

                Log::error('GetLate publish failed', [
                    'platform_post_id' => $platformPost->id,
                    'platform' => $platform,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'method' => 'getlate',
                    'error' => "GetLate API error ({$response->status()}): {$errorMessage}",
                ];
            }

            $result = $response->json();
            $this->completeExternalLog($log, $result, $response->status(), $durationMs);

            $externalId = $result['id'] ?? $result['post_id'] ?? null;
            $externalUrl = $result['url'] ?? $result['post_url'] ?? null;

            Log::info('GetLate publish succeeded', [
                'platform_post_id' => $platformPost->id,
                'platform' => $platform,
                'external_id' => $externalId,
            ]);

            return [
                'success' => true,
                'external_id' => $externalId,
                'external_url' => $externalUrl,
                'method' => 'getlate',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('GetLate publish exception', [
                'platform_post_id' => $platformPost->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'method' => 'getlate',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function supportsPlatform(string $platform): bool
    {
        return in_array($platform, self::SUPPORTED_PLATFORMS, true);
    }

    public function isConfiguredForBrand(Brand $brand): bool
    {
        return BrandAiKey::getKeyForProvider($brand, AiProvider::GetLate) !== null;
    }

    private function buildPayload(PlatformPost $platformPost, $socialPost, string $platform): array
    {
        $payload = [
            'content' => $platformPost->getCaption(),
            'platforms' => [$platform],
        ];

        $mediaUrls = $socialPost->media->pluck('url')->filter()->values()->toArray();
        if (!empty($mediaUrls)) {
            $payload['mediaItems'] = $mediaUrls;
        }

        if ($socialPost->scheduled_at) {
            $payload['scheduledFor'] = $socialPost->scheduled_at->toIso8601String();
        } else {
            $payload['publishNow'] = true;
        }

        $title = $platformPost->video_title ?? $socialPost->title;
        if ($title) {
            $payload['title'] = $title;
        }

        if (!empty($platformPost->hashtags)) {
            $payload['hashtags'] = $platformPost->hashtags;
        }

        return $payload;
    }
}
