<?php

namespace App\Services\StockPhoto;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashClient
{
    protected string $baseUrl = 'https://api.unsplash.com';

    protected ?string $accessKey;

    public function __construct()
    {
        $this->accessKey = config('services.unsplash.access_key');
    }

    /**
     * Check if the client is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessKey);
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
                'Authorization' => "Client-ID {$this->accessKey}",
                'Accept-Version' => 'v1',
            ])->get("{$this->baseUrl}/search/photos", [
                'query' => $query,
                'per_page' => $perPage,
                'page' => $page,
                'orientation' => 'landscape',
            ]);

            if (!$response->successful()) {
                Log::warning('Unsplash API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            return array_map(function ($photo) {
                return $this->transformPhoto($photo);
            }, $data['results'] ?? []);
        } catch (\Exception $e) {
            Log::error('Unsplash API exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get a random photo.
     */
    public function random(array $keywords = [], int $count = 1): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $params = ['count' => $count];
            if (!empty($keywords)) {
                $params['query'] = implode(' ', $keywords);
            }

            $response = Http::withHeaders([
                'Authorization' => "Client-ID {$this->accessKey}",
                'Accept-Version' => 'v1',
            ])->get("{$this->baseUrl}/photos/random", $params);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            // Single photo returns object, multiple returns array
            if ($count === 1 && !isset($data[0])) {
                $data = [$data];
            }

            return array_map(function ($photo) {
                return $this->transformPhoto($photo);
            }, $data);
        } catch (\Exception $e) {
            Log::error('Unsplash random photo exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Transform Unsplash photo response to standard format.
     */
    protected function transformPhoto(array $photo): array
    {
        return [
            'id' => $photo['id'],
            'source' => 'unsplash',
            'url' => $photo['urls']['regular'] ?? $photo['urls']['small'],
            'thumbnail' => $photo['urls']['thumb'] ?? $photo['urls']['small'],
            'full' => $photo['urls']['full'] ?? $photo['urls']['regular'],
            'download_url' => $photo['links']['download'] ?? $photo['urls']['full'],
            'width' => $photo['width'],
            'height' => $photo['height'],
            'color' => $photo['color'] ?? '#cccccc',
            'description' => $photo['description'] ?? $photo['alt_description'] ?? '',
            'author' => [
                'name' => $photo['user']['name'] ?? 'Unknown',
                'username' => $photo['user']['username'] ?? '',
                'url' => $photo['user']['links']['html'] ?? '',
            ],
            'attribution' => "Photo by {$photo['user']['name']} on Unsplash",
            'attribution_url' => $photo['links']['html'] ?? '',
        ];
    }
}
