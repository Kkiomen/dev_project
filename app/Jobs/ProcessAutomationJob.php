<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\Automation\AutomationOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        protected Brand $brand
    ) {}

    public function handle(AutomationOrchestrator $orchestrator): void
    {
        // Skip if automation is disabled
        if (!$this->brand->isAutomationEnabled()) {
            Log::info('Automation disabled for brand, skipping', [
                'brand_id' => $this->brand->id,
            ]);
            return;
        }

        // Skip if brand is not active
        if (!$this->brand->is_active) {
            Log::info('Brand is not active, skipping automation', [
                'brand_id' => $this->brand->id,
            ]);
            return;
        }

        // Skip if onboarding not completed
        if (!$this->brand->onboarding_completed) {
            Log::info('Brand onboarding not completed, skipping automation', [
                'brand_id' => $this->brand->id,
            ]);
            return;
        }

        try {
            $results = $orchestrator->processAutomation($this->brand);

            Log::info('Automation job completed', [
                'brand_id' => $this->brand->id,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Automation job failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Process automation job failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
