<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Services\SmManager\SmContentPlanGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmGenerateContentPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand,
        protected int $month,
        protected int $year
    ) {}

    public function handle(SmContentPlanGeneratorService $service): void
    {
        try {
            $strategy = $this->brand->smStrategies()->active()->latest()->first();

            if (!$strategy) {
                Log::warning('SmGenerateContentPlanJob: no active strategy found', [
                    'brand_id' => $this->brand->id,
                    'month' => $this->month,
                    'year' => $this->year,
                ]);

                throw new \RuntimeException('No active strategy found for brand');
            }

            $result = $service->generateMonthlyPlan($this->brand, $strategy, $this->month, $this->year);

            if (!$result['success']) {
                Log::warning('SmGenerateContentPlanJob: service returned failure', [
                    'brand_id' => $this->brand->id,
                    'month' => $this->month,
                    'year' => $this->year,
                    'error' => $result['error'] ?? 'Unknown error',
                    'error_code' => $result['error_code'] ?? null,
                ]);

                throw new \RuntimeException($result['error'] ?? 'Content plan generation failed');
            }

            Log::info('SmGenerateContentPlanJob: content plan generated', [
                'brand_id' => $this->brand->id,
                'plan_id' => $result['plan']->id,
                'month' => $this->month,
                'year' => $this->year,
                'slots_created' => $result['slots_created'],
            ]);
        } catch (\Exception $e) {
            Log::error('SmGenerateContentPlanJob: failed', [
                'brand_id' => $this->brand->id,
                'month' => $this->month,
                'year' => $this->year,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmGenerateContentPlanJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'month' => $this->month,
            'year' => $this->year,
            'error' => $exception->getMessage(),
        ]);
    }
}
