<?php

namespace App\Services\Publishing;

use App\Contracts\SocialPublisherInterface;
use App\Enums\PublishingProvider;
use App\Models\Brand;
use App\Models\PlatformPost;
use App\Services\Publishing\Adapters\DirectPublishingAdapter;
use App\Services\Publishing\Adapters\GetLatePublishingAdapter;
use App\Services\Publishing\Adapters\WebhookPublishingAdapter;

class PublisherResolver
{
    public function __construct(
        protected DirectPublishingAdapter $directAdapter,
        protected WebhookPublishingAdapter $webhookAdapter,
        protected GetLatePublishingAdapter $getLateAdapter,
    ) {}

    public function resolve(Brand $brand, PlatformPost $platformPost): SocialPublisherInterface
    {
        $platform = $platformPost->platform->value;

        // If brand has an explicit publishing provider set, use it
        if ($brand->publishing_provider !== null) {
            $adapter = $this->getAdapter($brand->publishing_provider);

            if ($adapter->supportsPlatform($platform) && $adapter->isConfiguredForBrand($brand)) {
                return $adapter;
            }

            // Explicit provider not available — fall through to legacy chain
        }

        // Legacy fallback chain: direct → webhook
        if ($this->directAdapter->supportsPlatform($platform) && $this->directAdapter->isConfiguredForBrand($brand)) {
            $credential = $brand->getPlatformCredential($platform);
            if ($credential && !$credential->isExpired()) {
                return $this->directAdapter;
            }
        }

        if ($this->webhookAdapter->isConfiguredForBrand($brand)) {
            return $this->webhookAdapter;
        }

        throw new \RuntimeException("No publishing method available for platform: {$platform}");
    }

    private function getAdapter(PublishingProvider $provider): SocialPublisherInterface
    {
        return match ($provider) {
            PublishingProvider::Direct => $this->directAdapter,
            PublishingProvider::Webhook => $this->webhookAdapter,
            PublishingProvider::GetLate => $this->getLateAdapter,
        };
    }
}
