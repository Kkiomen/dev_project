<?php

namespace App\Jobs\Apify;

use App\Enums\ApifyActorType;
use App\Enums\ScrapeStatus;
use App\Models\Brand;
use App\Models\CiCompetitorAccount;
use App\Models\CiScrapeRun;
use App\Services\Apify\ApifyService;
use App\Services\Apify\CompetitorScraperService;
use App\Services\Apify\TrendingContentService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CiProcessScrapeResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 300;
    public int $tries = 5;
    public int $backoff = 60;

    protected int $maxPollingAttempts = 20;

    public function __construct(
        protected Brand $brand,
        protected CiScrapeRun $scrapeRun,
    ) {}

    protected function taskType(): string { return 'ci_process_results'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->scrapeRun->id; }

    public function handle(
        ApifyService $apifyService,
        CompetitorScraperService $scraperService,
        TrendingContentService $trendingService,
    ): void {
        $scrapeRun = $this->scrapeRun->fresh();

        if (!$scrapeRun || $scrapeRun->status->isTerminal()) {
            return;
        }

        if (!$scrapeRun->apify_run_id) {
            $scrapeRun->markAsFailed('No Apify run ID');
            return;
        }

        $this->broadcastTaskStarted();

        Log::info('[CiProcessScrapeResultsJob] Checking run status', [
            'scrape_run_id' => $scrapeRun->id,
            'apify_run_id' => $scrapeRun->apify_run_id,
        ]);

        try {
            $status = $apifyService->getRunStatus($this->brand, $scrapeRun->apify_run_id);

            if (in_array($status['status'], ['RUNNING', 'READY'])) {
                // Still running, re-queue with delay
                self::dispatch($this->brand, $scrapeRun)->delay(now()->addMinutes(1));
                return;
            }

            if ($status['status'] !== 'SUCCEEDED') {
                $scrapeRun->markAsFailed("Apify run status: {$status['status']}");
                $this->broadcastTaskCompleted(false, "Apify run status: {$status['status']}");
                return;
            }

            $results = $apifyService->getRunResults($this->brand, $scrapeRun->apify_run_id);
            $actualCost = $status['usage_usd'] ?? null;

            $this->processResults($scrapeRun, $results, $scraperService, $trendingService);

            $scrapeRun->markAsSucceeded(count($results), $actualCost);
            $apifyService->recordCost($this->brand, $scrapeRun);

            Log::info('[CiProcessScrapeResultsJob] Results processed', [
                'scrape_run_id' => $scrapeRun->id,
                'results_count' => count($results),
                'cost' => $actualCost,
            ]);

            $this->broadcastTaskCompleted(true);
        } catch (\Throwable $e) {
            $scrapeRun->markAsFailed($e->getMessage());

            Log::error('[CiProcessScrapeResultsJob] Failed', [
                'scrape_run_id' => $scrapeRun->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    protected function processResults(
        CiScrapeRun $scrapeRun,
        array $results,
        CompetitorScraperService $scraperService,
        TrendingContentService $trendingService,
    ): void {
        $actorType = $scrapeRun->actor_type;

        if ($actorType->isProfileScraper()) {
            $this->processProfileResults($scrapeRun, $results, $scraperService);
        } elseif ($actorType->isPostScraper()) {
            $this->processPostResults($scrapeRun, $results, $scraperService);
        } elseif ($actorType === ApifyActorType::GoogleTrends) {
            $trendingService->processGoogleTrendsResults($this->brand, $results);
        } elseif (in_array($actorType, [ApifyActorType::InstagramHashtag, ApifyActorType::TiktokHashtag])) {
            $trendingService->processHashtagResults($this->brand, $actorType->platform(), $results);
        }
    }

    protected function processProfileResults(CiScrapeRun $scrapeRun, array $results, CompetitorScraperService $scraperService): void
    {
        $inputParams = $scrapeRun->input_params ?? [];
        $handles = $inputParams['usernames'] ?? $inputParams['profiles'] ?? $inputParams['handles'] ?? [];

        foreach ($handles as $index => $handle) {
            $account = CiCompetitorAccount::where('handle', ltrim($handle, '@'))
                ->whereHas('competitor', fn ($q) => $q->forBrand($this->brand->id))
                ->first();

            if ($account && isset($results[$index])) {
                $scraperService->processProfileResults($account, [$results[$index]]);
            }
        }
    }

    protected function processPostResults(CiScrapeRun $scrapeRun, array $results, CompetitorScraperService $scraperService): void
    {
        $platform = $scrapeRun->actor_type->platform();

        $accounts = CiCompetitorAccount::forPlatform($platform)
            ->whereHas('competitor', fn ($q) => $q->forBrand($this->brand->id)->active())
            ->get()
            ->keyBy('handle');

        $grouped = [];
        foreach ($results as $item) {
            $username = $item['ownerUsername'] ?? $item['authorMeta']['uniqueId'] ?? $item['author'] ?? null;
            if ($username) {
                $grouped[ltrim($username, '@')][] = $item;
            }
        }

        foreach ($grouped as $handle => $items) {
            $account = $accounts->get($handle);
            if ($account) {
                $scraperService->processPostResults($account, $items);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[CiProcessScrapeResultsJob] Failed permanently', [
            'scrape_run_id' => $this->scrapeRun->id,
            'error' => $exception->getMessage(),
        ]);

        $this->scrapeRun->markAsFailed($exception->getMessage());
    }
}
