<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmContentPlan;
use App\Models\SmContentPlanSlot;
use App\Models\SmStrategy;
use App\Services\Concerns\LogsApiUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmContentPlanGeneratorService
{
    use LogsApiUsage;

    /**
     * Generate a full month of content slots based on the strategy.
     *
     * @param Brand $brand
     * @param SmStrategy $strategy
     * @param int $month 1-12
     * @param int $year e.g. 2026
     * @param Carbon|null $fromDate Optional start date override (e.g. today instead of 1st)
     * @return array{success: bool, plan?: SmContentPlan, slots_created?: int, error?: string, error_code?: string}
     */
    public function generateMonthlyPlan(Brand $brand, SmStrategy $strategy, int $month, int $year, ?Carbon $fromDate = null): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $startDate = $fromDate ?? Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $systemPrompt = $this->buildSystemPrompt();
        $previousTopics = $this->getPreviousTopics($brand);
        $userPrompt = $this->buildPlanPrompt($brand, $strategy, $startDate, $endDate, $previousTopics);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_content_plan_generate', [
            'brand_name' => $brand->name,
            'strategy_id' => $strategy->id,
            'month' => $month,
            'year' => $year,
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

            $this->completeAiLog($log, [
                'slots_count' => count($parsed['slots'] ?? []),
            ], $promptTokens, $completionTokens, $durationMs);

            $slots = $parsed['slots'] ?? [];

            if (empty($slots)) {
                return ['success' => false, 'error' => 'AI returned no content plan slots'];
            }

            // Create plan and slots in a transaction
            $plan = null;
            $slotsCreated = 0;

            DB::transaction(function () use ($brand, $strategy, $month, $year, $slots, $startDate, &$plan, &$slotsCreated) {
                $plan = SmContentPlan::create([
                    'brand_id' => $brand->id,
                    'sm_strategy_id' => $strategy->id,
                    'month' => $month,
                    'year' => $year,
                    'status' => 'draft',
                    'summary' => $this->buildPlanSummary($slots),
                    'total_slots' => count($slots),
                    'completed_slots' => 0,
                    'generated_at' => now(),
                ]);

                $slotsCreated = $this->createSlotsFromPlan($plan, $slots, $startDate);

                $plan->update(['total_slots' => $slotsCreated]);
            });

            return [
                'success' => true,
                'plan' => $plan,
                'slots_created' => $slotsCreated,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmContentPlanGenerator: generateMonthlyPlan failed', [
                'brand_id' => $brand->id,
                'strategy_id' => $strategy->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate slots for an existing plan (async-friendly, reports progress via cache).
     *
     * @return array{success: bool, slots_created?: int, error?: string}
     */
    public function generateSlotsForPlan(SmContentPlan $plan, Brand $brand, SmStrategy $strategy, ?Carbon $fromDate = null): array
    {
        $cacheKey = "content_plan_gen:{$plan->id}";

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            Cache::put($cacheKey, ['step' => 'failed', 'error' => 'no_api_key'], now()->addMinutes(10));
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $startDate = $fromDate ?? Carbon::create($plan->year, $plan->month, 1)->startOfMonth();
        $endDate = Carbon::create($plan->year, $plan->month, 1)->endOfMonth();

        $systemPrompt = $this->buildSystemPrompt();
        $previousTopics = $this->getPreviousTopics($brand);
        $userPrompt = $this->buildPlanPrompt($brand, $strategy, $startDate, $endDate, $previousTopics);

        Cache::put($cacheKey, ['step' => 'calling_ai', 'slots_created' => 0], now()->addMinutes(10));

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_content_plan_generate', [
            'brand_name' => $brand->name,
            'strategy_id' => $strategy->id,
            'month' => $plan->month,
            'year' => $plan->year,
            'plan_id' => $plan->id,
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

            $this->completeAiLog($log, [
                'slots_count' => count($parsed['slots'] ?? []),
            ], $promptTokens, $completionTokens, $durationMs);

            $slots = $parsed['slots'] ?? [];

            if (empty($slots)) {
                Cache::put($cacheKey, ['step' => 'failed', 'error' => 'empty_response'], now()->addMinutes(10));
                return ['success' => false, 'error' => 'AI returned no content plan slots'];
            }

            Cache::put($cacheKey, ['step' => 'creating_slots', 'slots_created' => 0, 'total_slots' => count($slots)], now()->addMinutes(10));

            $slotsCreated = 0;

            DB::transaction(function () use ($plan, $slots, $startDate, &$slotsCreated, $cacheKey) {
                $slotsCreated = $this->createSlotsFromPlan($plan, $slots, $startDate);

                $plan->update([
                    'status' => 'draft',
                    'summary' => $this->buildPlanSummary($slots),
                    'total_slots' => $slotsCreated,
                    'completed_slots' => 0,
                    'generated_at' => now(),
                ]);
            });

            Cache::put($cacheKey, ['step' => 'done', 'slots_created' => $slotsCreated], now()->addMinutes(5));

            return ['success' => true, 'slots_created' => $slotsCreated];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmContentPlanGenerator: generateSlotsForPlan failed', [
                'plan_id' => $plan->id,
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, ['step' => 'failed', 'error' => $e->getMessage()], now()->addMinutes(10));

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a single topic proposition for a new slot.
     *
     * @param Brand $brand
     * @param array{platform: string, content_type: string, date: string, pillar?: string} $context
     * @return array{success: bool, topic?: string, description?: string, error?: string, error_code?: string}
     */
    public function generateTopicProposition(Brand $brand, array $context): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $strategy = $brand->smStrategies()->active()->latest()->first();
        $previousTopics = $this->getPreviousTopics($brand);
        $language = $brand->getLanguage();
        $languageName = $this->getLanguageName($language);

        $pillarInfo = !empty($context['pillar']) ? "Content pillar: {$context['pillar']}" : '';
        $avoidBlock = '';
        if (!empty($previousTopics)) {
            $topicsList = implode("\n", array_map(fn ($t) => "- {$t}", $previousTopics));
            $avoidBlock = "\n\nPREVIOUSLY USED TOPICS (DO NOT REPEAT THESE):\n{$topicsList}\n\nYou MUST avoid repeating or closely paraphrasing any of the above topics.";
        }

        $strategyContext = '';
        if ($strategy) {
            $pillars = json_encode($strategy->content_pillars ?? [], JSON_UNESCAPED_UNICODE);
            $strategyContext = "\nBrand strategy pillars: {$pillars}";
        }

        $userPrompt = <<<PROMPT
Suggest 1 unique and specific topic for a {$context['platform']} {$context['content_type']} post.

Brand: {$brand->name}
Industry: {$brand->industry}
Date: {$context['date']}
{$pillarInfo}{$strategyContext}{$avoidBlock}

Respond in {$languageName}.
Respond ONLY with valid JSON: {"topic": "...", "description": "..."}
The topic should be concise (max 100 chars). The description should explain what the post should cover (1-2 sentences).
PROMPT;

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_topic_proposition', [
            'platform' => $context['platform'],
            'content_type' => $context['content_type'],
            'date' => $context['date'],
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a creative social media strategist. Respond only with valid JSON.'],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 256,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, [
                'topic' => $parsed['topic'] ?? '',
            ], $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'topic' => $parsed['topic'] ?? '',
                'description' => $parsed['description'] ?? '',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmContentPlanGenerator: generateTopicProposition failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt for content plan generation.
     */
    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert social media content planner. Your job is to create detailed, actionable monthly content calendars based on a brand's strategy.

RULES:
1. Distribute content evenly across the month
2. Respect the posting frequency per platform from the strategy
3. Distribute content across pillars according to their percentage weights
4. Apply the content mix percentages (educational, entertaining, promotional, etc.)
5. Schedule posts at optimal times from the strategy
6. Ensure variety - avoid posting the same content type or pillar on consecutive days
7. Consider weekdays vs. weekends (usually lower frequency on weekends)
8. Each slot must have a specific, actionable topic idea (not generic)
9. NEVER repeat or closely paraphrase topics from the "PREVIOUSLY USED TOPICS" list. Each topic must be fresh and unique.

RESPONSE FORMAT:
You MUST respond with valid JSON only.
{
  "slots": [
    {
      "date": "2026-03-01",
      "time": "09:00",
      "platform": "instagram",
      "content_type": "carousel",
      "topic": "Specific topic idea for this post",
      "description": "Detailed description of what this post should cover, key points to include",
      "pillar": "Content Pillar Name"
    }
  ]
}

IMPORTANT:
- date format: YYYY-MM-DD
- time format: HH:MM (24h)
- content_type should be one of: post, carousel, video, reel, story, article, thread, poll, live
- Each slot must have a unique, specific topic (not repeated)
- The total number of slots should match the weekly frequency * number of weeks in the month
PROMPT;
    }

    /**
     * Build the user prompt with strategy details and date range.
     */
    protected function buildPlanPrompt(Brand $brand, SmStrategy $strategy, Carbon $startDate, Carbon $endDate, array $previousTopics = []): string
    {
        $weeksInMonth = $startDate->diffInWeeks($endDate) + 1;

        $pillarsJson = json_encode($strategy->content_pillars ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $frequencyJson = json_encode($strategy->posting_frequency ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $contentMixJson = json_encode($strategy->content_mix ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $optimalTimesJson = json_encode($strategy->optimal_times ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $language = $brand->getLanguage();
        $languageName = $this->getLanguageName($language);

        $prompt = <<<PROMPT
Generate a content calendar for {$startDate->format('F Y')}.

BRAND: {$brand->name}
INDUSTRY: {$brand->industry}
LANGUAGE: {$languageName}

DATE RANGE: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}
WEEKS IN MONTH: {$weeksInMonth}

CONTENT PILLARS (with percentage weights):
{$pillarsJson}

POSTING FREQUENCY (posts per week per platform):
{$frequencyJson}

CONTENT MIX (percentage per type):
{$contentMixJson}

OPTIMAL POSTING TIMES (per platform per day):
{$optimalTimesJson}

Generate a complete content calendar with specific, actionable topics for each slot.
Topics and descriptions must be written in {$languageName}.
Distribute posts evenly across the date range, respecting pillar percentages and content mix.

IMPORTANT: Only generate slots for dates within the DATE RANGE above ({$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}). Do NOT generate any slots for dates before {$startDate->format('Y-m-d')}.
PROMPT;

        // Filter to only active platforms
        $activePlatforms = $strategy->active_platforms;
        if (!empty($activePlatforms)) {
            $activePlatformsList = implode(', ', $activePlatforms);
            $prompt .= "\n\nIMPORTANT: Only generate slots for the following platforms: {$activePlatformsList}. Do NOT create slots for any other platforms.";
        }

        if (!empty($previousTopics)) {
            $topicsList = implode("\n", array_map(fn ($t) => "- {$t}", $previousTopics));
            $prompt .= <<<PROMPT


PREVIOUSLY USED TOPICS (DO NOT REPEAT THESE):
{$topicsList}

You MUST avoid repeating or closely paraphrasing any of the above topics. Each new topic must be distinctly different.
PROMPT;
        }

        return $prompt;
    }

    /**
     * Bulk create SmContentPlanSlot records from AI-generated slots.
     */
    protected function createSlotsFromPlan(SmContentPlan $plan, array $slots, ?Carbon $startDate = null): int
    {
        $created = 0;

        foreach ($slots as $index => $slot) {
            $date = $slot['date'] ?? null;
            $time = $slot['time'] ?? null;

            if (!$date) {
                continue;
            }

            try {
                $scheduledDate = Carbon::parse($date);
            } catch (\Throwable) {
                Log::warning('SmContentPlanGenerator: invalid date in slot', [
                    'date' => $date,
                    'index' => $index,
                ]);
                continue;
            }

            // Skip slots before the start date (past days)
            if ($startDate && $scheduledDate->lt($startDate->copy()->startOfDay())) {
                continue;
            }

            SmContentPlanSlot::create([
                'sm_content_plan_id' => $plan->id,
                'scheduled_date' => $scheduledDate->toDateString(),
                'scheduled_time' => $time,
                'platform' => $slot['platform'] ?? 'instagram',
                'content_type' => $slot['content_type'] ?? 'post',
                'topic' => $slot['topic'] ?? '',
                'description' => $slot['description'] ?? '',
                'pillar' => $slot['pillar'] ?? '',
                'status' => 'planned',
                'position' => $index,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Get previous topics for this brand to avoid repetition.
     */
    protected function getPreviousTopics(Brand $brand, int $limit = 100): array
    {
        return SmContentPlanSlot::whereHas('contentPlan', fn ($q) => $q->where('brand_id', $brand->id))
            ->whereNotNull('topic')
            ->where('topic', '!=', '')
            ->orderByDesc('scheduled_date')
            ->limit($limit)
            ->pluck('topic')
            ->toArray();
    }

    /**
     * Build a summary of the plan from the generated slots.
     */
    protected function buildPlanSummary(array $slots): array
    {
        $platformCounts = [];
        $pillarCounts = [];
        $contentTypeCounts = [];

        foreach ($slots as $slot) {
            $platform = $slot['platform'] ?? 'unknown';
            $pillar = $slot['pillar'] ?? 'unknown';
            $contentType = $slot['content_type'] ?? 'unknown';

            $platformCounts[$platform] = ($platformCounts[$platform] ?? 0) + 1;
            $pillarCounts[$pillar] = ($pillarCounts[$pillar] ?? 0) + 1;
            $contentTypeCounts[$contentType] = ($contentTypeCounts[$contentType] ?? 0) + 1;
        }

        return [
            'total_slots' => count($slots),
            'platforms' => $platformCounts,
            'pillars' => $pillarCounts,
            'content_types' => $contentTypeCounts,
        ];
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
            Log::warning('SmContentPlanGenerator: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 500),
            ]);

            return ['slots' => []];
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
