<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Services\SmManager\SmAlertNotificationService;
use App\Services\SmManager\SmCrisisDetectorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmDetectCrisisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand
    ) {}

    public function handle(SmCrisisDetectorService $detector, SmAlertNotificationService $notifier): void
    {
        try {
            $result = $detector->detect($this->brand);

            $alertsCreated = $result['alerts_created'];
            $indicators = $result['indicators'];

            if ($alertsCreated > 0) {
                // Notify for each newly created crisis alert
                $recentAlerts = $this->brand->smCrisisAlerts()
                    ->unresolved()
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->get();

                foreach ($recentAlerts as $alert) {
                    $notifier->notifyCrisis($this->brand, $alert);
                }

                Log::warning('SmDetectCrisisJob: crisis alerts detected and notifications sent', [
                    'brand_id' => $this->brand->id,
                    'alerts_created' => $alertsCreated,
                    'notifications_sent' => $recentAlerts->count(),
                    'indicator_types' => array_column($indicators, 'type'),
                ]);
            } else {
                Log::info('SmDetectCrisisJob: no crisis indicators detected', [
                    'brand_id' => $this->brand->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SmDetectCrisisJob: failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmDetectCrisisJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
