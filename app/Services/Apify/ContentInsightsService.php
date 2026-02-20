<?php

namespace App\Services\Apify;

use App\Models\Brand;
use App\Models\CiCompetitorPost;
use App\Models\CiInsight;
use App\Models\CiTrendingTopic;
use Illuminate\Support\Facades\Log;

class ContentInsightsService
{
    public function __construct(
        protected CompetitorAnalysisService $analysisService,
        protected TrendingContentService $trendingService,
    ) {}

    public function generateInsights(Brand $brand): int
    {
        $generated = 0;

        $generated += $this->generateContentGapInsights($brand);
        $generated += $this->generateTimingInsights($brand);
        $generated += $this->generateTrendAlerts($brand);
        $generated += $this->generateFormatInsights($brand);
        $generated += $this->generateHashtagInsights($brand);

        Log::info('[ContentInsightsService] Insights generated', [
            'brand_id' => $brand->id,
            'total' => $generated,
        ]);

        return $generated;
    }

    public function getProposalContext(Brand $brand): array
    {
        return [
            'competitor_context' => $this->analysisService->buildCompetitorContextForPrompt($brand),
            'trending_topics' => $this->getTrendingSummary($brand),
            'content_gaps' => $this->analysisService->identifyContentGaps($brand),
            'trend_adjusted_pillars' => $this->getTrendAdjustedPillars($brand),
        ];
    }

    public function getContentGenerationContext(Brand $brand, ?string $platform = null): array
    {
        $benchmarks = $this->analysisService->calculateBenchmarks($brand);

        $hashtags = $platform
            ? $this->trendingService->getHashtagsForPrompt($brand, $platform)
            : [];

        $topPosts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->topPerforming(5)
            ->whereNotNull('ai_analysis')
            ->get();

        $hookTypes = $topPosts->pluck('ai_analysis.hook_type')->filter()->countBy()->sortDesc()->keys()->take(3);
        $ctaTypes = $topPosts->pluck('ai_analysis.cta_type')->filter()->countBy()->sortDesc()->keys()->take(3);

        return [
            'benchmarks' => $benchmarks,
            'trending_hashtags' => $hashtags,
            'effective_hooks' => $hookTypes->values()->toArray(),
            'effective_ctas' => $ctaTypes->values()->toArray(),
            'style_tips' => $this->getStyleTips($brand, $platform),
        ];
    }

    public function getTrendAdjustedPillars(Brand $brand): array
    {
        $strategy = $brand->activeStrategy ?? null;
        if (!$strategy || empty($strategy->content_pillars)) {
            return [];
        }

        $pillars = $strategy->content_pillars;
        $trends = CiTrendingTopic::forBrand($brand->id)->active()->get();

        $adjusted = [];
        foreach ($pillars as $pillar) {
            $name = $pillar['name'] ?? '';
            $basePercentage = $pillar['percentage'] ?? (100 / count($pillars));

            $matchingTrends = $trends->filter(function ($trend) use ($name) {
                return str_contains(strtolower($trend->topic), strtolower($name))
                    || ($trend->category && str_contains(strtolower($trend->category), strtolower($name)));
            });

            $boost = 0;
            foreach ($matchingTrends as $trend) {
                $boost += match ($trend->trend_direction) {
                    'breakout' => 15,
                    'rising' => 8,
                    'stable' => 0,
                    'declining' => -5,
                    default => 0,
                };
            }

            $adjusted[] = [
                'name' => $name,
                'base_percentage' => $basePercentage,
                'adjusted_percentage' => max(5, min(50, $basePercentage + $boost)),
                'boost' => $boost,
                'trending_topics' => $matchingTrends->pluck('topic')->values()->toArray(),
            ];
        }

        // Normalize to 100%
        $total = array_sum(array_column($adjusted, 'adjusted_percentage'));
        if ($total > 0) {
            foreach ($adjusted as &$pillar) {
                $pillar['adjusted_percentage'] = round(($pillar['adjusted_percentage'] / $total) * 100, 1);
            }
        }

        return $adjusted;
    }

    public function getReportBenchmarks(Brand $brand): array
    {
        return $this->analysisService->calculateBenchmarks($brand);
    }

    protected function generateContentGapInsights(Brand $brand): int
    {
        $gaps = $this->analysisService->identifyContentGaps($brand);
        $count = 0;

        foreach (array_slice($gaps, 0, 5) as $gap) {
            CiInsight::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'insight_type' => 'content_gap',
                    'title' => "Content gap: {$gap['topic']}",
                ],
                [
                    'description' => "Competitors posted {$gap['competitor_posts']} times about \"{$gap['topic']}\" in the last 30 days. Consider creating content on this topic.",
                    'data' => $gap,
                    'priority' => min(10, $gap['competitor_posts']),
                    'valid_until' => now()->addDays(14),
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function generateTimingInsights(Brand $brand): int
    {
        $timings = $this->analysisService->getOptimalTimingInsights($brand);
        $count = 0;

        foreach ($timings as $platform => $slots) {
            if (empty($slots)) {
                continue;
            }

            $topSlot = $slots[0] ?? null;
            if (!$topSlot) {
                continue;
            }

            CiInsight::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'insight_type' => 'timing_optimization',
                    'platform' => $platform,
                ],
                [
                    'title' => "Best posting time for {$platform}",
                    'description' => "Based on competitor analysis, the best time to post on {$platform} is {$topSlot['day']} at {$topSlot['hour']}:00 (avg engagement: {$topSlot['avg_engagement']}%).",
                    'data' => ['best_slots' => $slots],
                    'priority' => 6,
                    'valid_until' => now()->addDays(14),
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function generateTrendAlerts(Brand $brand): int
    {
        $trends = CiTrendingTopic::forBrand($brand->id)
            ->active()
            ->rising()
            ->orderByDesc('growth_rate')
            ->limit(5)
            ->get();

        $count = 0;

        foreach ($trends as $trend) {
            CiInsight::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'insight_type' => 'trend_alert',
                    'title' => "Trending: {$trend->topic}",
                ],
                [
                    'platform' => $trend->platform,
                    'description' => "The topic \"{$trend->topic}\" is {$trend->trend_direction} with {$trend->growth_rate}% growth. Consider creating content around this topic.",
                    'data' => [
                        'topic' => $trend->topic,
                        'growth_rate' => $trend->growth_rate,
                        'direction' => $trend->trend_direction,
                        'related_hashtags' => $trend->related_hashtags,
                    ],
                    'priority' => $trend->trend_direction === 'breakout' ? 9 : 7,
                    'valid_until' => $trend->valid_until,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function generateFormatInsights(Brand $brand): int
    {
        $posts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->whereNotNull('ai_analysis')
            ->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        $formatPerformance = $posts->groupBy('ai_analysis.content_format')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'avg_engagement' => round($group->avg('engagement_rate'), 4),
            ])
            ->sortByDesc('avg_engagement');

        $topFormat = $formatPerformance->keys()->first();
        if (!$topFormat) {
            return 0;
        }

        $data = $formatPerformance->get($topFormat);

        CiInsight::updateOrCreate(
            [
                'brand_id' => $brand->id,
                'insight_type' => 'format_recommendation',
                'title' => "Top format: {$topFormat}",
            ],
            [
                'description' => "The \"{$topFormat}\" format has the highest average engagement ({$data['avg_engagement']}%) among competitors. Consider using this format more.",
                'data' => ['format_performance' => $formatPerformance->toArray()],
                'priority' => 5,
                'valid_until' => now()->addDays(14),
            ]
        );

        return 1;
    }

    protected function generateHashtagInsights(Brand $brand): int
    {
        $posts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->topPerforming(20)
            ->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        $hashtagCounts = [];
        foreach ($posts as $post) {
            foreach ($post->hashtags ?? [] as $hashtag) {
                $tag = strtolower($hashtag);
                $hashtagCounts[$tag] = ($hashtagCounts[$tag] ?? 0) + 1;
            }
        }

        arsort($hashtagCounts);
        $topHashtags = array_slice($hashtagCounts, 0, 15, true);

        if (empty($topHashtags)) {
            return 0;
        }

        $hashtagList = implode(', ', array_map(fn ($h) => "#{$h}", array_keys(array_slice($topHashtags, 0, 5))));

        CiInsight::updateOrCreate(
            [
                'brand_id' => $brand->id,
                'insight_type' => 'hashtag_strategy',
                'title' => 'Competitor hashtag strategy',
            ],
            [
                'description' => "Top hashtags used by high-performing competitor posts: {$hashtagList}. Consider incorporating these into your content.",
                'data' => ['top_hashtags' => $topHashtags],
                'priority' => 6,
                'valid_until' => now()->addDays(7),
            ]
        );

        return 1;
    }

    protected function getTrendingSummary(Brand $brand): string
    {
        $trends = CiTrendingTopic::forBrand($brand->id)
            ->active()
            ->rising()
            ->orderByDesc('growth_rate')
            ->limit(10)
            ->get();

        if ($trends->isEmpty()) {
            return '';
        }

        $lines = ["## Trending Topics"];
        foreach ($trends as $trend) {
            $platform = $trend->platform ? "[{$trend->platform}]" : "[cross-platform]";
            $lines[] = "- {$platform} {$trend->topic} ({$trend->trend_direction}, +{$trend->growth_rate}%)";
        }

        return implode("\n", $lines);
    }

    protected function getStyleTips(Brand $brand, ?string $platform): array
    {
        $posts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->topPerforming(10)
            ->whereNotNull('ai_analysis');

        if ($platform) {
            $posts->byPlatform($platform);
        }

        $posts = $posts->get();

        if ($posts->isEmpty()) {
            return [];
        }

        $tips = [];

        $avgCaptionLength = $posts->map(fn ($p) => mb_strlen($p->caption ?? ''))->avg();
        $tips[] = "Optimal caption length: ~" . round($avgCaptionLength) . " characters";

        $avgHashtags = $posts->map(fn ($p) => count($p->hashtags ?? []))->avg();
        $tips[] = "Average hashtags in top posts: " . round($avgHashtags);

        $topHook = $posts->pluck('ai_analysis.hook_type')->filter()->countBy()->sortDesc()->keys()->first();
        if ($topHook) {
            $tips[] = "Most effective hook type: {$topHook}";
        }

        return $tips;
    }
}
