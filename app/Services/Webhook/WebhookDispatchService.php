<?php

namespace App\Services\Webhook;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{

    public function generateText(SocialPost $post, ?string $promptOverride = null): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('text_generation')) {
            // No webhook configured - this is not an error, just skip
            return ['success' => true, 'skipped' => true, 'message' => 'No text generation webhook configured'];
        }

        $payload = $this->buildPayload($post, [
            'prompt' => $promptOverride ?? $brand->getWebhookPrompt('text_generation'),
        ]);

        return $this->sendWebhook($brand->getWebhookUrl('text_generation'), $payload);
    }

    public function generateImagePrompt(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand || !$brand->hasWebhook('image_generation')) {
            // No webhook configured - this is not an error, just skip
            return ['success' => true, 'skipped' => true, 'message' => 'No image generation webhook configured'];
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
