<?php

namespace App\Services\AI;

use App\Enums\AiProvider;
use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SocialPost;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectImageGeneratorService
{
    use LogsApiUsage;

    private const API_BASE = 'https://api.wavespeed.ai/api/v3';
    private const MODEL_ENDPOINT = '/google/nano-banana/text-to-image';
    private const POLL_INTERVAL_SECONDS = 2;
    private const MAX_POLL_ATTEMPTS = 30;

    public function generate(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand) {
            return ['success' => false, 'error' => 'No brand associated with post'];
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::WaveSpeed);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No WaveSpeed API key configured for this brand'];
        }

        $imagePrompt = $post->image_prompt;

        if (empty($imagePrompt)) {
            return ['success' => false, 'error' => 'No image prompt provided for this post'];
        }

        $fullPrompt = $this->buildPrompt($brand, $imagePrompt);

        $startTime = microtime(true);
        $log = $this->logExternalStart(
            $brand,
            'direct_image_generation',
            ApiProvider::WAVESPEED,
            self::MODEL_ENDPOINT,
            ['post_id' => $post->public_id, 'prompt' => $fullPrompt]
        );

        try {
            $jobId = $this->createJob($apiKey, $fullPrompt);
            $imageUrl = $this->pollForResult($apiKey, $jobId);
            $imageData = $this->downloadImage($imageUrl);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->completeExternalLog($log, ['job_id' => $jobId, 'image_url' => $imageUrl], 200, $durationMs);

            $extension = $this->guessExtension($imageUrl);

            return [
                'success' => true,
                'image_data' => $imageData,
                'mime_type' => $this->extensionToMime($extension),
                'filename' => 'wavespeed-' . substr(md5($jobId), 0, 12) . '.' . $extension,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('Direct image generation failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function buildPrompt(Brand $brand, string $imagePrompt): string
    {
        $settings = $brand->automation_settings ?? [];
        $systemPrompt = $settings['image_system_prompt'] ?? '';

        if (empty($systemPrompt)) {
            return "High quality, professional social media image. " . $imagePrompt;
        }

        return $this->replaceVariables($brand, $systemPrompt, $imagePrompt);
    }

    protected function replaceVariables(Brand $brand, string $prompt, string $imagePrompt): string
    {
        $targetAudience = $brand->target_audience ?? [];
        $voice = $brand->voice ?? [];

        $variables = [
            'brand_name' => $brand->name ?? '',
            'brand_description' => $brand->description ?? '',
            'industry' => $brand->industry ?? '',
            'tone' => $voice['tone'] ?? '',
            'language' => $voice['language'] ?? 'pl',
            'emoji_usage' => $voice['emoji_usage'] ?? 'sometimes',
            'personality' => implode(', ', $voice['personality'] ?? []),
            'target_age_range' => $targetAudience['age_range'] ?? '',
            'target_gender' => $targetAudience['gender'] ?? 'all',
            'interests' => implode(', ', $targetAudience['interests'] ?? []),
            'pain_points' => implode(', ', $targetAudience['pain_points'] ?? []),
            'content_pillars' => implode(', ', array_column($brand->content_pillars ?? [], 'name')),
            'image_prompt' => $imagePrompt,
        ];

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
        }

        return $prompt;
    }

    protected function createJob(string $apiKey, string $prompt): string
    {
        Log::info('WaveSpeed image generation request', [
            'prompt' => $prompt,
        ]);

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post(self::API_BASE . self::MODEL_ENDPOINT, [
                'prompt' => $prompt,
                'enable_base64_output' => false,
                'enable_sync_mode' => false,
                'output_format' => 'jpeg',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('WaveSpeed API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        $jobId = $data['data']['id'] ?? null;

        if (!$jobId) {
            throw new \RuntimeException('WaveSpeed API did not return a job ID');
        }

        return $jobId;
    }

    protected function pollForResult(string $apiKey, string $jobId): string
    {
        $endpoint = self::API_BASE . "/predictions/{$jobId}/result";

        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            sleep(self::POLL_INTERVAL_SECONDS);

            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->get($endpoint);

            if (!$response->successful()) {
                continue;
            }

            $data = $response->json();
            $status = $data['data']['status'] ?? $data['status'] ?? null;

            if ($status === 'completed' || $status === 'succeeded') {
                $outputs = $data['data']['outputs'] ?? $data['data']['output'] ?? $data['outputs'] ?? [];
                $imageUrl = is_array($outputs) ? ($outputs[0] ?? null) : $outputs;

                if ($imageUrl && is_string($imageUrl)) {
                    return $imageUrl;
                }

                Log::error('WaveSpeed job completed but no image URL found', [
                    'job_id' => $jobId,
                    'response' => $data,
                ]);

                throw new \RuntimeException('WaveSpeed job completed but no image URL found in response');
            }

            if ($status === 'failed' || $status === 'error') {
                $errorMsg = $data['data']['error'] ?? $data['error'] ?? 'Unknown error';
                throw new \RuntimeException('WaveSpeed job failed: ' . $errorMsg);
            }
        }

        throw new \RuntimeException('WaveSpeed image generation timed out after ' . (self::MAX_POLL_ATTEMPTS * self::POLL_INTERVAL_SECONDS) . 's');
    }

    protected function downloadImage(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download generated image: HTTP ' . $response->status());
        }

        return $response->body();
    }

    protected function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'png',
            'webp' => 'webp',
            'gif' => 'gif',
            default => 'jpg',
        };
    }

    protected function extensionToMime(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
