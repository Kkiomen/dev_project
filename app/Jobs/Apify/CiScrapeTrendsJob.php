<?php

namespace App\Jobs\Apify;

use App\Models\Brand;
use App\Services\Apify\ApifyService;
use App\Services\Apify\TrendingContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CiScrapeTrendsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        protected Brand $brand,
    ) {}

    public function handle(TrendingContentService $trendingService, ApifyService $apifyService): void
    {
        Log::info('[CiScrapeTrendsJob] Starting', ['brand_id' => $this->brand->id]);

        if ($apifyService->isBudgetExceeded($this->brand)) {
            Log::warning('[CiScrapeTrendsJob] Budget exceeded, skipping', [
                'brand_id' => $this->brand->id,
            ]);
            return;
        }

        try {
            $runs = [];

            $hashtagRuns = $trendingService->scrapeNicheHashtags($this->brand, 'instagram');
            $runs = array_merge($runs, $hashtagRuns);

            $tiktokRuns = $trendingService->scrapeNicheHashtags($this->brand, 'tiktok');
            $runs = array_merge($runs, $tiktokRuns);

            $trendsRuns = $trendingService->scrapeGoogleTrends($this->brand);
            $runs = array_merge($runs, $trendsRuns);

            foreach ($runs as $run) {
                CiProcessScrapeResultsJob::dispatch($this->brand, $run)
                    ->delay(now()->addMinutes(3));
            }

            Log::info('[CiScrapeTrendsJob] Completed', [
                'brand_id' => $this->brand->id,
                'runs' => count($runs),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CiScrapeTrendsJob] Failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[CiScrapeTrendsJob] Failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
