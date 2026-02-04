<?php

namespace App\Services\Webhook;

use App\Models\SocialPost;
use App\Services\PostAiGenerationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{
    public function __construct(
        protected PostAiGenerationService $aiGenerationService
    ) {}

    public function generateText(SocialPost $post, ?string $promptOverride = null): array
    {
        $brand = $post->brand;

        if ($brand && $brand->hasWebhook('text_generation')) {
            $payload = $this->buildPayload($post, [
                'prompt' => $promptOverride ?? $brand->getWebhookPrompt('text_generation'),
            ]);

            return $this->sendWebhook($brand->getWebhookUrl('text_generation'), $payload);
        }

        // Fallback to AI generation
        return $this->fallbackTextGeneration($post);
    }

    public function generateImagePrompt(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('image_generation')) {
            return ['success' => false, 'error' => 'No image generation webhook configured'];
        }

        $payload = $this->buildPayload($post, [
            'prompt' => $brand->getWebhookPrompt('image_generation'),
        ]);

        return $this->sendWebhook($brand->getWebhookUrl('image_generation'), $payload);
    }

    public function publish(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('publish')) {
            return ['success' => false, 'error' => 'No publish webhook configured'];
        }

        $payload = $this->buildPayload($post);

        return $this->sendWebhook($brand->getWebhookUrl('publish'), $payload);
    }

    public function onApprove(SocialPost $post): void
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('on_approve')) {
            return;
        }

        $payload = $this->buildPayload($post);

        try {
            Http::timeout(10)->post($brand->getWebhookUrl('on_approve'), $payload);
        } catch (\Throwable $e) {
            Log::warning('on_approve webhook failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function buildPayload(SocialPost $post, array $extra = []): array
    {
        $brand = $post->brand;

        $payload = [
            'post_id' => $post->public_id,
            'brand_id' => $brand?->public_id,
            'brand_name' => $brand?->name,
            'title' => $post->title,
            'main_caption' => $post->main_caption,
            'image_prompt' => $post->image_prompt,
            'status' => $post->status->value,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'brand_context' => $brand?->buildAiContext() ?? [],
            'callback_url' => url('/api/v1/webhooks/automation-callback'),
        ];

        return array_merge($payload, $extra);
    }

    protected function sendWebhook(string $url, array $payload): array
    {
        try {
            $response = Http::timeout(30)->retry(2, 100)->post($url, $payload);

            // HTTP 202 = Accepted for async processing
            // The n8n trigger responds immediately, result comes via callback
            if ($response->status() === 202) {
                return [
                    'success' => true,
                    'async' => true,
                    'message' => 'Request accepted for processing',
                ];
            }

            if ($response->successful()) {
                $data = $response->json();
                return array_merge(['success' => true], $data ?? []);
            }

            return [
                'success' => false,
                'error' => 'Webhook returned status ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Webhook dispatch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function fallbackTextGeneration(SocialPost $post): array
    {
        $brand = $post->brand;

        try {
            $config = [
                'topic' => $post->title ?? 'Social media post',
                'tone' => $brand?->getTone() ?? 'professional',
                'length' => 'medium',
                'platforms' => ['facebook'],
            ];

            $result = $this->aiGenerationService->generate($config, $brand);

            $caption = $result['facebook']['caption'] ?? $result['caption'] ?? null;
            $title = $result['facebook']['title'] ?? $result['title'] ?? null;

            if ($caption) {
                return [
                    'success' => true,
                    'caption' => $caption,
                    'title' => $title,
                ];
            }

            return ['success' => false, 'error' => 'AI generation returned no content'];
        } catch (\Throwable $e) {
            Log::error('AI fallback text generation failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
