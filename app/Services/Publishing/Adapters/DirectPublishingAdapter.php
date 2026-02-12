<?php

namespace App\Services\Publishing\Adapters;

use App\Contracts\SocialPublisherInterface;
use App\Models\Brand;
use App\Models\PlatformPost;
use App\Services\Publishing\FacebookPublishingService;

class DirectPublishingAdapter implements SocialPublisherInterface
{
    public function __construct(
        protected FacebookPublishingService $facebookService
    ) {}

    public function publish(PlatformPost $platformPost): array
    {
        $platform = $platformPost->platform->value;

        $result = match ($platform) {
            'facebook' => $this->facebookService->publishToFacebook($platformPost),
            'instagram' => $this->facebookService->publishToInstagram($platformPost),
            default => throw new \RuntimeException("Direct publishing not supported for: {$platform}"),
        };

        $externalId = $result['id'] ?? $result['post_id'] ?? $result['media_id'] ?? null;

        return [
            'success' => true,
            'external_id' => $externalId,
            'method' => 'direct_api',
        ];
    }

    public function supportsPlatform(string $platform): bool
    {
        return in_array($platform, ['facebook', 'instagram'], true);
    }

    public function isConfiguredForBrand(Brand $brand): bool
    {
        foreach (['facebook', 'instagram'] as $platform) {
            $credential = $brand->getPlatformCredential($platform);
            if ($credential && !$credential->isExpired()) {
                return true;
            }
        }

        return false;
    }
}
