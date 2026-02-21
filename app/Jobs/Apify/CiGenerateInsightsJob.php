<?php

namespace App\Jobs\Apify;

use App\Models\Brand;
use App\Services\Apify\CompetitorAnalysisService;
use App\Services\Apify\ContentInsightsService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CiGenerateInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        protected Brand $brand,
    ) {}

    protected function taskType(): string { return 'ci_generate_insights'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->brand->id; }

    public function handle(
        CompetitorAnalysisService $analysisService,
        ContentInsightsService $insightsService,
    ): void {
        Log::info('[CiGenerateInsightsJob] Starting', ['brand_id' => $this->brand->id]);

        $this->broadcastTaskStarted();

        try {
            // First, analyze unanalyzed posts
            $analyzed = $analysisService->batchAnalyzePosts($this->brand);

            Log::info('[CiGenerateInsightsJob] Posts analyzed', [
                'brand_id' => $this->brand->id,
                'posts_analyzed' => $analyzed,
            ]);

            // Then generate insights
            $insights = $insightsService->generateInsights($this->brand);

            Log::info('[CiGenerateInsightsJob] Insights generated', [
                'brand_id' => $this->brand->id,
                'insights_count' => $insights,
            ]);

            $this->broadcastTaskCompleted(true);
        } catch (\Throwable $e) {
            Log::error('[CiGenerateInsightsJob] Failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[CiGenerateInsightsJob] Failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
