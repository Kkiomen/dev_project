<?php

namespace App\Services\Publishing\Adapters;

use App\Contracts\SocialPublisherInterface;
use App\Models\Brand;
use App\Models\PlatformPost;
use App\Services\Publishing\PublishingService;

class WebhookPublishingAdapter implements SocialPublisherInterface
{
    public function __construct(
        protected PublishingService $publishingService
    ) {}

    public function publish(PlatformPost $platformPost): array
    {
        $socialPost = $platformPost->socialPost;
        $platform = $platformPost->platform->value;

        $result = $this->publishingService->sendToWebhook($socialPost, $platform);

        if (!$result['success']) {
            return [
                'success' => false,
                'method' => 'webhook',
                'error' => $result['error'] ?? 'Webhook failed',
            ];
        }

        return [
            'success' => true,
            'method' => 'webhook',
        ];
    }

    public function supportsPlatform(string $platform): bool
    {
        return true;
    }

    public function isConfiguredForBrand(Brand $brand): bool
    {
        return $this->publishingService->isConfigured();
    }
}
