<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmAnalyticsSnapshot;
use App\Models\SmPerformanceScore;
use App\Models\SmPostAnalytics;
use App\Models\SmScheduledPost;
use App\Models\SmWeeklyReport;
use App\Models\SocialPost;
use App\Services\Concerns\LogsApiUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmReportGeneratorService
{
    use LogsApiUsage;

    protected string $model;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4o');
    }

    /**
     * Generate a weekly performance report for a brand.
     *
     * 1. Calculate the reporting period (default: last 7 days)
     * 2. Gather all relevant data (snapshots, post analytics, top posts, growth)
     * 3. Ask AI to generate summary and recommendations
     * 4. Create SmWeeklyReport record
     */
    public function generateWeeklyReport(Brand $brand, ?Carbon $periodStart = null): array
    {
        $this->resetRequestId();

        $start = $periodStart ?? Carbon::now()->subDays(7)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        // Check if a report already exists for this period
        $existing = SmWeeklyReport::where('brand_id', $brand->id)
            ->where('period_start', $start->toDateString())
            ->where('period_end', $end->toDateString())
            ->first();

        if ($existing) {
            return [
                'success' => true,
                'report' => $existing,
                'message' => 'Report already exists for this period.',
            ];
        }

        // Get API key
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return [
                'success' => false,
                'error_code' => 'no_api_key',
                'error' => 'No OpenAI API key configured for this brand.',
            ];
        }

        // Gather all data for the period
        $data = $this->gatherWeeklyData($brand, $start, $end);

        if ($data['total_posts'] === 0 && empty($data['snapshots'])) {
            return [
                'success' => false,
                'error' => 'Not enough data to generate a report for this period.',
            ];
        }

        // Generate AI summary
        $aiResult = $this->generateAiSummary($brand, $data, $apiKey);

        if (!$aiResult['success']) {
            return $aiResult;
        }

        $aiData = $aiResult['data'];

        // Create the report record
        $report = SmWeeklyReport::create([
            'brand_id' => $brand->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'summary' => $aiData['summary'] ?? $this->buildFallbackSummary($data),
            'top_posts' => $data['top_posts'],
            'recommendations' => $aiData['recommendations'] ?? 'No AI recommendations available.',
            'growth_metrics' => $aiData['growth_metrics'] ?? $data['growth_metrics'],
            'platform_breakdown' => $aiData['platform_breakdown'] ?? $data['platform_breakdown'],
            'status' => 'ready',
            'generated_at' => now(),
        ]);

        Log::info('SmReportGenerator: Weekly report created', [
            'brand_id' => $brand->id,
            'report_id' => $report->id,
            'period' => $report->getPeriodLabel(),
        ]);

        return [
            'success' => true,
            'report' => $report,
        ];
    }

    /**
     * Collect all metrics and data for the reporting period.
     */
    protected function gatherWeeklyData(Brand $brand, Carbon $start, Carbon $end): array
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        // 1. Analytics snapshots (follower counts, engagement rates per platform)
        $snapshots = SmAnalyticsSnapshot::where('brand_id', $brand->id)
            ->inPeriod($startStr, $endStr)
            ->orderBy('snapshot_date')
            ->get();

        $formattedSnapshots = $snapshots->map(fn ($s) => [
            'platform' => $s->platform,
            'date' => $s->snapshot_date->toDateString(),
            'followers' => $s->followers,
            'reach' => $s->reach,
            'impressions' => $s->impressions,
            'engagement_rate' => (float) $s->engagement_rate,
            'profile_views' => $s->profile_views,
            'website_clicks' => $s->website_clicks,
        ])->toArray();

        // 2. Post analytics (individual post performance)
        $postAnalytics = SmPostAnalytics::whereHas('socialPost', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->whereBetween('collected_at', [$start, $end])
            ->get();

        // 3. Top performing posts (by total engagement)
        $topPosts = $this->getTopPosts($brand, $start, $end, 5);

        // 4. Worst performing posts
        $worstPosts = $this->getWorstPosts($brand, $start, $end, 3);

        // 5. Published/scheduled counts
        $publishedCount = SmScheduledPost::where('brand_id', $brand->id)
            ->where('status', 'published')
            ->whereBetween('published_at', [$start, $end])
            ->count();

        $scheduledCount = SmScheduledPost::where('brand_id', $brand->id)
            ->whereBetween('scheduled_at', [$start, $end])
            ->count();

        // 6. Growth metrics (follower changes)
        $growthMetrics = $this->calculateGrowthMetrics($brand, $start, $end);

        // 7. Platform breakdown
        $platformBreakdown = $this->calculatePlatformBreakdown($postAnalytics);

        // 8. Previous period data for comparison
        $previousStart = $start->copy()->subDays(7);
        $previousEnd = $start->copy()->subDay()->endOfDay();
        $previousPostAnalytics = SmPostAnalytics::whereHas('socialPost', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->whereBetween('collected_at', [$previousStart, $previousEnd])
            ->get();

        $previousEngagement = $previousPostAnalytics->sum(fn ($a) => $a->getTotalEngagement());
        $currentEngagement = $postAnalytics->sum(fn ($a) => $a->getTotalEngagement());

        return [
            'snapshots' => $formattedSnapshots,
            'total_posts' => $publishedCount,
            'scheduled_posts' => $scheduledCount,
            'top_posts' => $topPosts,
            'worst_posts' => $worstPosts,
            'growth_metrics' => $growthMetrics,
            'platform_breakdown' => $platformBreakdown,
            'period' => [
                'start' => $startStr,
                'end' => $endStr,
            ],
            'engagement' => [
                'current_total' => $currentEngagement,
                'previous_total' => $previousEngagement,
                'change_percent' => $previousEngagement > 0
                    ? round((($currentEngagement - $previousEngagement) / $previousEngagement) * 100, 1)
                    : null,
            ],
            'averages' => [
                'likes' => $postAnalytics->avg('likes') ? round($postAnalytics->avg('likes'), 1) : 0,
                'comments' => $postAnalytics->avg('comments') ? round($postAnalytics->avg('comments'), 1) : 0,
                'shares' => $postAnalytics->avg('shares') ? round($postAnalytics->avg('shares'), 1) : 0,
                'reach' => $postAnalytics->avg('reach') ? round($postAnalytics->avg('reach'), 1) : 0,
                'engagement_rate' => $postAnalytics->avg('engagement_rate')
                    ? round((float) $postAnalytics->avg('engagement_rate'), 4)
                    : 0,
            ],
        ];
    }

    /**
     * Build the AI prompt for report generation.
     */
    protected function buildReportPrompt(Brand $brand, array $data): string
    {
        $brandContext = json_encode([
            'name' => $brand->name,
            'industry' => $brand->industry,
            'description' => $brand->description,
        ], JSON_PRETTY_PRINT);

        $dataJson = json_encode($data, JSON_PRETTY_PRINT);

        return <<<PROMPT
Generate a weekly social media performance report for this brand.

## Brand Context
{$brandContext}

## Period
{$data['period']['start']} to {$data['period']['end']}

## Data
{$dataJson}

Analyze the data and generate a structured report. Respond with valid JSON only:
{
  "summary": {
    "headline": "One-line summary of the week's performance",
    "overview": "2-3 sentence overview",
    "highlights": ["highlight 1", "highlight 2", "highlight 3"],
    "concerns": ["concern 1", "concern 2"]
  },
  "recommendations": "3-5 actionable recommendations for next week, as a single text block with numbered items",
  "growth_metrics": {
    "follower_change": <number or null>,
    "engagement_change_percent": <number or null>,
    "best_performing_platform": "<platform name>",
    "worst_performing_platform": "<platform name>",
    "trend": "growing|stable|declining"
  },
  "platform_breakdown": {
    "<platform>": {
      "posts_count": <number>,
      "avg_engagement_rate": <number>,
      "total_reach": <number>,
      "top_content_type": "<type>",
      "assessment": "brief assessment"
    }
  }
}
PROMPT;
    }

    /**
     * Call OpenAI to generate the report summary.
     */
    protected function generateAiSummary(Brand $brand, array $data, string $apiKey): array
    {
        $prompt = $this->buildReportPrompt($brand, $data);
        $language = $brand->getLanguage();

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_weekly_report', [
            'period_start' => $data['period']['start'],
            'period_end' => $data['period']['end'],
            'total_posts' => $data['total_posts'],
        ], $this->model);

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a social media analytics expert generating weekly performance reports. Write ALL text (summary, recommendations, insights) in {$language}. Always respond with valid JSON only, no markdown formatting.",
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.4,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $rawContent = $response->choices[0]->message->content ?? '{}';

            $this->completeAiLog(
                $log,
                ['raw_response' => $rawContent],
                $promptTokens,
                $completionTokens,
                $durationMs
            );

            $parsed = json_decode($rawContent, true);

            if (!$parsed) {
                Log::warning('SmReportGenerator: Failed to parse AI response', [
                    'brand_id' => $brand->id,
                    'raw' => $rawContent,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'summary' => $this->buildFallbackSummary($data),
                        'recommendations' => 'AI analysis could not be parsed. Review the raw metrics for insights.',
                        'growth_metrics' => $data['growth_metrics'],
                        'platform_breakdown' => $data['platform_breakdown'],
                    ],
                ];
            }

            return [
                'success' => true,
                'data' => $parsed,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmReportGenerator: AI summary generation failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            // Return fallback so the report is still created with raw data
            return [
                'success' => true,
                'data' => [
                    'summary' => $this->buildFallbackSummary($data),
                    'recommendations' => 'AI analysis unavailable. Error: ' . $e->getMessage(),
                    'growth_metrics' => $data['growth_metrics'],
                    'platform_breakdown' => $data['platform_breakdown'],
                ],
            ];
        }
    }

    /**
     * Get the top performing posts by total engagement.
     */
    protected function getTopPosts(Brand $brand, Carbon $start, Carbon $end, int $limit): array
    {
        $analytics = SmPostAnalytics::whereHas('socialPost', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->whereBetween('collected_at', [$start, $end])
            ->with('socialPost:id,title,main_caption,published_at')
            ->get()
            ->sortByDesc(fn ($a) => $a->getTotalEngagement())
            ->take($limit);

        return $analytics->map(fn (SmPostAnalytics $a) => [
            'post_id' => $a->social_post_id,
            'title' => $a->socialPost->title ?? null,
            'platform' => $a->platform,
            'likes' => $a->likes ?? 0,
            'comments' => $a->comments ?? 0,
            'shares' => $a->shares ?? 0,
            'saves' => $a->saves ?? 0,
            'reach' => $a->reach ?? 0,
            'engagement_rate' => (float) ($a->engagement_rate ?? 0),
            'total_engagement' => $a->getTotalEngagement(),
            'published_at' => $a->socialPost->published_at?->toDateTimeString(),
        ])->values()->toArray();
    }

    /**
     * Get the worst performing posts by total engagement.
     */
    protected function getWorstPosts(Brand $brand, Carbon $start, Carbon $end, int $limit): array
    {
        $analytics = SmPostAnalytics::whereHas('socialPost', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->whereBetween('collected_at', [$start, $end])
            ->with('socialPost:id,title,main_caption,published_at')
            ->get()
            ->sortBy(fn ($a) => $a->getTotalEngagement())
            ->take($limit);

        return $analytics->map(fn (SmPostAnalytics $a) => [
            'post_id' => $a->social_post_id,
            'title' => $a->socialPost->title ?? null,
            'platform' => $a->platform,
            'likes' => $a->likes ?? 0,
            'comments' => $a->comments ?? 0,
            'shares' => $a->shares ?? 0,
            'total_engagement' => $a->getTotalEngagement(),
        ])->values()->toArray();
    }

    /**
     * Calculate follower growth metrics across platforms.
     */
    protected function calculateGrowthMetrics(Brand $brand, Carbon $start, Carbon $end): array
    {
        $platforms = SmAnalyticsSnapshot::where('brand_id', $brand->id)
            ->inPeriod($start->toDateString(), $end->toDateString())
            ->select('platform')
            ->distinct()
            ->pluck('platform');

        $metrics = [];
        $totalFollowerChange = 0;
        $bestPlatform = null;
        $bestEngagement = -1;
        $worstPlatform = null;
        $worstEngagement = PHP_FLOAT_MAX;

        foreach ($platforms as $platform) {
            $firstSnapshot = SmAnalyticsSnapshot::where('brand_id', $brand->id)
                ->forPlatform($platform)
                ->inPeriod($start->toDateString(), $end->toDateString())
                ->orderBy('snapshot_date')
                ->first();

            $lastSnapshot = SmAnalyticsSnapshot::where('brand_id', $brand->id)
                ->forPlatform($platform)
                ->inPeriod($start->toDateString(), $end->toDateString())
                ->orderByDesc('snapshot_date')
                ->first();

            if ($firstSnapshot && $lastSnapshot) {
                $followerChange = ($lastSnapshot->followers ?? 0) - ($firstSnapshot->followers ?? 0);
                $totalFollowerChange += $followerChange;
                $avgEngagement = (float) ($lastSnapshot->engagement_rate ?? 0);

                $metrics[$platform] = [
                    'followers_start' => $firstSnapshot->followers ?? 0,
                    'followers_end' => $lastSnapshot->followers ?? 0,
                    'follower_change' => $followerChange,
                    'avg_engagement_rate' => $avgEngagement,
                ];

                if ($avgEngagement > $bestEngagement) {
                    $bestEngagement = $avgEngagement;
                    $bestPlatform = $platform;
                }

                if ($avgEngagement < $worstEngagement) {
                    $worstEngagement = $avgEngagement;
                    $worstPlatform = $platform;
                }
            }
        }

        // Determine trend based on engagement change
        $engagementChangePercent = null;
        $previousStart = $start->copy()->subDays(7);
        $previousEnd = $start->copy()->subDay();

        $previousSnapshots = SmAnalyticsSnapshot::where('brand_id', $brand->id)
            ->inPeriod($previousStart->toDateString(), $previousEnd->toDateString())
            ->avg('engagement_rate');

        $currentAvgEngagement = SmAnalyticsSnapshot::where('brand_id', $brand->id)
            ->inPeriod($start->toDateString(), $end->toDateString())
            ->avg('engagement_rate');

        if ($previousSnapshots > 0 && $currentAvgEngagement !== null) {
            $engagementChangePercent = round(
                (((float) $currentAvgEngagement - (float) $previousSnapshots) / (float) $previousSnapshots) * 100,
                1
            );
        }

        $trend = 'stable';
        if ($engagementChangePercent !== null) {
            if ($engagementChangePercent > 5) {
                $trend = 'growing';
            } elseif ($engagementChangePercent < -5) {
                $trend = 'declining';
            }
        }

        return [
            'follower_change' => $totalFollowerChange,
            'engagement_change_percent' => $engagementChangePercent,
            'best_performing_platform' => $bestPlatform,
            'worst_performing_platform' => $worstPlatform,
            'trend' => $trend,
            'per_platform' => $metrics,
        ];
    }

    /**
     * Calculate engagement breakdown per platform.
     */
    protected function calculatePlatformBreakdown($postAnalytics): array
    {
        $grouped = $postAnalytics->groupBy('platform');
        $breakdown = [];

        foreach ($grouped as $platform => $analytics) {
            $breakdown[$platform] = [
                'posts_count' => $analytics->count(),
                'total_likes' => $analytics->sum('likes'),
                'total_comments' => $analytics->sum('comments'),
                'total_shares' => $analytics->sum('shares'),
                'total_saves' => $analytics->sum('saves'),
                'total_reach' => $analytics->sum('reach'),
                'total_impressions' => $analytics->sum('impressions'),
                'avg_engagement_rate' => $analytics->avg('engagement_rate')
                    ? round((float) $analytics->avg('engagement_rate'), 4)
                    : 0,
                'total_engagement' => $analytics->sum(fn ($a) => $a->getTotalEngagement()),
            ];
        }

        return $breakdown;
    }

    /**
     * Build a fallback summary when AI is unavailable.
     */
    protected function buildFallbackSummary(array $data): array
    {
        $totalEngagement = $data['engagement']['current_total'] ?? 0;
        $changePercent = $data['engagement']['change_percent'] ?? null;

        $trendText = 'stable';
        if ($changePercent !== null) {
            if ($changePercent > 0) {
                $trendText = "up {$changePercent}%";
            } elseif ($changePercent < 0) {
                $trendText = "down " . abs($changePercent) . "%";
            }
        }

        return [
            'headline' => "Weekly report: {$data['total_posts']} posts published, engagement {$trendText}",
            'overview' => "This week {$data['total_posts']} posts were published with a total engagement of {$totalEngagement}.",
            'highlights' => array_filter([
                $data['total_posts'] > 0 ? "{$data['total_posts']} posts published this period" : null,
                $changePercent > 0 ? "Engagement increased by {$changePercent}%" : null,
                !empty($data['top_posts']) ? "Top post received " . ($data['top_posts'][0]['total_engagement'] ?? 0) . " total engagements" : null,
            ]),
            'concerns' => array_filter([
                $changePercent !== null && $changePercent < 0 ? "Engagement decreased by " . abs($changePercent) . "%" : null,
                $data['total_posts'] === 0 ? "No posts were published this period" : null,
            ]),
        ];
    }
}
