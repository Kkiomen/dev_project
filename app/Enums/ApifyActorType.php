<?php

namespace App\Enums;

enum ApifyActorType: string
{
    // Instagram
    case InstagramProfile = 'instagram_profile';
    case InstagramPosts = 'instagram_posts';
    case InstagramHashtag = 'instagram_hashtag';
    case InstagramReels = 'instagram_reels';

    // TikTok
    case TiktokProfile = 'tiktok_profile';
    case TiktokPosts = 'tiktok_posts';
    case TiktokHashtag = 'tiktok_hashtag';

    // LinkedIn
    case LinkedinProfile = 'linkedin_profile';
    case LinkedinPosts = 'linkedin_posts';

    // YouTube
    case YoutubeChannel = 'youtube_channel';
    case YoutubeVideos = 'youtube_videos';

    // Twitter/X
    case TwitterProfile = 'twitter_profile';
    case TwitterPosts = 'twitter_posts';

    // Cross-platform
    case GoogleTrends = 'google_trends';
    case WebScraper = 'web_scraper';

    public function actorId(): string
    {
        return match ($this) {
            self::InstagramProfile => 'apify~instagram-profile-scraper',
            self::InstagramPosts => 'apify~instagram-post-scraper',
            self::InstagramHashtag => 'apify~instagram-hashtag-scraper',
            self::InstagramReels => 'apify~instagram-reel-scraper',
            self::TiktokProfile => 'clockworks~tiktok-profile-scraper',
            self::TiktokPosts => 'clockworks~tiktok-scraper',
            self::TiktokHashtag => 'clockworks~tiktok-hashtag-scraper',
            self::LinkedinProfile => 'dev_fusion~Linkedin-Profile-Scraper',
            self::LinkedinPosts => 'harvestapi~linkedin-profile-posts',
            self::YoutubeChannel => 'streamers~youtube-channel-scraper',
            self::YoutubeVideos => 'bernardo~youtube-scraper',
            self::TwitterProfile => 'apidojo~twitter-user-scraper',
            self::TwitterPosts => 'apidojo~tweet-scraper',
            self::GoogleTrends => 'emastra~google-trends-scraper',
            self::WebScraper => 'apify~web-scraper',
        };
    }

    public function platform(): ?string
    {
        return match ($this) {
            self::InstagramProfile, self::InstagramPosts, self::InstagramHashtag, self::InstagramReels => 'instagram',
            self::TiktokProfile, self::TiktokPosts, self::TiktokHashtag => 'tiktok',
            self::LinkedinProfile, self::LinkedinPosts => 'linkedin',
            self::YoutubeChannel, self::YoutubeVideos => 'youtube',
            self::TwitterProfile, self::TwitterPosts => 'twitter',
            self::GoogleTrends, self::WebScraper => null,
        };
    }

    public function estimatedCostPerResult(): float
    {
        return match ($this) {
            self::InstagramProfile, self::TiktokProfile, self::LinkedinProfile, self::YoutubeChannel, self::TwitterProfile => 0.005,
            self::InstagramPosts, self::TiktokPosts, self::LinkedinPosts, self::YoutubeVideos, self::TwitterPosts => 0.003,
            self::InstagramHashtag, self::TiktokHashtag => 0.002,
            self::InstagramReels => 0.004,
            self::GoogleTrends => 0.001,
            self::WebScraper => 0.010,
        };
    }

    public function isProfileScraper(): bool
    {
        return in_array($this, [
            self::InstagramProfile,
            self::TiktokProfile,
            self::LinkedinProfile,
            self::YoutubeChannel,
            self::TwitterProfile,
        ]);
    }

    public function isPostScraper(): bool
    {
        return in_array($this, [
            self::InstagramPosts,
            self::TiktokPosts,
            self::LinkedinPosts,
            self::YoutubeVideos,
            self::TwitterPosts,
            self::InstagramReels,
        ]);
    }

    public static function forPlatform(string $platform): array
    {
        return array_filter(self::cases(), fn (self $case) => $case->platform() === $platform);
    }

    public static function profileScraperForPlatform(string $platform): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->platform() === $platform && $case->isProfileScraper()) {
                return $case;
            }
        }
        return null;
    }

    public static function postScraperForPlatform(string $platform): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->platform() === $platform && $case->isPostScraper()) {
                return $case;
            }
        }
        return null;
    }
}
