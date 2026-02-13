<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoEditorService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.video_editor.url', 'http://video-editor:3341');
        $this->timeout = config('services.video_editor.timeout', 600);
    }

    /**
     * Add captions to a video file.
     *
     * @param string $videoPath Path in storage
     * @param array $captions Caption data (style, segments, settings)
     * @param string $outputPath Where to store the result in storage
     * @return string The output path in storage
     */
    public function addCaptions(string $videoPath, array $captions, string $outputPath): string
    {
        $fullPath = Storage::path($videoPath);

        if (!file_exists($fullPath)) {
            throw new Exception("Video file not found: {$videoPath}");
        }

        Log::info('[VideoEditorService] Adding captions', [
            'video' => $videoPath,
            'style' => $captions['style'] ?? 'clean',
            'segments' => count($captions['segments'] ?? []),
        ]);

        $response = Http::timeout($this->timeout)
            ->attach('file', fopen($fullPath, 'r'), basename($videoPath))
            ->post("{$this->baseUrl}/add-captions", [
                'captions' => json_encode($captions),
            ]);

        if ($response->failed()) {
            $error = $response->json('error') ?? 'Caption rendering failed';
            Log::error('[VideoEditorService] Caption rendering failed', ['error' => $error]);
            throw new Exception("Caption rendering failed: {$error}");
        }

        Storage::put($outputPath, $response->body());

        Log::info('[VideoEditorService] Captions added successfully', ['output' => $outputPath]);

        return $outputPath;
    }

    /**
     * Remove silence from a video based on speech segments.
     *
     * @param string $videoPath Path in storage
     * @param array $segments Array of {start, end} objects to keep
     * @param string $outputPath Where to store the result
     * @param float $padding Seconds of padding around each segment
     * @return string The output path in storage
     */
    public function removeSilence(string $videoPath, array $segments, string $outputPath, float $padding = 0.1): string
    {
        $fullPath = Storage::path($videoPath);

        if (!file_exists($fullPath)) {
            throw new Exception("Video file not found: {$videoPath}");
        }

        Log::info('[VideoEditorService] Removing silence', [
            'video' => $videoPath,
            'segments_to_keep' => count($segments),
        ]);

        $response = Http::timeout($this->timeout)
            ->attach('file', fopen($fullPath, 'r'), basename($videoPath))
            ->post("{$this->baseUrl}/remove-silence", [
                'segments' => json_encode($segments),
                'padding' => $padding,
            ]);

        if ($response->failed()) {
            $error = $response->json('error') ?? 'Silence removal failed';
            throw new Exception("Silence removal failed: {$error}");
        }

        Storage::put($outputPath, $response->body());

        return $outputPath;
    }

    /**
     * Get video metadata (duration, dimensions, codecs, etc.).
     */
    public function probe(string $videoPath): array
    {
        $fullPath = Storage::path($videoPath);

        $response = Http::timeout(30)
            ->attach('file', fopen($fullPath, 'r'), basename($videoPath))
            ->post("{$this->baseUrl}/probe");

        if ($response->failed()) {
            throw new Exception("Video probe failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get available caption styles.
     */
    public function getCaptionStyles(): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/caption-styles");

        if ($response->failed()) {
            throw new Exception("Failed to fetch caption styles");
        }

        return $response->json('styles', []);
    }

    /**
     * Extract audio from a video file.
     */
    public function extractAudio(string $videoPath, string $outputPath, string $format = 'wav'): string
    {
        $fullPath = Storage::path($videoPath);

        $response = Http::timeout($this->timeout)
            ->attach('file', fopen($fullPath, 'r'), basename($videoPath))
            ->post("{$this->baseUrl}/extract-audio?format={$format}");

        if ($response->failed()) {
            throw new Exception("Audio extraction failed: " . $response->body());
        }

        Storage::put($outputPath, $response->body());

        return $outputPath;
    }

    /**
     * Check if the video editor service is healthy.
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
