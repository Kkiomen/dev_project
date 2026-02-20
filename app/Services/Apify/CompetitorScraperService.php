<?php

namespace App\Services\Apify;

use App\Enums\ApifyActorType;
use App\Models\Brand;
use App\Models\CiCompetitor;
use App\Models\CiCompetitorAccount;
use App\Models\CiCompetitorPost;
use Illuminate\Support\Facades\Log;

class CompetitorScraperService
{
    public function __construct(
        protected ApifyService $apifyService,
    ) {}

    public function scrapeProfiles(Brand $brand): array
    {
        $accounts = $this->getAccountsNeedingRefresh($brand, 'profile');

        if ($accounts->isEmpty()) {
            Log::info('[CompetitorScraperService] No profiles need refresh', ['brand_id' => $brand->id]);
            return [];
        }

        $scrapeRuns = [];

        $grouped = $accounts->groupBy('platform');
        foreach ($grouped as $platform => $platformAccounts) {
            $actorType = ApifyActorType::profileScraperForPlatform($platform);
            if (!$actorType) {
                continue;
            }

            $input = $this->buildProfileInput($actorType, $platformAccounts);

            try {
                $scrapeRun = $this->apifyService->startRun($brand, $actorType, $input);
                $scrapeRuns[] = $scrapeRun;

                Log::info('[CompetitorScraperService] Profile scrape started', [
                    'platform' => $platform,
                    'accounts' => $platformAccounts->count(),
                    'run_id' => $scrapeRun->id,
                ]);
            } catch (\Exception $e) {
                Log::error('[CompetitorScraperService] Profile scrape failed', [
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $scrapeRuns;
    }

    public function scrapePosts(Brand $brand, ?string $platform = null, int $postsPerCompetitor = 12): array
    {
        $query = CiCompetitorAccount::whereHas('competitor', fn ($q) => $q->forBrand($brand->id)->active());

        if ($platform) {
            $query->forPlatform($platform);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $scrapeRuns = [];

        $grouped = $accounts->groupBy('platform');
        foreach ($grouped as $plat => $platformAccounts) {
            $actorType = ApifyActorType::postScraperForPlatform($plat);
            if (!$actorType) {
                continue;
            }

            $input = $this->buildPostInput($actorType, $platformAccounts, $postsPerCompetitor);

            try {
                $scrapeRun = $this->apifyService->startRun($brand, $actorType, $input);
                $scrapeRuns[] = $scrapeRun;

                Log::info('[CompetitorScraperService] Post scrape started', [
                    'platform' => $plat,
                    'accounts' => $platformAccounts->count(),
                    'posts_per_account' => $postsPerCompetitor,
                ]);
            } catch (\Exception $e) {
                Log::error('[CompetitorScraperService] Post scrape failed', [
                    'platform' => $plat,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $scrapeRuns;
    }

    public function processProfileResults(CiCompetitorAccount $account, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $item = $items[0];
        $profileData = $this->normalizeProfile($account->platform, $item);

        $account->update([
            'profile_data' => $profileData,
            'external_id' => $profileData['external_id'] ?? $account->external_id,
            'last_scraped_at' => now(),
        ]);

        Log::info('[CompetitorScraperService] Profile processed', [
            'account_id' => $account->id,
            'followers' => $profileData['followers_count'] ?? null,
        ]);
    }

    public function processPostResults(CiCompetitorAccount $account, array $items): int
    {
        $created = 0;

        foreach ($items as $item) {
            $postData = $this->normalizePost($account->platform, $item);

            if (!$postData || !$postData['external_post_id']) {
                continue;
            }

            CiCompetitorPost::updateOrCreate(
                [
                    'ci_competitor_account_id' => $account->id,
                    'external_post_id' => $postData['external_post_id'],
                ],
                [
                    'brand_id' => $account->competitor->brand_id,
                    'ci_competitor_id' => $account->ci_competitor_id,
                    'platform' => $account->platform,
                    'post_type' => $postData['post_type'],
                    'caption' => $postData['caption'],
                    'hashtags' => $postData['hashtags'],
                    'post_url' => $postData['post_url'],
                    'posted_at' => $postData['posted_at'],
                    'likes' => $postData['likes'],
                    'comments' => $postData['comments'],
                    'shares' => $postData['shares'],
                    'saves' => $postData['saves'],
                    'views' => $postData['views'],
                    'engagement_rate' => $postData['engagement_rate'],
                    'raw_data' => $item,
                ]
            );
            $created++;
        }

        $account->update(['last_scraped_at' => now()]);

        Log::info('[CompetitorScraperService] Posts processed', [
            'account_id' => $account->id,
            'posts_upserted' => $created,
        ]);

        return $created;
    }

    public function normalizePost(string $platform, array $rawItem): ?array
    {
        return match ($platform) {
            'instagram' => $this->normalizeInstagramPost($rawItem),
            'tiktok' => $this->normalizeTiktokPost($rawItem),
            'linkedin' => $this->normalizeLinkedinPost($rawItem),
            'youtube' => $this->normalizeYoutubePost($rawItem),
            'twitter' => $this->normalizeTwitterPost($rawItem),
            default => null,
        };
    }

    protected function getAccountsNeedingRefresh(Brand $brand, string $type): \Illuminate\Database\Eloquent\Collection
    {
        return CiCompetitorAccount::whereHas('competitor', fn ($q) => $q->forBrand($brand->id)->active())
            ->needsRefresh(7)
            ->get();
    }

    protected function buildProfileInput(ApifyActorType $actorType, $accounts): array
    {
        $handles = $accounts->pluck('handle')->toArray();

        return match ($actorType->platform()) {
            'instagram' => ['usernames' => $handles, 'resultsLimit' => 1],
            'tiktok' => ['profiles' => $handles, 'resultsPerPage' => 1],
            'linkedin' => ['profileUrls' => array_map(fn ($h) => "https://www.linkedin.com/in/{$h}/", $handles)],
            'youtube' => ['startUrls' => array_map(fn ($h) => ['url' => "https://www.youtube.com/@{$h}"], $handles)],
            'twitter' => ['twitterHandles' => $handles, 'maxItems' => 1],
            default => ['usernames' => $handles],
        };
    }

    protected function buildPostInput(ApifyActorType $actorType, $accounts, int $postsPerAccount): array
    {
        $handles = $accounts->pluck('handle')->toArray();

        return match ($actorType->platform()) {
            'instagram' => ['username' => $handles, 'resultsLimit' => $postsPerAccount],
            'tiktok' => ['profiles' => array_map(fn ($h) => "@{$h}", $handles), 'resultsPerPage' => $postsPerAccount],
            'linkedin' => ['profileUrls' => array_map(fn ($h) => "https://www.linkedin.com/in/{$h}/recent-activity/all/", $handles), 'maxPosts' => $postsPerAccount],
            'youtube' => ['startUrls' => array_map(fn ($h) => ['url' => "https://www.youtube.com/@{$h}/videos"], $handles), 'maxResults' => $postsPerAccount],
            'twitter' => ['twitterHandles' => $handles, 'maxItems' => $postsPerAccount],
            default => ['usernames' => $handles, 'maxItems' => $postsPerAccount],
        };
    }

    protected function normalizeProfile(string $platform, array $raw): array
    {
        return match ($platform) {
            'instagram' => [
                'external_id' => $raw['id'] ?? null,
                'username' => $raw['username'] ?? null,
                'full_name' => $raw['fullName'] ?? null,
                'bio' => $raw['biography'] ?? null,
                'followers_count' => $raw['followersCount'] ?? 0,
                'following_count' => $raw['followsCount'] ?? 0,
                'posts_count' => $raw['postsCount'] ?? 0,
                'profile_pic_url' => $raw['profilePicUrl'] ?? null,
                'is_verified' => $raw['verified'] ?? false,
            ],
            'tiktok' => [
                'external_id' => $raw['id'] ?? null,
                'username' => $raw['uniqueId'] ?? null,
                'full_name' => $raw['nickname'] ?? null,
                'bio' => $raw['signature'] ?? null,
                'followers_count' => $raw['fans'] ?? $raw['followerCount'] ?? 0,
                'following_count' => $raw['following'] ?? $raw['followingCount'] ?? 0,
                'posts_count' => $raw['video'] ?? $raw['videoCount'] ?? 0,
                'profile_pic_url' => $raw['avatarLarger'] ?? null,
                'is_verified' => $raw['verified'] ?? false,
            ],
            default => [
                'external_id' => $raw['id'] ?? null,
                'username' => $raw['username'] ?? $raw['handle'] ?? null,
                'full_name' => $raw['name'] ?? $raw['fullName'] ?? null,
                'bio' => $raw['bio'] ?? $raw['description'] ?? null,
                'followers_count' => $raw['followersCount'] ?? $raw['followers'] ?? 0,
                'following_count' => $raw['followingCount'] ?? $raw['following'] ?? 0,
                'posts_count' => $raw['postsCount'] ?? 0,
            ],
        };
    }

    protected function normalizeInstagramPost(array $raw): array
    {
        $followers = $raw['ownerFollowerCount'] ?? 0;
        $engagement = ($raw['likesCount'] ?? 0) + ($raw['commentsCount'] ?? 0);
        $engagementRate = $followers > 0 ? min(($engagement / $followers) * 100, 9999) : 0;

        return [
            'external_post_id' => $raw['id'] ?? $raw['shortCode'] ?? null,
            'post_type' => $raw['type'] ?? 'post',
            'caption' => $raw['caption'] ?? null,
            'hashtags' => $this->extractHashtags($raw['caption'] ?? ''),
            'post_url' => $raw['url'] ?? null,
            'posted_at' => isset($raw['timestamp']) ? date('Y-m-d H:i:s', strtotime($raw['timestamp'])) : null,
            'likes' => $raw['likesCount'] ?? 0,
            'comments' => $raw['commentsCount'] ?? 0,
            'shares' => 0,
            'saves' => 0,
            'views' => $raw['videoViewCount'] ?? 0,
            'engagement_rate' => round($engagementRate, 4),
        ];
    }

    protected function normalizeTiktokPost(array $raw): array
    {
        $followers = $raw['authorMeta']['fans'] ?? 0;
        $engagement = ($raw['diggCount'] ?? 0) + ($raw['commentCount'] ?? 0) + ($raw['shareCount'] ?? 0);
        $engagementRate = $followers > 0 ? min(($engagement / $followers) * 100, 9999) : 0;

        return [
            'external_post_id' => $raw['id'] ?? null,
            'post_type' => 'video',
            'caption' => $raw['text'] ?? null,
            'hashtags' => array_map(fn ($h) => $h['name'] ?? $h, $raw['hashtags'] ?? []),
            'post_url' => $raw['webVideoUrl'] ?? null,
            'posted_at' => isset($raw['createTime']) ? date('Y-m-d H:i:s', $raw['createTime']) : null,
            'likes' => $raw['diggCount'] ?? 0,
            'comments' => $raw['commentCount'] ?? 0,
            'shares' => $raw['shareCount'] ?? 0,
            'saves' => $raw['collectCount'] ?? 0,
            'views' => $raw['playCount'] ?? 0,
            'engagement_rate' => round($engagementRate, 4),
        ];
    }

    protected function normalizeLinkedinPost(array $raw): array
    {
        $engagement = ($raw['numLikes'] ?? 0) + ($raw['numComments'] ?? 0) + ($raw['numShares'] ?? 0);

        return [
            'external_post_id' => $raw['urn'] ?? $raw['id'] ?? null,
            'post_type' => $raw['type'] ?? 'post',
            'caption' => $raw['text'] ?? null,
            'hashtags' => $this->extractHashtags($raw['text'] ?? ''),
            'post_url' => $raw['url'] ?? null,
            'posted_at' => $raw['postedAt'] ?? null,
            'likes' => $raw['numLikes'] ?? 0,
            'comments' => $raw['numComments'] ?? 0,
            'shares' => $raw['numShares'] ?? 0,
            'saves' => 0,
            'views' => $raw['numImpressions'] ?? 0,
            'engagement_rate' => 0,
        ];
    }

    protected function normalizeYoutubePost(array $raw): array
    {
        $views = $raw['viewCount'] ?? 0;
        $engagement = ($raw['likes'] ?? 0) + ($raw['commentsCount'] ?? 0);
        $engagementRate = $views > 0 ? min(($engagement / $views) * 100, 9999) : 0;

        return [
            'external_post_id' => $raw['id'] ?? null,
            'post_type' => 'video',
            'caption' => $raw['title'] ?? null,
            'hashtags' => $raw['tags'] ?? [],
            'post_url' => $raw['url'] ?? null,
            'posted_at' => $raw['date'] ?? $raw['uploadDate'] ?? null,
            'likes' => $raw['likes'] ?? 0,
            'comments' => $raw['commentsCount'] ?? 0,
            'shares' => 0,
            'saves' => 0,
            'views' => $raw['viewCount'] ?? 0,
            'engagement_rate' => round($engagementRate, 4),
        ];
    }

    protected function normalizeTwitterPost(array $raw): array
    {
        $engagement = ($raw['likeCount'] ?? 0) + ($raw['replyCount'] ?? 0) + ($raw['retweetCount'] ?? 0);

        return [
            'external_post_id' => $raw['id'] ?? null,
            'post_type' => $raw['type'] ?? 'tweet',
            'caption' => $raw['text'] ?? $raw['full_text'] ?? null,
            'hashtags' => array_map(fn ($h) => $h['text'] ?? $h, $raw['entities']['hashtags'] ?? []),
            'post_url' => $raw['url'] ?? null,
            'posted_at' => $raw['createdAt'] ?? null,
            'likes' => $raw['likeCount'] ?? 0,
            'comments' => $raw['replyCount'] ?? 0,
            'shares' => $raw['retweetCount'] ?? 0,
            'saves' => $raw['bookmarkCount'] ?? 0,
            'views' => $raw['viewCount'] ?? 0,
            'engagement_rate' => 0,
        ];
    }

    protected function extractHashtags(string $text): array
    {
        preg_match_all('/#(\w+)/u', $text, $matches);
        return $matches[1] ?? [];
    }
}
