<?php

namespace App\Services;

use App\Enums\RssFeedStatus;
use App\Jobs\FetchRssFeedJob;
use App\Models\Brand;
use App\Models\RssArticle;
use App\Models\RssFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laminas\Feed\Reader\Reader as FeedReader;

class RssFeedService
{
    public function addFeed(Brand $brand, string $url, ?string $name = null): RssFeed
    {
        $urlHash = hash('sha256', $url);
        $existing = $brand->rssFeeds()->where('url_hash', $urlHash)->first();

        if ($existing) {
            throw new \InvalidArgumentException('This feed URL has already been added to this brand.');
        }

        $response = Http::timeout(15)
            ->retry(2, 500)
            ->withHeaders(['User-Agent' => 'BrandAutomation/1.0'])
            ->get($url);

        $response->throw();

        $channel = FeedReader::importString($response->body());

        $feed = $brand->rssFeeds()->create([
            'name' => $name ?: $channel->getTitle() ?: parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'site_url' => $channel->getLink(),
            'status' => RssFeedStatus::Active,
        ]);

        FetchRssFeedJob::dispatch($feed, 7);

        return $feed;
    }

    public function fetchArticles(RssFeed $feed, int $sinceDays = 1): int
    {
        $response = Http::timeout(15)
            ->retry(2, 500)
            ->withHeaders(['User-Agent' => 'BrandAutomation/1.0'])
            ->get($feed->url);

        $response->throw();

        $channel = FeedReader::importString($response->body());
        $cutoff = now()->subDays($sinceDays);
        $count = 0;

        foreach ($channel as $entry) {
            $publishedAt = $entry->getDateModified() ?? $entry->getDateCreated();

            if ($publishedAt && $publishedAt < $cutoff) {
                continue;
            }

            $guid = $entry->getId() ?: $entry->getLink() ?: md5($entry->getTitle());

            $guidHash = hash('sha256', $guid);

            RssArticle::firstOrCreate(
                [
                    'rss_feed_id' => $feed->id,
                    'guid_hash' => $guidHash,
                ],
                [
                    'brand_id' => $feed->brand_id,
                    'guid' => $guid,
                    'title' => mb_substr($entry->getTitle() ?? '', 0, 1024),
                    'description' => $entry->getDescription(),
                    'url' => $entry->getLink() ?? '',
                    'author' => $this->extractAuthor($entry),
                    'image_url' => $this->extractImageUrl($entry),
                    'categories' => $this->extractCategories($entry),
                    'published_at' => $publishedAt,
                ]
            );

            $count++;
        }

        $feed->markFetched();

        Log::info('RSS feed fetched', [
            'feed_id' => $feed->id,
            'new_articles' => $count,
        ]);

        return $count;
    }

    public function cleanupOldArticles(int $retentionDays = 30): int
    {
        return RssArticle::where('published_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    private function extractAuthor($entry): ?string
    {
        $author = $entry->getAuthor();

        if (is_array($author)) {
            return $author['name'] ?? null;
        }

        return null;
    }

    private function extractImageUrl($entry): ?string
    {
        $enclosure = $entry->getEnclosure();

        if ($enclosure && str_starts_with($enclosure->type ?? '', 'image/')) {
            return $enclosure->url ?? null;
        }

        return null;
    }

    private function extractCategories($entry): ?array
    {
        $categories = $entry->getCategories();

        if (!$categories || $categories->count() === 0) {
            return null;
        }

        $result = [];
        foreach ($categories as $category) {
            $label = $category['label'] ?? $category['term'] ?? null;
            if ($label) {
                $result[] = $label;
            }
        }

        return $result ?: null;
    }
}
