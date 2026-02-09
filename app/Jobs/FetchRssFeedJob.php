<?php

namespace App\Jobs;

use App\Models\RssFeed;
use App\Services\RssFeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchRssFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [30, 60];

    public function __construct(
        protected RssFeed $feed,
        protected int $sinceDays = 1,
    ) {}

    public function handle(RssFeedService $service): void
    {
        if (!$this->feed->isActive()) {
            Log::info('RSS feed not active, skipping', [
                'feed_id' => $this->feed->id,
            ]);
            return;
        }

        try {
            $count = $service->fetchArticles($this->feed, $this->sinceDays);

            Log::info('RSS feed job completed', [
                'feed_id' => $this->feed->id,
                'articles_fetched' => $count,
            ]);
        } catch (\Exception $e) {
            $this->feed->markError($e->getMessage());

            Log::error('RSS feed job failed', [
                'feed_id' => $this->feed->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->feed->markError($exception->getMessage());

        Log::error('RSS feed job failed permanently', [
            'feed_id' => $this->feed->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
