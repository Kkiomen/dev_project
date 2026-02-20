<?php

namespace App\Jobs\Apify;

use App\Models\Brand;
use App\Services\Apify\ApifyService;
use App\Services\Apify\CompetitorScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CiScrapeCompetitorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        protected Brand $brand,
        protected string $type = 'profiles',
    ) {}

    public function handle(CompetitorScraperService $scraperService, ApifyService $apifyService): void
    {
        Log::info('[CiScrapeCompetitorsJob] Starting', [
            'brand_id' => $this->brand->id,
            'type' => $this->type,
        ]);

        if ($apifyService->isBudgetExceeded($this->brand)) {
            Log::warning('[CiScrapeCompetitorsJob] Budget exceeded, skipping', [
                'brand_id' => $this->brand->id,
            ]);
            return;
        }

        try {
            $runs = match ($this->type) {
                'profiles' => $scraperService->scrapeProfiles($this->brand),
                'posts' => $scraperService->scrapePosts($this->brand),
                default => [],
            };

            foreach ($runs as $run) {
                CiProcessScrapeResultsJob::dispatch($this->brand, $run)
                    ->delay(now()->addMinutes(3));
            }

            Log::info('[CiScrapeCompetitorsJob] Completed, dispatched processing jobs', [
                'brand_id' => $this->brand->id,
                'runs' => count($runs),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CiScrapeCompetitorsJob] Failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[CiScrapeCompetitorsJob] Failed permanently', [
            'brand_id' => $this->brand->id,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
