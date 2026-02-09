<?php

namespace App\Console\Commands;

use App\Jobs\FetchRssFeedJob;
use App\Models\RssFeed;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class FetchAllRssFeedsCommand extends Command
{
    protected $signature = 'rss:fetch {--feed= : Fetch specific feed by ID} {--cleanup : Clean up old articles}';

    protected $description = 'Fetch articles from RSS feeds';

    public function handle(RssFeedService $service): int
    {
        if ($this->option('cleanup')) {
            $deleted = $service->cleanupOldArticles();
            $this->info("Cleaned up {$deleted} old articles.");
        }

        if ($feedId = $this->option('feed')) {
            $feed = RssFeed::findByPublicIdOrFail($feedId);
            FetchRssFeedJob::dispatch($feed);
            $this->info("Dispatched fetch job for feed: {$feed->name}");

            return self::SUCCESS;
        }

        $feeds = RssFeed::dueForFetch()->get();

        if ($feeds->isEmpty()) {
            $this->info('No feeds due for fetching.');
            return self::SUCCESS;
        }

        foreach ($feeds as $feed) {
            FetchRssFeedJob::dispatch($feed);
        }

        $this->info("Dispatched fetch jobs for {$feeds->count()} feeds.");

        return self::SUCCESS;
    }
}
