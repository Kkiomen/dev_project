<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class CodeImageService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.rayso.url', 'http://rayso:3333');
        $this->timeout = config('services.rayso.timeout', 30);
    }

    /**
     * Generate code image and return as base64.
     *
     * @param string $code The code to render
     * @param array $options Optional settings: title, theme, background, darkMode, padding, language
     * @return string Base64 encoded PNG image
     * @throws Exception
     */
    public function generate(string $code, array $options = []): string
    {
        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/generate", array_merge([
                'code' => $code,
            ], $this->filterOptions($options)));

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception('Failed to generate code image: ' . ($error['message'] ?? $response->body()));
        }

        return base64_encode($response->body());
    }

    /**
     * Generate code image and save to storage.
     *
     * @param string $code The code to render
     * @param string $path Storage path for the image
     * @param array $options Optional settings: title, theme, background, darkMode, padding, language
     * @return bool
     * @throws Exception
     */
    public function generateAndStore(string $code, string $path, array $options = []): bool
    {
        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/generate", array_merge([
                'code' => $code,
            ], $this->filterOptions($options)));

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception('Failed to generate code image: ' . ($error['message'] ?? $response->body()));
        }

        return Storage::put($path, $response->body());
    }

    /**
     * Generate code image and return raw binary.
     *
     * @param string $code The code to render
     * @param array $options Optional settings
     * @return string Raw PNG binary
     * @throws Exception
     */
    public function generateRaw(string $code, array $options = []): string
    {
        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/generate", array_merge([
                'code' => $code,
            ], $this->filterOptions($options)));

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception('Failed to generate code image: ' . ($error['message'] ?? $response->body()));
        }

        return $response->body();
    }

    /**
     * Check if rayso service is healthy.
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful() && $response->json('status') === 'ok';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get available themes.
     *
     * @return array
     */
    public static function availableThemes(): array
    {
        return ['breeze', 'candy', 'crimson', 'falcon', 'meadow', 'midnight', 'raindrop', 'sunset'];
    }

    /**
     * Get available padding values.
     *
     * @return array
     */
    public static function availablePaddings(): array
    {
        return [16, 32, 64, 128];
    }

    /**
     * Filter and validate options.
     *
     * @param array $options
     * @return array
     */
    protected function filterOptions(array $options): array
    {
        $allowed = ['title', 'theme', 'background', 'darkMode', 'padding', 'language'];
        return array_filter(
            array_intersect_key($options, array_flip($allowed)),
            fn($value) => $value !== null
        );
    }
}
