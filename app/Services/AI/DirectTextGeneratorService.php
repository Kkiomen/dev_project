<?php

namespace App\Services\AI;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SocialPost;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class DirectTextGeneratorService
{
    use LogsApiUsage;

    /**
     * Generate text for a post via OpenAI when no webhook is configured.
     */
    public function generate(SocialPost $post, ?string $promptOverride = null): array
    {
        $brand = $post->brand;

        if (!$brand) {
            return ['success' => false, 'error' => 'No brand associated with post'];
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $userPrompt = $promptOverride ?? $post->text_prompt;

        if (empty($userPrompt)) {
            return ['success' => false, 'error' => 'No text prompt provided for this post'];
        }

        $systemPrompt = $this->resolveSystemPrompt($brand);
        $fullUserPrompt = $userPrompt . "\n\nRespond with JSON: {\"caption\": \"...\", \"title\": \"...\"}";

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'direct_text_generation', [
            'post_id' => $post->public_id,
            'user_prompt' => $userPrompt,
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $fullUserPrompt],
                ],
                'max_tokens' => 2048,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'caption' => $parsed['caption'] ?? '',
                'title' => $parsed['title'] ?? '',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('Direct text generation failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate an image description (image_prompt) from post content via OpenAI.
     */
    public function generateImageDescription(SocialPost $post): array
    {
        $brand = $post->brand;

        if (!$brand) {
            return ['success' => false, 'error' => 'No brand associated with post'];
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $caption = $post->main_caption ?: $post->text_prompt;

        if (empty($caption)) {
            return ['success' => false, 'error' => 'No caption or text prompt available for this post'];
        }

        $language = $this->getLanguageName($brand->getLanguage());

        $systemPrompt = "You are an expert visual content strategist for the brand \"{$brand->name}\".";
        if ($brand->description) {
            $systemPrompt .= "\nBrand description: {$brand->description}";
        }
        if ($brand->industry) {
            $systemPrompt .= "\nIndustry: {$brand->industry}";
        }
        $systemPrompt .= "\n\nBased on a social media post caption, create a short, vivid image description that an AI image generator can use to produce a matching visual for the post.";
        $systemPrompt .= "\nThe description should be specific, visual, and suitable for social media.";
        $systemPrompt .= "\nWrite the image description in {$language}.";
        $systemPrompt .= "\nRespond with valid JSON only.";

        $fullUserPrompt = "Post caption:\n\"{$caption}\"\n\nRespond with JSON: {\"image_prompt\": \"...\"}";

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'direct_image_description', [
            'post_id' => $post->public_id,
            'caption' => mb_substr($caption, 0, 200),
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $fullUserPrompt],
                ],
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = trim($response->choices[0]->message->content);

            // Strip markdown code blocks if present
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $content = $matches[1];
            }

            $decoded = json_decode($content, true);
            $imagePrompt = '';

            if (json_last_error() === JSON_ERROR_NONE) {
                $imagePrompt = $decoded['image_prompt'] ?? '';
            } else {
                // Fallback: use raw response as the image prompt
                $imagePrompt = $content;
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, ['image_prompt' => $imagePrompt], $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'image_prompt' => $imagePrompt,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('Direct image description generation failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve the system prompt: use brand's configured prompt or build a default.
     */
    protected function resolveSystemPrompt(Brand $brand): string
    {
        $settings = $brand->automation_settings ?? [];
        $prompt = $settings['text_system_prompt'] ?? '';

        if (!empty($prompt)) {
            return $this->replaceVariables($brand, $prompt);
        }

        return $this->buildDefaultSystemPrompt($brand);
    }

    /**
     * Replace {{variable}} placeholders in a prompt template.
     */
    protected function replaceVariables(Brand $brand, string $prompt): string
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
        ];

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Build a rich default system prompt from brand data.
     */
    protected function buildDefaultSystemPrompt(Brand $brand): string
    {
        $language = $this->getLanguageName($brand->getLanguage());
        $tone = $brand->getTone() ?? 'professional';
        $personality = implode(', ', $brand->getPersonality());
        $emojiUsage = $brand->getEmojiUsage();
        $context = $brand->buildAiContext();

        $prompt = "You are an expert social media content creator for the brand \"{$brand->name}\".";

        if ($brand->description) {
            $prompt .= "\nBrand description: {$brand->description}";
        }

        if ($brand->industry) {
            $prompt .= "\nIndustry: {$brand->industry}";
        }

        $prompt .= "\n\nBRAND VOICE:";
        $prompt .= "\n- Tone: {$tone}";
        if ($personality) {
            $prompt .= "\n- Personality: {$personality}";
        }
        $prompt .= "\n- Language: {$language}";
        $prompt .= "\n- Emoji usage: {$emojiUsage}";

        $prompt .= "\n\nTARGET AUDIENCE:";
        $prompt .= "\n- Age range: " . ($context['target_audience']['age_range'] ?? 'Not specified');
        $prompt .= "\n- Gender: " . ($context['target_audience']['gender'] ?? 'all');
        if (!empty($context['target_audience']['interests'])) {
            $prompt .= "\n- Interests: " . implode(', ', $context['target_audience']['interests']);
        }
        if (!empty($context['target_audience']['pain_points'])) {
            $prompt .= "\n- Pain points: " . implode(', ', $context['target_audience']['pain_points']);
        }

        $pillars = array_column($brand->content_pillars ?? [], 'name');
        if (!empty($pillars)) {
            $prompt .= "\n\nCONTENT PILLARS: " . implode(', ', $pillars);
        }

        $prompt .= <<<'PROMPT'


RULES:
1. Write all content in the specified language
2. Match the brand's voice exactly
3. Create content that resonates with the target audience
4. Include a strong hook (first sentence that grabs attention)
5. Provide value to the reader
6. Include a call-to-action when appropriate
7. DO NOT use Unicode bold/italic formatting - use plain text only

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text, no markdown code blocks.
{
  "caption": "The main content/caption for the post",
  "title": "A short title for the post (max 100 characters)"
}
PROMPT;

        return $prompt;
    }

    /**
     * Parse the AI response JSON.
     */
    protected function parseResponse(string $content): array
    {
        $content = trim($content);

        // Strip markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        // Try to extract the first JSON object if there's surrounding text
        if (!str_starts_with($content, '{')) {
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $content = $matches[0];
            }
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Last resort: treat entire response as the caption
            Log::warning('Direct text generation: could not parse JSON, using raw text', [
                'raw' => mb_substr($content, 0, 200),
            ]);

            return [
                'caption' => $content,
                'title' => '',
            ];
        }

        // Convert literal \n to actual newlines
        $convertNewlines = fn($text) => is_string($text) ? str_replace('\n', "\n", $text) : $text;

        return [
            'caption' => $convertNewlines($decoded['caption'] ?? $decoded['main_caption'] ?? ''),
            'title' => $convertNewlines($decoded['title'] ?? ''),
        ];
    }

    /**
     * Get full language name from code.
     */
    protected function getLanguageName(string $code): string
    {
        $languages = [
            'pl' => 'Polish',
            'en' => 'English',
            'de' => 'German',
            'es' => 'Spanish',
            'fr' => 'French',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            'cs' => 'Czech',
            'sk' => 'Slovak',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'bg' => 'Bulgarian',
            'hr' => 'Croatian',
            'sl' => 'Slovenian',
            'sr' => 'Serbian',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'el' => 'Greek',
            'tr' => 'Turkish',
            'ar' => 'Arabic',
            'he' => 'Hebrew',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'hi' => 'Hindi',
        ];

        return $languages[$code] ?? 'English';
    }
}
