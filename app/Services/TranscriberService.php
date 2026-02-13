<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscriberService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.transcriber.url', 'http://transcriber:3340');
        $this->timeout = config('services.transcriber.timeout', 300);
    }

    /**
     * Transcribe a video/audio file and return word-level timestamps.
     *
     * @param string $filePath Path in storage
     * @param string|null $language Source language (null for auto-detect)
     * @return array Transcription result with segments and language info
     */
    public function transcribe(string $filePath, ?string $language = null): array
    {
        $fullPath = Storage::path($filePath);

        if (!file_exists($fullPath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $params = ['word_timestamps' => 'true'];
        if ($language) {
            $params['language'] = $language;
        }

        Log::info('[TranscriberService] Starting transcription', [
            'file' => $filePath,
            'language' => $language,
        ]);

        $response = Http::timeout($this->timeout)
            ->attach('file', fopen($fullPath, 'r'), basename($filePath))
            ->post("{$this->baseUrl}/transcribe?" . http_build_query($params));

        if ($response->failed()) {
            $error = $response->json('error') ?? $response->body();
            Log::error('[TranscriberService] Transcription failed', ['error' => $error]);
            throw new Exception("Transcription failed: {$error}");
        }

        $result = $response->json();

        Log::info('[TranscriberService] Transcription completed', [
            'language' => $result['language'] ?? 'unknown',
            'segments' => count($result['segments'] ?? []),
            'duration' => $result['duration'] ?? 0,
        ]);

        return $result;
    }

    /**
     * Detect the language of an audio/video file.
     */
    public function detectLanguage(string $filePath): array
    {
        $fullPath = Storage::path($filePath);

        $response = Http::timeout(60)
            ->attach('file', fopen($fullPath, 'r'), basename($filePath))
            ->post("{$this->baseUrl}/detect-language");

        if ($response->failed()) {
            throw new Exception("Language detection failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Check if the transcriber service is healthy.
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
}
