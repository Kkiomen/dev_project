<?php

namespace App\Services\Apify;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\CiCompetitorPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorAnalysisService
{
    public function batchAnalyzePosts(Brand $brand, int $chunkSize = 20): int
    {
        $posts = CiCompetitorPost::forBrand($brand->id)
            ->unanalyzed()
            ->latest('posted_at')
            ->limit(100)
            ->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);
        if (!$apiKey) {
            Log::warning('[CompetitorAnalysisService] No OpenAI key for brand', ['brand_id' => $brand->id]);
            return 0;
        }

        $analyzed = 0;

        foreach ($posts->chunk($chunkSize) as $chunk) {
            $postsText = $chunk->map(fn ($post) => [
                'id' => $post->id,
                'platform' => $post->platform,
                'caption' => mb_substr($post->caption ?? '', 0, 500),
                'likes' => $post->likes,
                'comments' => $post->comments,
                'shares' => $post->shares,
                'views' => $post->views,
                'post_type' => $post->post_type,
            ])->toJson();

            try {
                $analysis = $this->callOpenAi($apiKey, $this->buildAnalysisPrompt($postsText));
                $decoded = json_decode($analysis, true);
                $results = $decoded['results'] ?? $decoded;

                if (!is_array($results)) {
                    continue;
                }

                foreach ($results as $result) {
                    $post = $chunk->firstWhere('id', $result['id'] ?? null);
                    if ($post) {
                        $post->update([
                            'ai_analysis' => [
                                'topic' => $result['topic'] ?? null,
                                'hook_type' => $result['hook_type'] ?? null,
                                'cta_type' => $result['cta_type'] ?? null,
                                'content_format' => $result['content_format'] ?? null,
                                'virality_score' => $result['virality_score'] ?? 0,
                                'key_takeaway' => $result['key_takeaway'] ?? null,
                            ],
                            'analyzed_at' => now(),
                        ]);
                        $analyzed++;
                    }
                }
            } catch (\Exception $e) {
                Log::error('[CompetitorAnalysisService] Batch analysis failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $analyzed;
    }

    public function calculateBenchmarks(Brand $brand): array
    {
        $posts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->get();

        if ($posts->isEmpty()) {
            return ['has_data' => false];
        }

        $overall = [
            'has_data' => true,
            'total_posts' => $posts->count(),
            'avg_likes' => round($posts->avg('likes')),
            'avg_comments' => round($posts->avg('comments')),
            'avg_shares' => round($posts->avg('shares')),
            'avg_views' => round($posts->avg('views')),
            'avg_engagement_rate' => round($posts->avg('engagement_rate'), 4),
            'median_engagement_rate' => round($this->median($posts->pluck('engagement_rate')->toArray()), 4),
        ];

        $byPlatform = [];
        foreach ($posts->groupBy('platform') as $platform => $platformPosts) {
            $byPlatform[$platform] = [
                'total_posts' => $platformPosts->count(),
                'avg_likes' => round($platformPosts->avg('likes')),
                'avg_comments' => round($platformPosts->avg('comments')),
                'avg_engagement_rate' => round($platformPosts->avg('engagement_rate'), 4),
                'top_post_engagement' => round($platformPosts->max('engagement_rate'), 4),
            ];
        }

        $byContentType = [];
        foreach ($posts->groupBy('post_type') as $type => $typePosts) {
            if (!$type) {
                continue;
            }
            $byContentType[$type] = [
                'total_posts' => $typePosts->count(),
                'avg_engagement_rate' => round($typePosts->avg('engagement_rate'), 4),
            ];
        }

        $bestTimes = $this->calculateBestPostingTimes($posts);

        return array_merge($overall, [
            'by_platform' => $byPlatform,
            'by_content_type' => $byContentType,
            'best_posting_times' => $bestTimes,
        ]);
    }

    public function identifyContentGaps(Brand $brand): array
    {
        $competitorPosts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->whereNotNull('ai_analysis')
            ->get();

        if ($competitorPosts->isEmpty()) {
            return [];
        }

        $competitorTopics = $competitorPosts
            ->pluck('ai_analysis.topic')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(20)
            ->toArray();

        return array_map(fn ($count, $topic) => [
            'topic' => $topic,
            'competitor_posts' => $count,
        ], $competitorTopics, array_keys($competitorTopics));
    }

    public function getOptimalTimingInsights(Brand $brand): array
    {
        $posts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->whereNotNull('posted_at')
            ->get();

        return $this->calculateBestPostingTimes($posts);
    }

    public function buildCompetitorContextForPrompt(Brand $brand): string
    {
        $benchmarks = $this->calculateBenchmarks($brand);
        if (!($benchmarks['has_data'] ?? false)) {
            return '';
        }

        $topPosts = CiCompetitorPost::forBrand($brand->id)
            ->recent(30)
            ->topPerforming(5)
            ->get();

        $lines = ["## Competitive Intelligence Data"];
        $lines[] = "Average engagement rate: {$benchmarks['avg_engagement_rate']}%";
        $lines[] = "Average likes: {$benchmarks['avg_likes']}, comments: {$benchmarks['avg_comments']}";

        if (!$topPosts->isEmpty()) {
            $lines[] = "\n### Top performing competitor posts (last 30 days):";
            foreach ($topPosts as $post) {
                $caption = mb_substr($post->caption ?? 'No caption', 0, 100);
                $lines[] = "- [{$post->platform}] {$caption}... (engagement: {$post->engagement_rate}%)";
            }
        }

        $hooks = $topPosts->pluck('ai_analysis.hook_type')->filter()->unique()->values();
        if ($hooks->isNotEmpty()) {
            $lines[] = "\n### Most effective hook types: " . $hooks->implode(', ');
        }

        return implode("\n", $lines);
    }

    public function discoverCompetitors(Brand $brand, array $platforms = []): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);
        if (!$apiKey) {
            throw new \Exception('no_api_key');
        }

        $context = $brand->buildAiContext();

        if (empty($context['industry']) && empty($context['description'])) {
            return [];
        }

        $prompt = $this->buildDiscoverPrompt($context, $platforms);
        $response = $this->callOpenAi($apiKey, $prompt);
        $data = json_decode($response, true);

        return $data['competitors'] ?? [];
    }

    protected function buildDiscoverPrompt(array $brandContext, array $platforms): string
    {
        $contextJson = json_encode($brandContext, JSON_UNESCAPED_UNICODE);
        $platformFilter = !empty($platforms)
            ? 'Only include accounts for these platforms: ' . implode(', ', $platforms)
            : 'Include accounts for all relevant platforms (instagram, tiktok, linkedin, youtube, twitter)';

        return <<<PROMPT
Based on the following brand profile, suggest 5-10 competitors or similar creators/brands in the same niche that this brand should track on social media.

Brand profile:
{$contextJson}

{$platformFilter}

For each competitor provide:
- name: The brand/creator name
- description: Brief description of what they do and why they're relevant (1-2 sentences)
- relevance_score: 1-10 how relevant they are as a competitor
- accounts: Array of social media accounts with { platform, handle } (handle without @)

Focus on real, well-known brands/creators in this niche. Prioritize those with active social media presence.

Respond with JSON: {"competitors": [{"name": "...", "description": "...", "relevance_score": 8, "accounts": [{"platform": "instagram", "handle": "username"}]}]}
PROMPT;
    }

    protected function calculateBestPostingTimes($posts): array
    {
        $times = [];

        foreach ($posts->groupBy('platform') as $platform => $platformPosts) {
            $hourEngagement = [];

            foreach ($platformPosts as $post) {
                if (!$post->posted_at) {
                    continue;
                }
                $dayOfWeek = $post->posted_at->dayOfWeek;
                $hour = $post->posted_at->hour;
                $key = "{$dayOfWeek}_{$hour}";
                $hourEngagement[$key][] = $post->engagement_rate;
            }

            $avgBySlot = [];
            foreach ($hourEngagement as $key => $rates) {
                $avgBySlot[$key] = array_sum($rates) / count($rates);
            }

            arsort($avgBySlot);
            $topSlots = array_slice($avgBySlot, 0, 5, true);

            $formattedSlots = [];
            foreach ($topSlots as $key => $avg) {
                [$day, $hour] = explode('_', $key);
                $dayName = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$day] ?? $day;
                $formattedSlots[] = [
                    'day' => $dayName,
                    'hour' => (int) $hour,
                    'avg_engagement' => round($avg, 4),
                ];
            }

            $times[$platform] = $formattedSlots;
        }

        return $times;
    }

    protected function callOpenAi(string $apiKey, string $prompt): string
    {
        $response = Http::timeout(60)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a social media analysis expert. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '{}');
    }

    protected function buildAnalysisPrompt(string $postsJson): string
    {
        return <<<PROMPT
Analyze these social media posts from competitors. For each post, identify:
- topic: The main topic/theme (1-3 words)
- hook_type: Type of hook used (question, statistic, story, controversy, list, how-to, quote, personal, none)
- cta_type: Type of CTA (comment, share, save, link, follow, none)
- content_format: Format (carousel, reel, static, story, text, video)
- virality_score: 1-10 score based on engagement relative to the batch
- key_takeaway: One sentence about why this post worked or didn't

Posts data:
{$postsJson}

Respond with JSON: {"results": [{"id": ..., "topic": ..., "hook_type": ..., "cta_type": ..., "content_format": ..., "virality_score": ..., "key_takeaway": ...}]}
PROMPT;
    }

    protected function median(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }

        return $values[$mid];
    }
}
