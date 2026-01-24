<?php

namespace App\Services\AI;

use App\Models\AiOperationLog;
use App\Models\Brand;
use App\Services\OpenAiClientService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ContentPlannerService
{
    public function __construct(
        protected OpenAiClientService $openAiClient,
        protected AiResponseValidator $validator
    ) {}

    /**
     * Generate a content plan for a week.
     */
    public function generateWeeklyPlan(Brand $brand, Carbon $startDate): array
    {
        $endDate = $startDate->copy()->addDays(6);

        return $this->generatePlan($brand, $startDate, $endDate);
    }

    /**
     * Generate a content plan for a month.
     */
    public function generateMonthlyPlan(Brand $brand, Carbon $startDate): array
    {
        $endDate = $startDate->copy()->addMonth()->subDay();

        return $this->generatePlan($brand, $startDate, $endDate);
    }

    /**
     * Generate a content plan for a date range.
     */
    public function generatePlan(Brand $brand, Carbon $startDate, Carbon $endDate): array
    {
        $startTime = microtime(true);

        // Start logging
        $log = AiOperationLog::start($brand, 'plan_generation', [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        try {
            $context = $this->getBrandContext($brand);
            $systemPrompt = $this->buildSystemPrompt($brand);
            $userPrompt = $this->buildUserPrompt($brand, $startDate, $endDate, $context);

            $response = retry(3, function () use ($systemPrompt, $userPrompt) {
                return $this->openAiClient->chatCompletion([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ]);
            }, 1000);

            $content = $response->choices[0]->message->content;
            $plan = $this->validator->validateContentPlan($content);

            // Calculate costs (approximate)
            $tokensUsed = $response->usage->totalTokens ?? 0;
            $cost = $this->calculateCost($tokensUsed);
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            $log->complete($plan, $tokensUsed, $cost, $durationMs);

            return $plan;
        } catch (\Exception $e) {
            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            $log->fail($e->getMessage(), $durationMs);

            throw $e;
        }
    }

    /**
     * Get cached brand context or build new one.
     */
    protected function getBrandContext(Brand $brand): array
    {
        return Cache::remember(
            "brand:{$brand->id}:ai_context",
            3600,
            fn() => $brand->buildAiContext()
        );
    }

    /**
     * Build the system prompt for content planning.
     */
    protected function buildSystemPrompt(Brand $brand): string
    {
        $language = $brand->getLanguage() === 'pl' ? 'Polish' : 'English';

        return <<<PROMPT
You are an expert social media strategist. Your task is to create a content calendar for a brand.

RULES:
1. Write all content in {$language}
2. Follow the brand's voice, tone, and personality
3. Respect the specified posting frequency for each platform
4. Balance content across all content pillars according to their percentages
5. Schedule posts at optimal times provided
6. Avoid repetitive topics - ensure variety
7. Create content that drives engagement

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text before or after the JSON.
{
  "posts": [
    {
      "date": "YYYY-MM-DD",
      "time": "HH:MM",
      "platform": "facebook|instagram|youtube",
      "pillar": "Content pillar name",
      "topic": "Brief topic description (1-2 sentences)",
      "type": "text|carousel|video|story|reel|short",
      "hook": "Attention-grabbing opening line"
    }
  ]
}

Content types per platform:
- Facebook: text, video, carousel
- Instagram: text, carousel, video, story, reel
- YouTube: video, short
PROMPT;
    }

    /**
     * Build the user prompt with brand-specific details.
     */
    protected function buildUserPrompt(Brand $brand, Carbon $startDate, Carbon $endDate, array $context): string
    {
        $pillarsText = collect($context['content_pillars'])->map(function ($pillar) {
            return "- {$pillar['name']}: {$pillar['description'] ?? 'No description'} ({$pillar['percentage']}%)";
        })->join("\n");

        $platformsText = collect($context['enabled_platforms'])->map(function ($platform) use ($brand) {
            $freq = $brand->getPostingFrequency(\App\Enums\Platform::from($platform));
            $times = implode(', ', $brand->getBestTimes(\App\Enums\Platform::from($platform)));
            return "- {$platform}: {$freq} posts/week, best times: {$times}";
        })->join("\n");

        $audienceText = "Age: {$context['target_audience']['age_range']}, Gender: {$context['target_audience']['gender']}";
        if (!empty($context['target_audience']['interests'])) {
            $audienceText .= "\nInterests: " . implode(', ', $context['target_audience']['interests']);
        }
        if (!empty($context['target_audience']['pain_points'])) {
            $audienceText .= "\nPain points: " . implode(', ', $context['target_audience']['pain_points']);
        }

        $voiceText = "Tone: {$context['voice']['tone']}, Emoji usage: {$context['voice']['emoji_usage']}";
        if (!empty($context['voice']['personality'])) {
            $voiceText .= "\nPersonality: " . implode(', ', $context['voice']['personality']);
        }

        return <<<PROMPT
Create a content plan for the following brand:

BRAND: {$context['name']}
INDUSTRY: {$context['industry']}
DESCRIPTION: {$context['description']}

TARGET AUDIENCE:
{$audienceText}

BRAND VOICE:
{$voiceText}

CONTENT PILLARS (balance content according to these percentages):
{$pillarsText}

PLATFORMS & FREQUENCY:
{$platformsText}

PERIOD: {$startDate->toDateString()} to {$endDate->toDateString()}

Please create a detailed content plan for this period. Ensure:
1. Posts are distributed across the period
2. Content pillars are balanced according to percentages
3. Each platform receives the correct number of posts per week
4. Topics are varied and engaging
5. Posts are scheduled at the specified best times
PROMPT;
    }

    /**
     * Calculate approximate cost based on tokens used.
     * Using GPT-4o pricing: $5/1M input, $15/1M output (averaged to $10/1M)
     */
    protected function calculateCost(int $tokensUsed): float
    {
        return ($tokensUsed / 1000000) * 10;
    }
}
