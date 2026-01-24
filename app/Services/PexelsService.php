<?php

namespace App\Services;

use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Http;

class PexelsService
{
    use LogsApiUsage;

    protected string $apiKey;

    protected string $baseUrl;

    protected ?Brand $currentBrand = null;

    public function __construct()
    {
        $this->apiKey = config('services.pexels.api_key', '');
        $this->baseUrl = config('services.pexels.base_url', 'https://api.pexels.com/v1');
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
     * Check if Pexels API is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Search for photos on Pexels.
     *
     * @param  string  $query  Search query
     * @param  int  $perPage  Number of results (1-80)
     * @param  string  $orientation  landscape, portrait, or square
     * @param  string  $size  large, medium, or small
     * @return array Array of photo results
     */
    public function searchPhotos(
        string $query,
        int $perPage = 5,
        ?string $orientation = null,
        ?string $size = null
    ): array {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Pexels API key is not configured',
                'photos' => [],
            ];
        }

        $startTime = microtime(true);
        $endpoint = '/search';

        $params = [
            'query' => $query,
            'per_page' => min($perPage, 80),
        ];

        if ($orientation) {
            $params['orientation'] = $orientation;
        }

        if ($size) {
            $params['size'] = $size;
        }

        // Start API usage logging
        $log = $this->logExternalStart(
            $this->currentBrand,
            'pexels_search',
            ApiProvider::PEXELS,
            $endpoint,
            $params
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get("{$this->baseUrl}/search", $params);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (! $response->successful()) {
                $this->failLog($log, 'Pexels API request failed: ' . $response->status(), $durationMs, $response->status());

                return [
                    'success' => false,
                    'error' => 'Pexels API request failed: '.$response->status(),
                    'photos' => [],
                ];
            }

            $data = $response->json();
            $result = [
                'success' => true,
                'total_results' => $data['total_results'] ?? 0,
                'photos' => collect($data['photos'] ?? [])->map(function ($photo) {
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
            $this->failLog($log, 'Pexels API error: ' . $e->getMessage(), $durationMs);

            return [
                'success' => false,
                'error' => 'Pexels API error: '.$e->getMessage(),
                'photos' => [],
            ];
        }
    }

    /**
     * Get curated photos (popular/trending).
     */
    public function getCuratedPhotos(int $perPage = 5): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Pexels API key is not configured',
                'photos' => [],
            ];
        }

        $startTime = microtime(true);
        $endpoint = '/curated';
        $params = ['per_page' => min($perPage, 80)];

        // Start API usage logging
        $log = $this->logExternalStart(
            $this->currentBrand,
            'pexels_curated',
            ApiProvider::PEXELS,
            $endpoint,
            $params
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get("{$this->baseUrl}/curated", $params);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if (! $response->successful()) {
                $this->failLog($log, 'Pexels API request failed: ' . $response->status(), $durationMs, $response->status());

                return [
                    'success' => false,
                    'error' => 'Pexels API request failed',
                    'photos' => [],
                ];
            }

            $data = $response->json();
            $result = [
                'success' => true,
                'photos' => collect($data['photos'] ?? [])->map(function ($photo) {
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
            $this->failLog($log, 'Pexels API error: ' . $e->getMessage(), $durationMs);

            return [
                'success' => false,
                'error' => 'Pexels API error: '.$e->getMessage(),
                'photos' => [],
            ];
        }
    }

    /**
     * Format a photo response for easier use.
     */
    protected function formatPhoto(array $photo): array
    {
        return [
            'id' => $photo['id'],
            'width' => $photo['width'],
            'height' => $photo['height'],
            'url' => $photo['url'],
            'photographer' => $photo['photographer'],
            'photographer_url' => $photo['photographer_url'],
            'avg_color' => $photo['avg_color'] ?? null,
            'src' => [
                'original' => $photo['src']['original'] ?? null,
                'large2x' => $photo['src']['large2x'] ?? null,
                'large' => $photo['src']['large'] ?? null,
                'medium' => $photo['src']['medium'] ?? null,
                'small' => $photo['src']['small'] ?? null,
                'portrait' => $photo['src']['portrait'] ?? null,
                'landscape' => $photo['src']['landscape'] ?? null,
                'tiny' => $photo['src']['tiny'] ?? null,
            ],
            'alt' => $photo['alt'] ?? '',
        ];
    }

    /**
     * Get best image URL for given dimensions.
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
