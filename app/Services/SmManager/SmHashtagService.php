<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmBrandKit;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmHashtagService
{
    use LogsApiUsage;

    /**
     * Generate relevant hashtags for a given post text and platform.
     *
     * @param Brand $brand
     * @param string $text The post text to generate hashtags for
     * @param string $platform Target platform (instagram, x, linkedin, etc.)
     * @param int $count Total number of hashtags to generate
     * @return array{success: bool, hashtags?: array, categories?: array, error?: string, error_code?: string}
     */
    public function generate(Brand $brand, string $text, string $platform, int $count = 15): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildSystemPrompt($brand);
        $userPrompt = $this->buildPrompt($brand, $text, $platform, $count);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_hashtag_generate', [
            'platform' => $platform,
            'text_length' => mb_strlen($text),
            'count' => $count,
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            $allHashtags = $this->flattenHashtags($parsed['categories'] ?? []);

            return [
                'success' => true,
                'hashtags' => $allHashtags,
                'categories' => [
                    'trending' => $parsed['categories']['trending'] ?? [],
                    'niche' => $parsed['categories']['niche'] ?? [],
                    'branded' => $parsed['categories']['branded'] ?? [],
                ],
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmHashtag: generate failed', [
                'brand_id' => $brand->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build system prompt for the hashtag generation AI.
     */
    protected function buildSystemPrompt(Brand $brand): string
    {
        $prompt = "You are a senior social media hashtag strategist at a marketing agency, working for the brand \"{$brand->name}\".";

        if ($brand->industry) {
            $prompt .= "\nIndustry: {$brand->industry}";
        }

        if ($brand->description) {
            $prompt .= "\nBrand description: {$brand->description}";
        }

        $prompt .= <<<'PROMPT'


Your job is to create a research-level hashtag strategy that maximizes discoverability and attracts the right audience. Think like a growth strategist, not a content filler.

You must categorize hashtags into three groups:
1. TRENDING - High-volume, currently active hashtags relevant to the topic (broad reach, >100K posts)
2. NICHE - Specific, lower-competition hashtags for targeted discovery (community-focused, <50K posts). These are the most valuable for growth — they put content in front of the right people.
3. BRANDED - Hashtags specific to the brand, its campaigns, or its signature topics

STRATEGY:
- Balance long-tail (specific, 3+ words) and short-tail (broad, 1-2 words) hashtags
- Include hashtags where the brand can realistically rank in "Top Posts"
- Match hashtag language to the post language
- Consider current trends and seasonal relevance
- Niche hashtags should target the specific sub-community the content speaks to

RULES:
- All hashtags must start with # and use camelCase or lowercase (no spaces)
- Hashtags must be relevant to both the post content and the brand
- Never include offensive, controversial, or banned hashtags
- Respect platform-specific limits
- Do NOT use generic filler hashtags like #love #instagood #photooftheday unless truly relevant

RESPONSE FORMAT:
Respond with valid JSON only.
{
  "categories": {
    "trending": ["#hashtag1", "#hashtag2"],
    "niche": ["#hashtag3", "#hashtag4"],
    "branded": ["#hashtag5"]
  }
}
PROMPT;

        return $prompt;
    }

    /**
     * Build the user prompt with post context and platform-specific hashtag rules.
     */
    protected function buildPrompt(Brand $brand, string $text, string $platform, int $count): string
    {
        $platformRules = $this->getPlatformHashtagRules($platform);
        $language = $this->getLanguageName($brand->getLanguage());

        $pillars = array_column($brand->content_pillars ?? [], 'name');
        $pillarsText = !empty($pillars) ? implode(', ', $pillars) : 'N/A';

        $prompt = <<<PROMPT
Platform: {$platform}
Platform hashtag rules: {$platformRules}
Total hashtags needed: {$count}
Brand content pillars: {$pillarsText}
Post language: {$language} (match hashtag language to post language, mix with English hashtags for broader reach)

Post text:
"{$text}"
PROMPT;

        // Load branded and industry hashtags from SmBrandKit
        $brandKit = SmBrandKit::where('brand_id', $brand->id)->first();
        if ($brandKit) {
            $brandedTags = $brandKit->getBrandedHashtags();
            $industryTags = $brandKit->getIndustryHashtags();

            if (!empty($brandedTags)) {
                $prompt .= "\n\nBRANDED HASHTAGS (MUST include at least 1-2 of these in the branded category):\n" . implode(' ', $brandedTags);
            }

            if (!empty($industryTags)) {
                $prompt .= "\n\nINDUSTRY HASHTAGS (consider including relevant ones):\n" . implode(' ', $industryTags);
            }
        }

        $prompt .= <<<PROMPT


Generate {$count} hashtags distributed across the three categories (trending, niche, branded).
Suggested distribution:
- Trending: ~35% of total
- Niche: ~40% of total
- Branded: ~25% of total
PROMPT;

        return $prompt;
    }

    /**
     * Get platform-specific hashtag rules and recommendations.
     */
    protected function getPlatformHashtagRules(string $platform): string
    {
        return match ($platform) {
            'instagram' => 'Up to 30 hashtags allowed. Best practice: 8-15 hashtags. Mix of popular and niche. Place in caption or first comment.',
            'x' => '2-3 hashtags maximum. Only the most relevant and trending. Hashtags count toward 280 char limit.',
            'linkedin' => '3-5 hashtags recommended. Professional and industry-specific. Place at the end of the post.',
            'facebook' => '3-5 hashtags. Less hashtag-driven platform. Use only the most relevant ones.',
            'tiktok' => 'Up to 10 hashtags. Include trending challenge tags. Mix trending with niche for discovery.',
            'youtube' => 'Up to 15 hashtags in description. SEO-focused, include search terms. First 3 appear above title.',
            default => 'Up to 10 hashtags. Mix of trending and niche for optimal discovery.',
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
            Log::warning('SmHashtag: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 300),
            ]);

            return [
                'categories' => [
                    'trending' => [],
                    'niche' => [],
                    'branded' => [],
                ],
            ];
        }

        return $decoded;
    }

    /**
     * Get full language name from ISO code.
     */
    protected function getLanguageName(string $code): string
    {
        $languages = [
            'pl' => 'Polish', 'en' => 'English', 'de' => 'German', 'es' => 'Spanish',
            'fr' => 'French', 'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch',
            'ru' => 'Russian', 'uk' => 'Ukrainian', 'cs' => 'Czech', 'sk' => 'Slovak',
            'hu' => 'Hungarian', 'ro' => 'Romanian', 'bg' => 'Bulgarian', 'hr' => 'Croatian',
            'sl' => 'Slovenian', 'sr' => 'Serbian', 'lt' => 'Lithuanian', 'lv' => 'Latvian',
            'et' => 'Estonian', 'fi' => 'Finnish', 'sv' => 'Swedish', 'no' => 'Norwegian',
            'da' => 'Danish', 'el' => 'Greek', 'tr' => 'Turkish', 'ar' => 'Arabic',
            'he' => 'Hebrew', 'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese (Simplified)',
        ];

        return $languages[$code] ?? 'English';
    }

    /**
     * Flatten categorized hashtags into a single array, removing duplicates.
     */
    protected function flattenHashtags(array $categories): array
    {
        $all = array_merge(
            $categories['trending'] ?? [],
            $categories['niche'] ?? [],
            $categories['branded'] ?? [],
        );

        return array_values(array_unique($all));
    }
}
