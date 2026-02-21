<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Services\SmManager\SmAlertNotificationService;
use App\Services\SmManager\SmCrisisDetectorService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmDetectCrisisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 60;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand
    ) {}

    protected function taskType(): string { return 'crisis_detection'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->brand->id; }

    public function handle(SmCrisisDetectorService $detector, SmAlertNotificationService $notifier): void
    {
        $this->broadcastTaskStarted();

        try {
            $result = $detector->detect($this->brand);

            $alertsCreated = $result['alerts_created'];
            $indicators = $result['indicators'];

            if ($alertsCreated > 0) {
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

            $this->broadcastTaskCompleted(true);
        } catch (\Exception $e) {
            Log::error('SmDetectCrisisJob: failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

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
