<?php

namespace App\Jobs;

use App\Models\RssFeed;
use App\Services\RssFeedService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchRssFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [30, 60];

    public function __construct(
        protected RssFeed $feed,
        protected int $sinceDays = 1,
    ) {}

    protected function taskType(): string { return 'rss_fetch'; }
    protected function taskUserId(): int { return $this->feed->brand->user_id; }
    protected function taskModelId(): string|int { return $this->feed->id; }

    public function handle(RssFeedService $service): void
    {
        if (!$this->feed->isActive()) {
            Log::info('RSS feed not active, skipping', [
                'feed_id' => $this->feed->id,
            ]);
            return;
        }

        $this->broadcastTaskStarted();

        try {
            $count = $service->fetchArticles($this->feed, $this->sinceDays);

            Log::info('RSS feed job completed', [
                'feed_id' => $this->feed->id,
                'articles_fetched' => $count,
            ]);

            $this->broadcastTaskCompleted(true);
        } catch (\Exception $e) {
            $this->feed->markError($e->getMessage());

            Log::error('RSS feed job failed', [
                'feed_id' => $this->feed->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

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
