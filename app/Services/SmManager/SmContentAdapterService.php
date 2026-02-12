<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmContentAdapterService
{
    use LogsApiUsage;

    /**
     * Adapt content from one platform to another.
     *
     * @param Brand $brand
     * @param string $originalText Source post text
     * @param string $sourcePlatform Platform the content was originally written for
     * @param string $targetPlatform Platform to adapt the content to
     * @return array{success: bool, text?: string, hashtags?: array, notes?: string, error?: string, error_code?: string}
     */
    public function adaptForPlatform(Brand $brand, string $originalText, string $sourcePlatform, string $targetPlatform): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $language = $this->getLanguageName($brand->getLanguage());
        $tone = $brand->getTone() ?? 'professional';
        $sourceConstraints = $this->getPlatformConstraints($sourcePlatform);
        $targetConstraints = $this->getPlatformConstraints($targetPlatform);

        $systemPrompt = $this->buildSystemPrompt($brand, $language, $tone);
        $userPrompt = $this->buildAdaptPrompt($originalText, $sourcePlatform, $targetPlatform, $sourceConstraints, $targetConstraints);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_content_adapter_single', [
            'source_platform' => $sourcePlatform,
            'target_platform' => $targetPlatform,
            'text_length' => mb_strlen($originalText),
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 2048,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'text' => $parsed['text'] ?? '',
                'hashtags' => $parsed['hashtags'] ?? [],
                'notes' => $parsed['notes'] ?? '',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmContentAdapter: adaptForPlatform failed', [
                'brand_id' => $brand->id,
                'source' => $sourcePlatform,
                'target' => $targetPlatform,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Adapt content for multiple target platforms in a single AI call.
     *
     * @param Brand $brand
     * @param string $originalText Source post text
     * @param string $sourcePlatform Platform the content was originally written for
     * @param array $targetPlatforms List of platform names to adapt to
     * @return array{success: bool, platforms?: array, error?: string, error_code?: string}
     */
    public function adaptForAllPlatforms(Brand $brand, string $originalText, string $sourcePlatform, array $targetPlatforms): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $language = $this->getLanguageName($brand->getLanguage());
        $tone = $brand->getTone() ?? 'professional';

        $systemPrompt = $this->buildSystemPrompt($brand, $language, $tone);
        $userPrompt = $this->buildBatchAdaptPrompt($originalText, $sourcePlatform, $targetPlatforms);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_content_adapter_batch', [
            'source_platform' => $sourcePlatform,
            'target_platforms' => $targetPlatforms,
            'text_length' => mb_strlen($originalText),
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 4096,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            $platforms = [];
            foreach ($targetPlatforms as $platform) {
                $platformData = $parsed['platforms'][$platform] ?? [];
                $platforms[$platform] = [
                    'text' => $platformData['text'] ?? '',
                    'hashtags' => $platformData['hashtags'] ?? [],
                    'notes' => $platformData['notes'] ?? '',
                ];
            }

            return [
                'success' => true,
                'platforms' => $platforms,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmContentAdapter: adaptForAllPlatforms failed', [
                'brand_id' => $brand->id,
                'source' => $sourcePlatform,
                'targets' => $targetPlatforms,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt for content adaptation.
     */
    protected function buildSystemPrompt(Brand $brand, string $language, string $tone): string
    {
        $prompt = "You are an expert cross-platform social media content adapter for the brand \"{$brand->name}\".";

        if ($brand->description) {
            $prompt .= "\nBrand description: {$brand->description}";
        }

        if ($brand->industry) {
            $prompt .= "\nIndustry: {$brand->industry}";
        }

        $personality = implode(', ', $brand->getPersonality());
        if ($personality) {
            $prompt .= "\nBrand personality: {$personality}";
        }

        $prompt .= <<<PROMPT


RULES:
1. Write all content in {$language}
2. Maintain the brand voice (tone: {$tone}) across all platforms
3. Preserve the core message and key information
4. Adapt format, length, style, and tone nuance to fit the target platform
5. DO NOT use Unicode bold/italic formatting - use plain text only
6. Add platform-appropriate hashtags
7. Respect character limits for each platform

ADAPTATION GUIDELINES:
- X (Twitter): Shorten drastically, make punchy, max 280 chars. Use 2-3 hashtags.
- Instagram: Visual storytelling, emojis OK, max 2200 chars. Up to 30 hashtags.
- LinkedIn: Professional, insightful, add industry context. 3-5 hashtags.
- Facebook: Conversational, engaging, encourage discussion. 3-5 hashtags.
- TikTok: Trendy, casual, Gen-Z friendly language. Up to 10 hashtags.
- YouTube: Descriptive, SEO-optimized. Up to 15 hashtags.

RESPONSE FORMAT:
Respond with valid JSON only. No additional text, no markdown code blocks.
PROMPT;

        return $prompt;
    }

    /**
     * Build the user prompt for single-platform adaptation.
     */
    protected function buildAdaptPrompt(
        string $originalText,
        string $sourcePlatform,
        string $targetPlatform,
        array $sourceConstraints,
        array $targetConstraints,
    ): string {
        return <<<PROMPT
Adapt the following {$sourcePlatform} post for {$targetPlatform}.

Source platform style: {$sourceConstraints['style']}
Target platform style: {$targetConstraints['style']}
Target max characters: {$targetConstraints['max_chars']}
Target max hashtags: {$targetConstraints['max_hashtags']}

Original {$sourcePlatform} post:
"{$originalText}"

Respond with JSON:
{
  "text": "Adapted post text for {$targetPlatform}",
  "hashtags": ["#tag1", "#tag2"],
  "notes": "Brief note on key adaptations made"
}
PROMPT;
    }

    /**
     * Build the user prompt for multi-platform batch adaptation.
     */
    protected function buildBatchAdaptPrompt(string $originalText, string $sourcePlatform, array $targetPlatforms): string
    {
        $platformDetails = [];
        foreach ($targetPlatforms as $platform) {
            $constraints = $this->getPlatformConstraints($platform);
            $platformDetails[] = "- {$platform}: max {$constraints['max_chars']} chars, max {$constraints['max_hashtags']} hashtags, style: {$constraints['style']}";
        }
        $platformList = implode("\n", $platformDetails);

        return <<<PROMPT
Adapt the following {$sourcePlatform} post for multiple platforms.

Target platforms:
{$platformList}

Original {$sourcePlatform} post:
"{$originalText}"

Respond with JSON:
{
  "platforms": {
    "platform_name": {
      "text": "Adapted post text",
      "hashtags": ["#tag1", "#tag2"],
      "notes": "Brief note on key adaptations"
    }
  }
}

Include an entry for each target platform.
PROMPT;
    }

    /**
     * Get character limits, hashtag rules, and formatting rules per platform.
     */
    protected function getPlatformConstraints(string $platform): array
    {
        return match ($platform) {
            'x' => ['max_chars' => 280, 'max_hashtags' => 3, 'style' => 'concise, punchy'],
            'instagram' => ['max_chars' => 2200, 'max_hashtags' => 30, 'style' => 'visual storytelling, emojis OK'],
            'linkedin' => ['max_chars' => 3000, 'max_hashtags' => 5, 'style' => 'professional, insightful'],
            'facebook' => ['max_chars' => 63206, 'max_hashtags' => 5, 'style' => 'conversational, engaging'],
            'tiktok' => ['max_chars' => 2200, 'max_hashtags' => 10, 'style' => 'trendy, casual, Gen-Z friendly'],
            'youtube' => ['max_chars' => 5000, 'max_hashtags' => 15, 'style' => 'descriptive, SEO-optimized'],
            default => ['max_chars' => 2000, 'max_hashtags' => 10, 'style' => 'general'],
        };
    }

    /**
     * Parse AI response, handling markdown code blocks.
     */
    protected function parseResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        if (!str_starts_with($content, '{')) {
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $content = $matches[0];
            }
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('SmContentAdapter: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 300),
            ]);

            return [
                'text' => $content,
                'hashtags' => [],
                'notes' => '',
                'platforms' => [],
            ];
        }

        $convertNewlines = fn ($text) => is_string($text) ? str_replace('\n', "\n", $text) : ($text ?? '');

        // Handle both single and batch responses
        if (isset($decoded['platforms'])) {
            foreach ($decoded['platforms'] as $platform => $data) {
                $decoded['platforms'][$platform]['text'] = $convertNewlines($data['text'] ?? '');
                $decoded['platforms'][$platform]['notes'] = $convertNewlines($data['notes'] ?? '');
            }
        } else {
            $decoded['text'] = $convertNewlines($decoded['text'] ?? '');
            $decoded['notes'] = $convertNewlines($decoded['notes'] ?? '');
        }

        return $decoded;
    }

    /**
     * Get full language name from ISO code.
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
        ];

        return $languages[$code] ?? 'English';
    }
}
