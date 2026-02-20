<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmStrategy;
use App\Services\Apify\CompetitorAnalysisService;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmStrategyGeneratorService
{
    use LogsApiUsage;

    /**
     * Analyze brand data and generate a full social media strategy.
     *
     * @param Brand $brand
     * @return array{success: bool, strategy?: array, error?: string, error_code?: string}
     */
    public function generateStrategy(Brand $brand): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildStrategyPrompt($brand);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_strategy_generate', [
            'brand_name' => $brand->name,
            'industry' => $brand->industry,
            'has_existing_pillars' => !empty($brand->content_pillars),
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

            $strategy = [
                'content_pillars' => $parsed['content_pillars'] ?? [],
                'posting_frequency' => $parsed['posting_frequency'] ?? [],
                'target_audience' => $parsed['target_audience'] ?? [],
                'goals' => $parsed['goals'] ?? [],
                'content_mix' => $parsed['content_mix'] ?? [],
                'optimal_times' => $parsed['optimal_times'] ?? [],
                'ai_recommendations' => $parsed['ai_recommendations'] ?? '',
            ];

            return [
                'success' => true,
                'strategy' => $strategy,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmStrategyGenerator: generateStrategy failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Refine an existing strategy based on actual performance data.
     *
     * @param Brand $brand
     * @param SmStrategy $currentStrategy
     * @param array $performanceData Metrics: engagement_rate, top_posts, worst_posts, audience_growth, etc.
     * @return array{success: bool, strategy?: array, error?: string, error_code?: string}
     */
    public function refineStrategy(Brand $brand, SmStrategy $currentStrategy, array $performanceData): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildRefinePrompt($brand, $currentStrategy, $performanceData);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_strategy_refine', [
            'brand_name' => $brand->name,
            'strategy_id' => $currentStrategy->id,
            'has_performance_data' => !empty($performanceData),
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

            $strategy = [
                'content_pillars' => $parsed['content_pillars'] ?? $currentStrategy->content_pillars,
                'posting_frequency' => $parsed['posting_frequency'] ?? $currentStrategy->posting_frequency,
                'target_audience' => $parsed['target_audience'] ?? $currentStrategy->target_audience,
                'goals' => $parsed['goals'] ?? $currentStrategy->goals,
                'content_mix' => $parsed['content_mix'] ?? $currentStrategy->content_mix,
                'optimal_times' => $parsed['optimal_times'] ?? $currentStrategy->optimal_times,
                'ai_recommendations' => $parsed['ai_recommendations'] ?? '',
            ];

            return [
                'success' => true,
                'strategy' => $strategy,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmStrategyGenerator: refineStrategy failed', [
                'brand_id' => $brand->id,
                'strategy_id' => $currentStrategy->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt for strategy generation.
     */
    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert social media strategist with deep knowledge of platform algorithms, audience engagement, and content marketing.

Your job is to create comprehensive, data-informed social media strategies tailored to specific brands.

EXPERTISE AREAS:
- Platform-specific best practices (Instagram, LinkedIn, X, Facebook, TikTok, YouTube)
- Content pillar frameworks
- Audience targeting and segmentation
- Posting frequency optimization
- Content mix and format strategies
- Timing optimization per platform and audience

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text, no markdown code blocks.
{
  "content_pillars": [
    {
      "name": "Pillar Name",
      "description": "What this pillar covers and why it matters",
      "percentage": 30,
      "content_types": ["carousel", "video", "text post"],
      "example_topics": ["Topic 1", "Topic 2"]
    }
  ],
  "posting_frequency": {
    "instagram": 5,
    "linkedin": 3,
    "x": 7,
    "facebook": 3,
    "tiktok": 4,
    "youtube": 1
  },
  "target_audience": {
    "age_range": "25-35",
    "gender": "all",
    "interests": ["interest1", "interest2", "interest3"],
    "pain_points": ["pain point 1", "pain point 2", "pain point 3"]
  },
  "goals": [
    {
      "goal": "Goal Name",
      "metric": "KPI metric to track",
      "target_value": "Target value",
      "timeframe": "monthly"
    }
  ],
  "content_mix": {
    "educational": 30,
    "entertaining": 20,
    "promotional": 15,
    "inspirational": 15,
    "community": 10,
    "behind_the_scenes": 10
  },
  "optimal_times": {
    "instagram": {"monday": ["09:00", "18:00"], "tuesday": ["10:00", "19:00"], "wednesday": ["09:00", "18:00"], "thursday": ["10:00", "19:00"], "friday": ["09:00", "17:00"], "saturday": ["11:00"], "sunday": ["11:00"]},
    "linkedin": {"monday": ["08:00", "12:00"], "tuesday": ["08:00", "12:00"], "wednesday": ["08:00", "12:00"], "thursday": ["08:00", "12:00"], "friday": ["08:00"]}
  },
  "ai_recommendations": "Free-text strategic recommendations and insights for the brand."
}

IMPORTANT RULES:
1. Posting frequency values are posts PER WEEK for each platform
2. Content pillar percentages must sum to 100
3. Content mix percentages must sum to 100
4. Only include platforms that are relevant to the brand
5. Optimal times should be in HH:MM format (24h)
6. Base recommendations on current platform best practices
7. Tailor everything to the brand's specific industry and audience
PROMPT;
    }

    /**
     * Build the user prompt for initial strategy generation.
     */
    protected function buildStrategyPrompt(Brand $brand): string
    {
        $context = $brand->buildAiContext();
        $enabledPlatforms = $context['enabled_platforms'];
        $platformsList = !empty($enabledPlatforms) ? implode(', ', $enabledPlatforms) : 'instagram, linkedin, facebook';

        $existingPillars = $brand->content_pillars ?? [];
        $pillarsText = !empty($existingPillars)
            ? "Existing content pillars: " . implode(', ', array_column($existingPillars, 'name'))
            : "No existing content pillars defined.";

        $prompt = <<<PROMPT
Create a comprehensive social media strategy for this brand.

BRAND CONTEXT:
- Name: {$brand->name}
- Industry: {$brand->industry}
- Description: {$brand->description}
- Active platforms: {$platformsList}
- {$pillarsText}

TARGET AUDIENCE (current):
- Age range: {$context['target_audience']['age_range']}
- Gender: {$context['target_audience']['gender']}
PROMPT;

        if (!empty($context['target_audience']['interests'])) {
            $prompt .= "\n- Interests: " . implode(', ', $context['target_audience']['interests']);
        }

        if (!empty($context['target_audience']['pain_points'])) {
            $prompt .= "\n- Pain points: " . implode(', ', $context['target_audience']['pain_points']);
        }

        $language = $context['voice']['language'] ?? 'en';

        $prompt .= "\n\nBRAND VOICE:";
        $prompt .= "\n- Tone: " . ($context['voice']['tone'] ?? 'professional');
        $prompt .= "\n- Language: " . $language;

        if (!empty($context['voice']['personality'])) {
            $prompt .= "\n- Personality: " . implode(', ', $context['voice']['personality']);
        }

        // Inject competitive landscape from CI
        try {
            $analysisService = app(CompetitorAnalysisService::class);
            $competitorContext = $analysisService->buildCompetitorContextForPrompt($brand);
            if (!empty($competitorContext)) {
                $prompt .= "\n\nCOMPETITIVE LANDSCAPE:\n" . $competitorContext;
            }
        } catch (\Throwable $e) {
            // CI data unavailable — proceed without it
        }

        $prompt .= "\n\nGenerate a strategy that includes content pillars, posting frequency per platform, refined target audience, goals with KPIs, content mix percentages, optimal posting times, and strategic recommendations.";
        $prompt .= "\nOnly include platforms from: {$platformsList}";
        $prompt .= "\nWrite ALL text content (pillar names, descriptions, recommendations, audience pain points) in {$language}.";

        return $prompt;
    }

    /**
     * Build the user prompt for strategy refinement based on performance data.
     */
    protected function buildRefinePrompt(Brand $brand, SmStrategy $currentStrategy, array $performanceData): string
    {
        $currentStrategyJson = json_encode([
            'content_pillars' => $currentStrategy->content_pillars,
            'posting_frequency' => $currentStrategy->posting_frequency,
            'target_audience' => $currentStrategy->target_audience,
            'goals' => $currentStrategy->goals,
            'content_mix' => $currentStrategy->content_mix,
            'optimal_times' => $currentStrategy->optimal_times,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $performanceJson = json_encode($performanceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Refine the current social media strategy based on actual performance data.

BRAND: {$brand->name}
INDUSTRY: {$brand->industry}

CURRENT STRATEGY:
{$currentStrategyJson}

PERFORMANCE DATA:
{$performanceJson}

Analyze the performance data and refine the strategy:
1. Adjust content pillar percentages based on what's working
2. Update posting frequency if some platforms perform better
3. Refine target audience based on actual engagement data
4. Update goals and KPIs based on progress
5. Adjust content mix based on format performance
6. Optimize posting times based on engagement patterns
7. Provide updated recommendations highlighting what to change and why

Return the complete updated strategy in the same JSON format.
Include specific data-driven reasoning in ai_recommendations.
PROMPT;
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
            Log::warning('SmStrategyGenerator: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 500),
            ]);

            return [
                'content_pillars' => [],
                'posting_frequency' => [],
                'target_audience' => [],
                'goals' => [],
                'content_mix' => [],
                'optimal_times' => [],
                'ai_recommendations' => $content,
            ];
        }

        return $decoded;
    }
}
