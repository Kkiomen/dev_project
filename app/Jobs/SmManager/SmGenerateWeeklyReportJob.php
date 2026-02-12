<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Services\SmManager\SmReportGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmGenerateWeeklyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand
    ) {}

    public function handle(SmReportGeneratorService $reportGenerator): void
    {
        try {
            $result = $reportGenerator->generateWeeklyReport($this->brand);

            if (!$result['success']) {
                Log::warning('SmGenerateWeeklyReportJob: report generation returned failure', [
                    'brand_id' => $this->brand->id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'error_code' => $result['error_code'] ?? null,
                ]);

                throw new \RuntimeException($result['error'] ?? 'Weekly report generation failed');
            }

            $report = $result['report'];

            Log::info('SmGenerateWeeklyReportJob: weekly report generated', [
                'brand_id' => $this->brand->id,
                'report_id' => $report->id,
                'period' => $report->getPeriodLabel(),
                'message' => $result['message'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('SmGenerateWeeklyReportJob: failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmGenerateWeeklyReportJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
