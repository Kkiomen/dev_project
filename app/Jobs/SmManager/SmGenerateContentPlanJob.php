<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Models\SmContentPlan;
use App\Models\SmStrategy;
use App\Services\SmManager\SmContentPlanGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmGenerateContentPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        protected SmContentPlan $plan,
        protected Brand $brand,
        protected SmStrategy $strategy,
        protected ?string $fromDate = null
    ) {}

    public function handle(SmContentPlanGeneratorService $service): void
    {
        $from = $this->fromDate ? Carbon::parse($this->fromDate) : null;

        $result = $service->generateSlotsForPlan($this->plan, $this->brand, $this->strategy, $from);

        if (!$result['success']) {
            Log::warning('SmGenerateContentPlanJob: service returned failure', [
                'brand_id' => $this->brand->id,
                'plan_id' => $this->plan->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            $this->plan->update(['status' => 'draft']);

            throw new \RuntimeException($result['error'] ?? 'Content plan generation failed');
        }

        Log::info('SmGenerateContentPlanJob: content plan generated', [
            'brand_id' => $this->brand->id,
            'plan_id' => $this->plan->id,
            'slots_created' => $result['slots_created'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $cacheKey = "content_plan_gen:{$this->plan->id}";
        Cache::put($cacheKey, ['step' => 'failed', 'error' => $exception->getMessage()], now()->addMinutes(10));

        $this->plan->update(['status' => 'draft']);

        Log::error('SmGenerateContentPlanJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'plan_id' => $this->plan->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
