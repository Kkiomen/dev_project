<?php

namespace App\Services\AI;

use App\Enums\AiProvider;
use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmBrandKit;
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

        if (!empty($systemPrompt)) {
            return $this->replaceVariables($brand, $systemPrompt, $imagePrompt);
        }

        $brandKit = SmBrandKit::where('brand_id', $brand->id)->first();
        $visualSuffix = $this->buildVisualSuffix($brandKit, $brand->industry);

        return $imagePrompt . ' ' . $visualSuffix;
    }

    /**
     * Build a brand-aware visual suffix from SmBrandKit and industry.
     */
    public function buildVisualSuffix(?SmBrandKit $brandKit, ?string $industry): string
    {
        $parts = [];

        if ($brandKit?->style_preset) {
            $parts[] = match ($brandKit->style_preset) {
                'modern' => '4k RAW photograph, clean contemporary aesthetic, sharp focus.',
                'classic' => '4k RAW photograph, timeless warm tones, film grain texture.',
                'bold' => '4k RAW photograph, high contrast, dramatic directional light.',
                'minimal' => '4k RAW photograph, extreme negative space, muted desaturated palette.',
                'playful' => '4k RAW photograph, warm natural colors, soft organic feel.',
                default => '4k RAW photograph, editorial aesthetic.',
            };
        } else {
            $parts[] = '4k RAW photograph, editorial aesthetic, natural lighting.';
        }

        $colors = $brandKit?->colors ?? [];
        $colorNames = [];
        if (!empty($colors['primary'])) {
            $colorNames[] = $this->hexToColorName($colors['primary']);
        }
        if (!empty($colors['secondary'])) {
            $colorNames[] = $this->hexToColorName($colors['secondary']);
        }
        if (!empty($colors['accent'])) {
            $colorNames[] = $this->hexToColorName($colors['accent']);
        }
        if (!empty($colorNames)) {
            $parts[] = 'Color tones: ' . implode(', ', $colorNames) . '.';
        }

        if ($industry) {
            $industryAesthetic = $this->getIndustryAesthetic($industry);
            if ($industryAesthetic) {
                $parts[] = $industryAesthetic;
            }
        }

        $parts[] = 'Bokeh, shallow depth of field. No text, no words, no letters, no watermarks.';

        return implode(' ', $parts);
    }

    /**
     * Convert a hex color to a descriptive color name using HSL hue mapping.
     */
    public function hexToColorName(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return 'neutral';
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0) {
            $s = 0;
            $h = 0;
        } else {
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            $h = match ($max) {
                $r => (($g - $b) / $d + ($g < $b ? 6 : 0)) * 60,
                $g => (($b - $r) / $d + 2) * 60,
                default => (($r - $g) / $d + 4) * 60,
            };
        }

        // Low saturation → grayscale
        if ($s < 0.1) {
            return match (true) {
                $l < 0.15 => 'near-black',
                $l < 0.35 => 'dark charcoal',
                $l < 0.65 => 'medium gray',
                $l < 0.85 => 'light gray',
                default => 'off-white',
            };
        }

        // Lightness modifier
        $lightnessPrefix = match (true) {
            $l < 0.25 => 'deep ',
            $l < 0.4 => 'dark ',
            $l > 0.8 => 'pale ',
            $l > 0.65 => 'soft ',
            default => '',
        };

        // Hue name
        $hueName = match (true) {
            $h < 15 => 'red',
            $h < 35 => 'orange',
            $h < 55 => 'golden yellow',
            $h < 75 => 'yellow',
            $h < 105 => 'lime green',
            $h < 150 => 'green',
            $h < 175 => 'teal',
            $h < 200 => 'cyan',
            $h < 230 => 'blue',
            $h < 260 => 'indigo',
            $h < 290 => 'violet',
            $h < 320 => 'magenta',
            $h < 345 => 'rose',
            default => 'red',
        };

        return $lightnessPrefix . $hueName;
    }

    /**
     * Get industry-specific aesthetic description for image suffix.
     */
    protected function getIndustryAesthetic(?string $industry): string
    {
        if (!$industry) {
            return '';
        }

        $industryLower = strtolower($industry);

        return match (true) {
            str_contains($industryLower, 'beauty') || str_contains($industryLower, 'cosmetic')
                => 'Luxurious textures, dewy surfaces, golden warmth.',
            str_contains($industryLower, 'food') || str_contains($industryLower, 'gastro') || str_contains($industryLower, 'restaurant')
                => 'Warm appetizing tones, rustic textures, inviting ambiance.',
            str_contains($industryLower, 'fitness') || str_contains($industryLower, 'sport')
                => 'Dynamic energy, powerful movement, dramatic contrast.',
            str_contains($industryLower, 'tech') || str_contains($industryLower, 'software')
                => 'Clean precision, cool tones, geometric minimalism.',
            str_contains($industryLower, 'fashion') || str_contains($industryLower, 'clothing')
                => 'Editorial sophistication, textural richness, high-contrast styling.',
            str_contains($industryLower, 'health') || str_contains($industryLower, 'medical') || str_contains($industryLower, 'wellness')
                => 'Clinical precision with warmth, calming airy environments, trust-building aesthetics.',
            str_contains($industryLower, 'real estate') || str_contains($industryLower, 'interior')
                => 'Architectural elegance, warm ambient lighting, aspirational spaces.',
            str_contains($industryLower, 'travel') || str_contains($industryLower, 'tourism')
                => 'Vibrant destination colors, golden hour warmth, expansive compositions.',
            str_contains($industryLower, 'finance') || str_contains($industryLower, 'banking')
                => 'Trustworthy stability, navy and gold accents, geometric confidence.',
            str_contains($industryLower, 'education') || str_contains($industryLower, 'coaching')
                => 'Warm knowledge-sharing environments, focused clarity, approachable professionalism.',
            default => '',
        };
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
