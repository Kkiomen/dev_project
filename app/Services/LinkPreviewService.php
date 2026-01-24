<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LinkPreviewService
{
    protected int $cacheMinutes = 60;
    protected int $timeout = 10;

    public function fetch(string $url): ?array
    {
        $cacheKey = 'link_preview:' . md5($url);

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($url) {
            return $this->fetchFromUrl($url);
        });
    }

    protected function fetchFromUrl(string $url): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; LinkPreviewBot/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            return $this->parseOpenGraphTags($html, $url);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch link preview', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function parseOpenGraphTags(string $html, string $url): array
    {
        $data = [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image' => null,
            'site_name' => null,
            'type' => null,
        ];

        // Parse OG tags
        $ogTags = [
            'og:title' => 'title',
            'og:description' => 'description',
            'og:image' => 'image',
            'og:site_name' => 'site_name',
            'og:type' => 'type',
            'og:url' => 'url',
        ];

        foreach ($ogTags as $property => $key) {
            $pattern = '/<meta[^>]*property=["\']' . preg_quote($property, '/') . '["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i';
            $patternAlt = '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*property=["\']' . preg_quote($property, '/') . '["\'][^>]*>/i';

            if (preg_match($pattern, $html, $matches) || preg_match($patternAlt, $html, $matches)) {
                $data[$key] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            }
        }

        // Fallback to standard meta tags
        if (empty($data['title'])) {
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
                $data['title'] = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            }
        }

        if (empty($data['description'])) {
            $pattern = '/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i';
            $patternAlt = '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']description["\'][^>]*>/i';

            if (preg_match($pattern, $html, $matches) || preg_match($patternAlt, $html, $matches)) {
                $data['description'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            }
        }

        // Make image URL absolute if relative
        if (!empty($data['image']) && !preg_match('/^https?:\/\//i', $data['image'])) {
            $parsed = parse_url($url);
            $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
            $data['image'] = $baseUrl . '/' . ltrim($data['image'], '/');
        }

        return $data;
    }

    public function clearCache(string $url): void
    {
        $cacheKey = 'link_preview:' . md5($url);
        Cache::forget($cacheKey);
    }
}
