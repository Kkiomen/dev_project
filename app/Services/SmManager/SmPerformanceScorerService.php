<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Enums\ApiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmPerformanceScore;
use App\Models\SmPostAnalytics;
use App\Models\SocialPost;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmPerformanceScorerService
{
    use LogsApiUsage;

    protected string $model;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4o');
    }

    /**
     * Score a single post's performance using AI analysis.
     *
     * 1. Gather post analytics (likes, comments, shares, reach)
     * 2. Calculate brand averages for comparison
     * 3. Ask AI for a 0-100 score with analysis and recommendations
     * 4. Persist SmPerformanceScore record
     */
    public function scorePost(SocialPost $post): array
    {
        $this->resetRequestId();

        $brand = $post->brand;

        if (!$brand) {
            return [
                'success' => false,
                'error' => 'Post has no associated brand.',
            ];
        }

        // 1. Get post analytics
        $analytics = SmPostAnalytics::where('social_post_id', $post->id)
            ->latest('collected_at')
            ->first();

        if (!$analytics) {
            return [
                'success' => false,
                'error' => 'No analytics data available for this post.',
            ];
        }

        // 2. Get API key
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return [
                'success' => false,
                'error_code' => 'no_api_key',
                'error' => 'No OpenAI API key configured for this brand.',
            ];
        }

        // 3. Calculate brand averages for context
        $brandAverages = $this->calculateBrandAverages($brand, $analytics->platform);

        // 4. Build prompt and call AI
        $prompt = $this->buildScoringPrompt($post, $this->formatAnalytics($analytics), $brandAverages);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_performance_score', [
            'social_post_id' => $post->id,
            'platform' => $analytics->platform,
        ], $this->model);

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a social media analytics expert. Analyze post performance and provide scoring. Always respond with valid JSON only, no markdown.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
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

            if (!$parsed || !isset($parsed['score'])) {
                Log::warning('SmPerformanceScorer: Failed to parse AI response', [
                    'social_post_id' => $post->id,
                    'raw' => $rawContent,
                ]);

                return [
                    'success' => false,
                    'error' => 'AI returned an invalid scoring response.',
                ];
            }

            $score = max(0, min(100, (int) $parsed['score']));

            $analysis = $parsed['analysis'] ?? [
                'strengths' => [],
                'weaknesses' => [],
            ];

            $recommendations = $parsed['recommendations'] ?? '';

            // 5. Persist the score
            $performanceScore = SmPerformanceScore::create([
                'social_post_id' => $post->id,
                'score' => $score,
                'analysis' => $analysis,
                'recommendations' => $recommendations,
                'ai_model' => $this->model,
            ]);

            Log::info('SmPerformanceScorer: Post scored', [
                'social_post_id' => $post->id,
                'score' => $score,
                'label' => $performanceScore->getScoreLabel(),
            ]);

            return [
                'success' => true,
                'score' => $score,
                'label' => $performanceScore->getScoreLabel(),
                'analysis' => $analysis,
                'recommendations' => $recommendations,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmPerformanceScorer: AI scoring failed', [
                'social_post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'AI scoring failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Score multiple recent unscored posts for a brand.
     */
    public function scoreBatch(Brand $brand, int $limit = 10): array
    {
        // Find posts that have analytics but no performance score yet
        $unscoredPosts = SocialPost::where('brand_id', $brand->id)
            ->whereHas('platformPosts', function ($query) {
                $query->where('enabled', true);
            })
            ->whereDoesntHave('performanceScores')
            ->whereHas('postAnalytics')
            ->latest('published_at')
            ->limit($limit)
            ->get();

        if ($unscoredPosts->isEmpty()) {
            return [
                'success' => true,
                'scored' => 0,
                'errors' => 0,
                'message' => 'No unscored posts found.',
            ];
        }

        $scored = 0;
        $errors = 0;

        foreach ($unscoredPosts as $post) {
            $result = $this->scorePost($post);

            if ($result['success']) {
                $scored++;
            } else {
                $errors++;

                // Stop batch if we hit an API key issue
                if (isset($result['error_code']) && $result['error_code'] === 'no_api_key') {
                    Log::warning('SmPerformanceScorer: Batch stopped - no API key', [
                        'brand_id' => $brand->id,
                    ]);

                    return [
                        'success' => false,
                        'error_code' => 'no_api_key',
                        'scored' => $scored,
                        'errors' => $errors,
                    ];
                }
            }
        }

        Log::info('SmPerformanceScorer: Batch scoring complete', [
            'brand_id' => $brand->id,
            'scored' => $scored,
            'errors' => $errors,
        ]);

        return [
            'success' => true,
            'scored' => $scored,
            'errors' => $errors,
        ];
    }

    /**
     * Build the AI prompt for scoring a post.
     */
    protected function buildScoringPrompt(SocialPost $post, array $analytics, array $brandAverages): string
    {
        $postContent = $post->main_caption ?? $post->title ?? '(no content)';
        $platform = $analytics['platform'] ?? 'unknown';

        $analyticsJson = json_encode($analytics, JSON_PRETTY_PRINT);
        $averagesJson = json_encode($brandAverages, JSON_PRETTY_PRINT);

        return <<<PROMPT
Analyze this social media post's performance and provide a score from 0 to 100.

## Post Information
- Platform: {$platform}
- Content: {$postContent}

## Post Metrics
{$analyticsJson}

## Brand Average Metrics (for comparison)
{$averagesJson}

## Scoring Criteria
- 90-100: Viral/exceptional (significantly above brand average)
- 70-89: Excellent (above brand average)
- 50-69: Good (at or near brand average)
- 30-49: Below average (below brand average)
- 0-29: Poor (significantly below brand average)

Consider: engagement rate relative to averages, reach vs impressions ratio, comment-to-like ratio, shares/saves as high-value engagements.

Respond with JSON:
{
  "score": <0-100>,
  "analysis": {
    "strengths": ["strength 1", "strength 2"],
    "weaknesses": ["weakness 1", "weakness 2"]
  },
  "recommendations": "Actionable advice for improving future posts..."
}
PROMPT;
    }

    /**
     * Calculate brand-wide average metrics for a given platform.
     */
    protected function calculateBrandAverages(Brand $brand, string $platform): array
    {
        $averages = SmPostAnalytics::whereHas('socialPost', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('platform', $platform)
            ->selectRaw('
                AVG(likes) as avg_likes,
                AVG(comments) as avg_comments,
                AVG(shares) as avg_shares,
                AVG(saves) as avg_saves,
                AVG(reach) as avg_reach,
                AVG(impressions) as avg_impressions,
                AVG(clicks) as avg_clicks,
                AVG(engagement_rate) as avg_engagement_rate,
                COUNT(*) as total_posts
            ')
            ->first();

        if (!$averages || $averages->total_posts === 0) {
            return [
                'avg_likes' => 0,
                'avg_comments' => 0,
                'avg_shares' => 0,
                'avg_saves' => 0,
                'avg_reach' => 0,
                'avg_impressions' => 0,
                'avg_clicks' => 0,
                'avg_engagement_rate' => 0,
                'total_posts_analyzed' => 0,
            ];
        }

        return [
            'avg_likes' => round((float) $averages->avg_likes, 1),
            'avg_comments' => round((float) $averages->avg_comments, 1),
            'avg_shares' => round((float) $averages->avg_shares, 1),
            'avg_saves' => round((float) $averages->avg_saves, 1),
            'avg_reach' => round((float) $averages->avg_reach, 1),
            'avg_impressions' => round((float) $averages->avg_impressions, 1),
            'avg_clicks' => round((float) $averages->avg_clicks, 1),
            'avg_engagement_rate' => round((float) $averages->avg_engagement_rate, 4),
            'total_posts_analyzed' => (int) $averages->total_posts,
        ];
    }

    /**
     * Format SmPostAnalytics model into an array for prompting.
     */
    protected function formatAnalytics(SmPostAnalytics $analytics): array
    {
        return [
            'platform' => $analytics->platform,
            'likes' => $analytics->likes ?? 0,
            'comments' => $analytics->comments ?? 0,
            'shares' => $analytics->shares ?? 0,
            'saves' => $analytics->saves ?? 0,
            'reach' => $analytics->reach ?? 0,
            'impressions' => $analytics->impressions ?? 0,
            'clicks' => $analytics->clicks ?? 0,
            'video_views' => $analytics->video_views ?? 0,
            'engagement_rate' => (float) ($analytics->engagement_rate ?? 0),
            'total_engagement' => $analytics->getTotalEngagement(),
            'collected_at' => $analytics->collected_at?->toDateTimeString(),
        ];
    }
}
