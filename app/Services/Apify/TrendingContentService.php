<?php

namespace App\Services\Apify;

use App\Enums\ApifyActorType;
use App\Models\Brand;
use App\Models\CiTrendingTopic;
use Illuminate\Support\Facades\Log;

class TrendingContentService
{
    public function __construct(
        protected ApifyService $apifyService,
    ) {}

    public function scrapeNicheHashtags(Brand $brand, string $platform = 'instagram'): array
    {
        $keywords = $this->getSeedKeywords($brand);

        if (empty($keywords)) {
            Log::info('[TrendingContentService] No seed keywords found', ['brand_id' => $brand->id]);
            return [];
        }

        $actorType = match ($platform) {
            'instagram' => ApifyActorType::InstagramHashtag,
            'tiktok' => ApifyActorType::TiktokHashtag,
            default => null,
        };

        if (!$actorType) {
            return [];
        }

        $input = match ($platform) {
            'instagram' => ['hashtags' => $keywords, 'resultsLimit' => 30],
            'tiktok' => ['hashtags' => $keywords, 'resultsPerPage' => 30],
            default => ['keywords' => $keywords],
        };

        try {
            $scrapeRun = $this->apifyService->startRun($brand, $actorType, $input);

            Log::info('[TrendingContentService] Hashtag scrape started', [
                'platform' => $platform,
                'keywords' => count($keywords),
                'run_id' => $scrapeRun->id,
            ]);

            return [$scrapeRun];
        } catch (\Exception $e) {
            Log::error('[TrendingContentService] Hashtag scrape failed', [
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function scrapeGoogleTrends(Brand $brand): array
    {
        $keywords = $this->getSeedKeywords($brand);

        if (empty($keywords)) {
            return [];
        }

        $input = [
            'searchTerms' => $keywords,
            'timeRange' => 'past30Days',
            'geo' => $this->getBrandGeo($brand),
            'isMultiple' => true,
        ];

        try {
            $scrapeRun = $this->apifyService->startRun($brand, ApifyActorType::GoogleTrends, $input);

            Log::info('[TrendingContentService] Google Trends scrape started', [
                'keywords' => count($keywords),
                'run_id' => $scrapeRun->id,
            ]);

            return [$scrapeRun];
        } catch (\Exception $e) {
            Log::error('[TrendingContentService] Google Trends scrape failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function processHashtagResults(Brand $brand, string $platform, array $items): int
    {
        $created = 0;

        foreach ($items as $item) {
            $topic = $this->normalizeHashtagResult($platform, $item);
            if (!$topic) {
                continue;
            }

            CiTrendingTopic::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'platform' => $platform,
                    'topic' => $topic['topic'],
                    'valid_from' => now()->toDateString(),
                ],
                [
                    'source' => "apify_{$platform}_hashtag",
                    'category' => $topic['category'],
                    'volume' => $topic['volume'],
                    'growth_rate' => $topic['growth_rate'],
                    'trend_direction' => $topic['trend_direction'],
                    'related_hashtags' => $topic['related_hashtags'],
                    'valid_until' => now()->addDays(7)->toDateString(),
                ]
            );
            $created++;
        }

        Log::info('[TrendingContentService] Hashtag results processed', [
            'platform' => $platform,
            'topics_upserted' => $created,
        ]);

        return $created;
    }

    public function processGoogleTrendsResults(Brand $brand, array $items): int
    {
        $created = 0;

        foreach ($items as $item) {
            $keyword = $item['keyword'] ?? $item['term'] ?? null;
            if (!$keyword) {
                continue;
            }

            $value = $item['value'] ?? $item['interest'] ?? 0;
            $previousValue = $item['previousValue'] ?? 0;
            $growthRate = $previousValue > 0 ? (($value - $previousValue) / $previousValue) * 100 : 0;

            $direction = match (true) {
                $growthRate > 50 => 'breakout',
                $growthRate > 10 => 'rising',
                $growthRate < -10 => 'declining',
                default => 'stable',
            };

            CiTrendingTopic::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'platform' => null,
                    'topic' => $keyword,
                    'valid_from' => now()->toDateString(),
                ],
                [
                    'source' => 'google_trends',
                    'category' => $this->mapToContentPillar($brand, $keyword),
                    'volume' => $value,
                    'growth_rate' => round($growthRate, 2),
                    'trend_direction' => $direction,
                    'related_hashtags' => $item['relatedQueries'] ?? [],
                    'valid_until' => now()->addDays(7)->toDateString(),
                ]
            );
            $created++;
        }

        Log::info('[TrendingContentService] Google Trends results processed', [
            'topics_upserted' => $created,
        ]);

        return $created;
    }

    public function getActiveTrends(Brand $brand, ?string $platform = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = CiTrendingTopic::forBrand($brand->id)->active();

        if ($platform) {
            $query->forPlatform($platform);
        }

        return $query->orderByDesc('growth_rate')->get();
    }

    public function getHashtagsForPrompt(Brand $brand, string $platform): array
    {
        $trends = CiTrendingTopic::forBrand($brand->id)
            ->active()
            ->where(fn ($q) => $q->where('platform', $platform)->orWhereNull('platform'))
            ->get();

        $trending = $trends->where('trend_direction', 'stable')
            ->pluck('related_hashtags')
            ->flatten()
            ->unique()
            ->take(10)
            ->values()
            ->toArray();

        $rising = $trends->whereIn('trend_direction', ['rising', 'breakout'])
            ->pluck('related_hashtags')
            ->flatten()
            ->unique()
            ->take(10)
            ->values()
            ->toArray();

        $niche = $trends->where('volume', '<', 100000)
            ->pluck('topic')
            ->take(10)
            ->values()
            ->toArray();

        return [
            'trending' => $trending,
            'niche' => $niche,
            'rising' => $rising,
        ];
    }

    protected function getSeedKeywords(Brand $brand): array
    {
        $keywords = [];

        $strategy = $brand->activeStrategy ?? null;
        if ($strategy) {
            $pillars = $strategy->content_pillars ?? [];
            foreach ($pillars as $pillar) {
                if (isset($pillar['name'])) {
                    $keywords[] = $pillar['name'];
                }
            }

            if (!empty($strategy->industry)) {
                $keywords[] = $strategy->industry;
            }
        }

        if (!empty($brand->niche)) {
            $keywords[] = $brand->niche;
        }

        return array_unique(array_slice($keywords, 0, 10));
    }

    protected function getBrandGeo(Brand $brand): string
    {
        return $brand->country ?? 'PL';
    }

    protected function normalizeHashtagResult(string $platform, array $raw): ?array
    {
        $topic = $raw['hashtag'] ?? $raw['name'] ?? $raw['tag'] ?? null;
        if (!$topic) {
            return null;
        }

        $volume = $raw['mediaCount'] ?? $raw['postsCount'] ?? $raw['videoCount'] ?? 0;

        return [
            'topic' => ltrim($topic, '#'),
            'category' => null,
            'volume' => $volume,
            'growth_rate' => $raw['growthRate'] ?? 0,
            'trend_direction' => $this->inferDirection($raw),
            'related_hashtags' => $raw['relatedHashtags'] ?? $raw['relatedTags'] ?? [],
        ];
    }

    protected function inferDirection(array $raw): string
    {
        $growth = $raw['growthRate'] ?? $raw['growth'] ?? null;

        if ($growth === null) {
            return 'stable';
        }

        return match (true) {
            $growth > 50 => 'breakout',
            $growth > 10 => 'rising',
            $growth < -10 => 'declining',
            default => 'stable',
        };
    }

    protected function mapToContentPillar(Brand $brand, string $keyword): ?string
    {
        $strategy = $brand->activeStrategy ?? null;
        if (!$strategy) {
            return null;
        }

        $pillars = $strategy->content_pillars ?? [];
        $keyword = strtolower($keyword);

        foreach ($pillars as $pillar) {
            $pillarName = strtolower($pillar['name'] ?? '');
            if (str_contains($keyword, $pillarName) || str_contains($pillarName, $keyword)) {
                return $pillar['name'];
            }
        }

        return null;
    }
}
