<?php

namespace App\Services;

use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashService
{
    use LogsApiUsage;

    protected string $accessKey;

    protected string $baseUrl = 'https://api.unsplash.com';

    protected ?Brand $currentBrand = null;

    public function __construct()
    {
        $this->accessKey = config('services.unsplash.access_key', '');
    }

    /**
     * Set the current brand context for logging.
     */
    public function forBrand(?Brand $brand): self
    {
        $this->currentBrand = $brand;

        return $this;
    }

    /**
     * Check if Unsplash API is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessKey);
    }

    /**
     * Search for photos on Unsplash.
     * Interface compatible with PexelsService::searchPhotos()
     */
    public function searchPhotos(
        string $query,
        int $perPage = 5,
        ?string $orientation = null,
        ?string $size = null
    ): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Unsplash API key is not configured',
                'photos' => [],
            ];
        }

        $startTime = microtime(true);
        $endpoint = '/search/photos';

        $params = [
            'query' => $query,
            'per_page' => min($perPage, 30), // Unsplash max is 30
        ];

        if ($orientation) {
            // Map common orientation values to Unsplash API format
            // Unsplash accepts: landscape, portrait, squarish
            $orientationMap = [
                'square' => 'squarish',
                'squarish' => 'squarish',
                'landscape' => 'landscape',
                'horizontal' => 'landscape',
                'portrait' => 'portrait',
                'vertical' => 'portrait',
            ];
            $mappedOrientation = $orientationMap[strtolower($orientation)] ?? null;

            if ($mappedOrientation) {
                $params['orientation'] = $mappedOrientation;
                Log::channel('single')->debug('UnsplashService: Mapped orientation', [
                    'original' => $orientation,
                    'mapped' => $mappedOrientation,
                ]);
            } else {
                Log::channel('single')->warning('UnsplashService: Unknown orientation, skipping', [
                    'orientation' => $orientation,
                ]);
            }
        }

        // Start API usage logging
        $log = $this->logExternalStart(
            $this->currentBrand,
            'unsplash_search',
            ApiProvider::UNSPLASH,
            $endpoint,
            $params
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => "Client-ID {$this->accessKey}",
                'Accept-Version' => 'v1',
            ])->get("{$this->baseUrl}/search/photos", $params);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $this->failLog($log, 'Unsplash API request failed: ' . $response->status(), $durationMs, $response->status());

                return [
                    'success' => false,
                    'error' => 'Unsplash API request failed: ' . $response->status(),
                    'photos' => [],
                ];
            }

            $data = $response->json();
            $result = [
                'success' => true,
                'total_results' => $data['total'] ?? 0,
                'photos' => collect($data['results'] ?? [])->map(function ($photo) {
                    return $this->formatPhoto($photo);
                })->toArray(),
            ];

            // Complete logging
            $this->completeExternalLog(
                $log,
                ['total_results' => $result['total_results'], 'photos_count' => count($result['photos'])],
                $response->status(),
                $durationMs
            );

            return $result;
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, 'Unsplash API error: ' . $e->getMessage(), $durationMs);

            return [
                'success' => false,
                'error' => 'Unsplash API error: ' . $e->getMessage(),
                'photos' => [],
            ];
        }
    }

    /**
     * Get random photos (similar to Pexels curated).
     */
    public function getCuratedPhotos(int $perPage = 5): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Unsplash API key is not configured',
                'photos' => [],
            ];
        }

        $startTime = microtime(true);
        $endpoint = '/photos/random';
        $params = ['count' => min($perPage, 30)];

        // Start API usage logging
        $log = $this->logExternalStart(
            $this->currentBrand,
            'unsplash_random',
            ApiProvider::UNSPLASH,
            $endpoint,
            $params
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => "Client-ID {$this->accessKey}",
                'Accept-Version' => 'v1',
            ])->get("{$this->baseUrl}/photos/random", $params);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                $this->failLog($log, 'Unsplash API request failed: ' . $response->status(), $durationMs, $response->status());

                return [
                    'success' => false,
                    'error' => 'Unsplash API request failed',
                    'photos' => [],
                ];
            }

            $data = $response->json();

            // Single photo returns object, multiple returns array
            if (!is_array($data) || !isset($data[0])) {
                $data = [$data];
            }

            $result = [
                'success' => true,
                'photos' => collect($data)->map(function ($photo) {
                    return $this->formatPhoto($photo);
                })->toArray(),
            ];

            // Complete logging
            $this->completeExternalLog(
                $log,
                ['photos_count' => count($result['photos'])],
                $response->status(),
                $durationMs
            );

            return $result;
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, 'Unsplash API error: ' . $e->getMessage(), $durationMs);

            return [
                'success' => false,
                'error' => 'Unsplash API error: ' . $e->getMessage(),
                'photos' => [],
            ];
        }
    }

    /**
     * Format a photo response to match Pexels format for compatibility.
     */
    protected function formatPhoto(array $photo): array
    {
        $urls = $photo['urls'] ?? [];

        return [
            'id' => $photo['id'],
            'width' => $photo['width'] ?? 1920,
            'height' => $photo['height'] ?? 1080,
            'url' => $photo['links']['html'] ?? '',
            'photographer' => $photo['user']['name'] ?? 'Unknown',
            'photographer_url' => $photo['user']['links']['html'] ?? '',
            'avg_color' => $photo['color'] ?? '#cccccc',
            'src' => [
                'original' => $urls['raw'] ?? $urls['full'] ?? null,
                'large2x' => $urls['full'] ?? null,
                'large' => $urls['regular'] ?? null,
                'medium' => $urls['regular'] ?? null,
                'small' => $urls['small'] ?? null,
                'portrait' => $this->buildSizedUrl($urls['raw'] ?? '', 800, 1200),
                'landscape' => $this->buildSizedUrl($urls['raw'] ?? '', 1200, 800),
                'tiny' => $urls['thumb'] ?? null,
            ],
            'alt' => $photo['alt_description'] ?? $photo['description'] ?? '',
            'attribution' => "Photo by {$photo['user']['name']} on Unsplash",
        ];
    }

    /**
     * Build Unsplash URL with specific dimensions.
     */
    protected function buildSizedUrl(string $rawUrl, int $width, int $height): string
    {
        if (empty($rawUrl)) {
            return '';
        }

        // Unsplash raw URLs support dynamic sizing
        return $rawUrl . "&w={$width}&h={$height}&fit=crop";
    }

    /**
     * Get best image URL for given dimensions.
     * Interface compatible with PexelsService::getBestImageUrl()
     */
    public function getBestImageUrl(array $photo, int $targetWidth, int $targetHeight): string
    {
        $src = $photo['src'] ?? [];

        // Calculate aspect ratio to determine best fit
        $isLandscape = $targetWidth > $targetHeight;
        $isPortrait = $targetHeight > $targetWidth;
        $isSquare = abs($targetWidth - $targetHeight) < 100;

        // Choose best size based on target dimensions
        if ($isSquare && $targetWidth <= 800) {
            return $src['medium'] ?? $src['large'] ?? $src['original'];
        }

        if ($isPortrait) {
            return $src['portrait'] ?? $src['large'] ?? $src['original'];
        }

        if ($isLandscape) {
            return $src['landscape'] ?? $src['large'] ?? $src['original'];
        }

        // Default to large for most uses
        if ($targetWidth <= 1200) {
            return $src['large'] ?? $src['large2x'] ?? $src['original'];
        }

        return $src['large2x'] ?? $src['original'];
    }
}
