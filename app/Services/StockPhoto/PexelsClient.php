<?php

namespace App\Services\StockPhoto;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PexelsClient
{
    protected string $baseUrl = 'https://api.pexels.com/v1';

    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.pexels.api_key');
    }

    /**
     * Check if the client is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Search for photos.
     */
    public function search(array $keywords, int $perPage = 9, int $page = 1): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $query = implode(' ', $keywords);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get("{$this->baseUrl}/search", [
                'query' => $query,
                'per_page' => $perPage,
                'page' => $page,
                'orientation' => 'landscape',
            ]);

            if (!$response->successful()) {
                Log::warning('Pexels API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            return array_map(function ($photo) {
                return $this->transformPhoto($photo);
            }, $data['photos'] ?? []);
        } catch (\Exception $e) {
            Log::error('Pexels API exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get curated photos.
     */
    public function curated(int $perPage = 9, int $page = 1): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get("{$this->baseUrl}/curated", [
                'per_page' => $perPage,
                'page' => $page,
            ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            return array_map(function ($photo) {
                return $this->transformPhoto($photo);
            }, $data['photos'] ?? []);
        } catch (\Exception $e) {
            Log::error('Pexels curated exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Transform Pexels photo response to standard format.
     */
    protected function transformPhoto(array $photo): array
    {
        return [
            'id' => (string) $photo['id'],
            'source' => 'pexels',
            'url' => $photo['src']['large'] ?? $photo['src']['medium'],
            'thumbnail' => $photo['src']['tiny'] ?? $photo['src']['small'],
            'full' => $photo['src']['original'] ?? $photo['src']['large2x'],
            'download_url' => $photo['src']['original'],
            'width' => $photo['width'],
            'height' => $photo['height'],
            'color' => $photo['avg_color'] ?? '#cccccc',
            'description' => $photo['alt'] ?? '',
            'author' => [
                'name' => $photo['photographer'] ?? 'Unknown',
                'username' => '',
                'url' => $photo['photographer_url'] ?? '',
            ],
            'attribution' => "Photo by {$photo['photographer']} on Pexels",
            'attribution_url' => $photo['url'] ?? '',
        ];
    }
}
