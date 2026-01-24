<?php

namespace App\Services\StockPhoto;

use Illuminate\Support\Facades\Cache;

class StockPhotoService
{
    public function __construct(
        protected UnsplashClient $unsplash,
        protected PexelsClient $pexels
    ) {}

    /**
     * Search for photos across all providers.
     */
    public function search(array $keywords, int $perPage = 9): array
    {
        $cacheKey = 'stock:search:' . md5(json_encode($keywords) . $perPage);

        return Cache::remember($cacheKey, 86400, function () use ($keywords, $perPage) {
            $results = [];

            // Try Unsplash first
            if ($this->unsplash->isConfigured()) {
                $results = $this->unsplash->search($keywords, $perPage);
            }

            // If not enough results, try Pexels
            if (count($results) < $perPage && $this->pexels->isConfigured()) {
                $needed = $perPage - count($results);
                $pexelsResults = $this->pexels->search($keywords, $needed);
                $results = array_merge($results, $pexelsResults);
            }

            // Limit to requested amount
            return array_slice($results, 0, $perPage);
        });
    }

    /**
     * Search with multiple keyword sets for variety.
     */
    public function searchWithVariety(array $keywordSets, int $perPage = 9): array
    {
        $results = [];
        $perKeyword = max(1, (int) ceil($perPage / count($keywordSets)));

        foreach ($keywordSets as $keywords) {
            $keywordsArray = is_array($keywords) ? $keywords : [$keywords];
            $photos = $this->search($keywordsArray, $perKeyword);
            $results = array_merge($results, $photos);
        }

        // Shuffle for variety and limit
        shuffle($results);

        return array_slice($results, 0, $perPage);
    }

    /**
     * Get curated/featured photos.
     */
    public function featured(int $perPage = 9): array
    {
        $cacheKey = 'stock:featured:' . $perPage;

        return Cache::remember($cacheKey, 3600, function () use ($perPage) {
            $results = [];

            // Try Pexels curated first (better quality curated selection)
            if ($this->pexels->isConfigured()) {
                $results = $this->pexels->curated($perPage);
            }

            // Fallback to Unsplash random
            if (count($results) < $perPage && $this->unsplash->isConfigured()) {
                $needed = $perPage - count($results);
                $unsplashResults = $this->unsplash->random([], $needed);
                $results = array_merge($results, $unsplashResults);
            }

            return array_slice($results, 0, $perPage);
        });
    }

    /**
     * Clear cached results.
     */
    public function clearCache(): void
    {
        // This would need proper cache tagging in production
        Cache::flush();
    }

    /**
     * Check if any provider is configured.
     */
    public function isAvailable(): bool
    {
        return $this->unsplash->isConfigured() || $this->pexels->isConfigured();
    }

    /**
     * Get available providers.
     */
    public function getAvailableProviders(): array
    {
        $providers = [];

        if ($this->unsplash->isConfigured()) {
            $providers[] = 'unsplash';
        }

        if ($this->pexels->isConfigured()) {
            $providers[] = 'pexels';
        }

        return $providers;
    }
}
