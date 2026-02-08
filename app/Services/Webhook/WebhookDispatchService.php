<?php

namespace App\Services\Webhook;

use App\Models\SocialPost;
use App\Services\AI\DirectImageGeneratorService;
use App\Services\AI\DirectTextGeneratorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{
    public function __construct(
        protected DirectTextGeneratorService $directTextGenerator,
        protected DirectImageGeneratorService $directImageGenerator,
    ) {}

    public function generateText(SocialPost $post, ?string $promptOverride = null): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('text_generation')) {
            return $this->directTextGenerator->generate($post, $promptOverride);
        }

        $payload = $this->buildPayload($post, [
            'prompt' => $promptOverride ?? $brand->getWebhookPrompt('text_generation'),
            'text_prompt' => $post->text_prompt,
            'system_prompt' => $this->getResolvedSystemPrompt($brand, 'text'),
        ]);

        return $this->sendWebhook($brand->getWebhookUrl('text_generation'), $payload);
    }

    public function generateImageDescription(SocialPost $post): array
    {
        return $this->directTextGenerator->generateImageDescription($post);
    }

    public function generateImagePrompt(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('image_generation')) {
            return $this->directImageGenerator->generate($post);
        }

        $payload = $this->buildPayload($post, [
            'prompt' => $brand->getWebhookPrompt('image_generation'),
            'system_prompt' => $this->getResolvedSystemPrompt($brand, 'image', $post),
        ]);

        return $this->sendWebhook($brand->getWebhookUrl('image_generation'), $payload);
    }

    public function publish(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('publish')) {
            // No webhook configured - this is not an error, just skip
            return ['success' => true, 'skipped' => true, 'message' => 'No publish webhook configured'];
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

    /**
     * Get resolved system prompt with variables replaced.
     */
    protected function getResolvedSystemPrompt($brand, string $type, ?SocialPost $post = null): string
    {
        $settings = $brand->automation_settings ?? [];
        $prompt = $settings[$type . '_system_prompt'] ?? '';

        if (empty($prompt)) {
            return '';
        }

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
            'image_prompt' => $post->image_prompt ?? '',
        ];

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
        }

        return $prompt;
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

}
