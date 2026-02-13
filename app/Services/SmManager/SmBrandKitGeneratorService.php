<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmBrandKitGeneratorService
{
    use LogsApiUsage;

    /**
     * Generate a complete brand kit based on brand context.
     *
     * @param Brand $brand
     * @return array{success: bool, brand_kit?: array, error?: string, error_code?: string}
     */
    public function generateBrandKit(Brand $brand): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildBrandKitPrompt($brand);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_brand_kit_generate', [
            'brand_name' => $brand->name,
            'industry' => $brand->industry,
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

            $brandKit = [
                'colors' => $parsed['colors'] ?? [],
                'fonts' => $parsed['fonts'] ?? [],
                'style_preset' => $parsed['style_preset'] ?? null,
                'tone_of_voice' => $parsed['tone_of_voice'] ?? null,
                'voice_attributes' => $parsed['voice_attributes'] ?? [],
                'content_pillars' => $parsed['content_pillars'] ?? [],
                'hashtag_groups' => $parsed['hashtag_groups'] ?? [],
                'brand_guidelines_notes' => $parsed['brand_guidelines_notes'] ?? '',
            ];

            return [
                'success' => true,
                'brand_kit' => $brandKit,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmBrandKitGenerator: generateBrandKit failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt for brand kit generation.
     */
    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert brand identity designer with deep knowledge of visual branding, typography, color theory, and brand voice strategy.

Your job is to create comprehensive brand identity kits tailored to specific brands and their industries.

EXPERTISE AREAS:
- Color palette design and color psychology
- Typography selection and pairing
- Brand voice and tone definition
- Content strategy and pillar frameworks
- Hashtag strategy for social media
- Visual style direction

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text, no markdown code blocks.
{
  "colors": {
    "primary": "#hex",
    "secondary": "#hex",
    "accent": "#hex",
    "background": "#hex",
    "text": "#hex"
  },
  "fonts": {
    "heading": { "family": "Font Name", "weight": "700" },
    "body": { "family": "Font Name", "weight": "400" }
  },
  "style_preset": "modern|classic|bold|minimal|playful",
  "tone_of_voice": "professional|casual|friendly|authoritative|humorous|inspirational",
  "voice_attributes": ["attribute1", "attribute2", "attribute3", "attribute4"],
  "content_pillars": [
    {
      "name": "Pillar Name",
      "description": "What this pillar covers",
      "percentage": 30
    }
  ],
  "hashtag_groups": {
    "branded": ["#BrandTag1", "#BrandTag2"],
    "industry": ["#IndustryTag1", "#IndustryTag2"]
  },
  "brand_guidelines_notes": "Brief brand guidelines and recommendations."
}

IMPORTANT RULES:
1. Colors must be valid hex codes (e.g. #6366F1)
2. Font families should be popular Google Fonts available for web use
3. style_preset must be one of: modern, classic, bold, minimal, playful
4. tone_of_voice must be one of: professional, casual, friendly, authoritative, humorous, inspirational
5. voice_attributes should be 4-6 descriptive adjectives
6. content_pillars percentages must sum to 100
7. hashtag_groups should contain 3-5 hashtags per group
8. Colors should be harmonious and appropriate for the brand's industry
9. Tailor everything to the brand's specific industry, audience, and personality
PROMPT;
    }

    /**
     * Build the user prompt for brand kit generation.
     */
    protected function buildBrandKitPrompt(Brand $brand): string
    {
        $context = $brand->buildAiContext();
        $enabledPlatforms = $context['enabled_platforms'];
        $platformsList = !empty($enabledPlatforms) ? implode(', ', $enabledPlatforms) : 'instagram, linkedin, facebook';

        $prompt = <<<PROMPT
Create a comprehensive brand identity kit for this brand.

BRAND CONTEXT:
- Name: {$brand->name}
- Industry: {$brand->industry}
- Description: {$brand->description}
- Active platforms: {$platformsList}
PROMPT;

        if (!empty($context['target_audience'])) {
            $prompt .= "\n\nTARGET AUDIENCE:";
            $prompt .= "\n- Age range: " . ($context['target_audience']['age_range'] ?? 'not specified');
            $prompt .= "\n- Gender: " . ($context['target_audience']['gender'] ?? 'all');

            if (!empty($context['target_audience']['interests'])) {
                $prompt .= "\n- Interests: " . implode(', ', $context['target_audience']['interests']);
            }
        }

        $language = $context['voice']['language'] ?? 'en';

        if (!empty($context['voice'])) {
            $prompt .= "\n\nEXISTING BRAND VOICE:";
            $prompt .= "\n- Tone: " . ($context['voice']['tone'] ?? 'not set');
            $prompt .= "\n- Language: " . $language;

            if (!empty($context['voice']['personality'])) {
                $prompt .= "\n- Personality: " . implode(', ', $context['voice']['personality']);
            }
        }

        $prompt .= "\n\nGenerate a complete brand identity kit including color palette, typography, visual style, tone of voice, voice attributes, content pillars with percentages, branded and industry hashtags, and brief brand guidelines notes.";
        $prompt .= "\nMake sure the brand kit reflects the brand's industry and target audience.";
        $prompt .= "\nWrite ALL text content (pillar names, descriptions, guidelines notes, hashtags) in {$language}.";

        return $prompt;
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
            Log::warning('SmBrandKitGenerator: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 500),
            ]);

            return [];
        }

        return $decoded;
    }
}
