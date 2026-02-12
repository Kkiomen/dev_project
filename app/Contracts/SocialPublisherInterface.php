<?php

namespace App\Contracts;

use App\Models\Brand;
use App\Models\PlatformPost;

interface SocialPublisherInterface
{
    /**
     * Publish a platform post to social media.
     *
     * @return array{success: bool, external_id?: string, external_url?: string, method: string, error?: string}
     */
    public function publish(PlatformPost $platformPost): array;

    public function supportsPlatform(string $platform): bool;

    public function isConfiguredForBrand(Brand $brand): bool;
}
