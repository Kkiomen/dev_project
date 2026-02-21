<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Models\SmAnalyticsSnapshot;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmCollectMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand
    ) {}

    protected function taskType(): string { return 'metrics_collection'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->brand->id; }

    public function handle(): void
    {
        $this->broadcastTaskStarted();

        try {
            $accounts = $this->brand->smAccounts()
                ->where('status', 'active')
                ->get();

            if ($accounts->isEmpty()) {
                Log::info('SmCollectMetricsJob: no active accounts found', [
                    'brand_id' => $this->brand->id,
                ]);

                $this->broadcastTaskCompleted(true);
                return;
            }

            $snapshotsCreated = 0;

            foreach ($accounts as $account) {
                $metadata = $account->metadata ?? [];

                SmAnalyticsSnapshot::create([
                    'brand_id' => $this->brand->id,
                    'platform' => $account->platform,
                    'snapshot_date' => now()->toDateString(),
                    'followers' => $metadata['followers_count'] ?? null,
                    'following' => $metadata['following_count'] ?? null,
                    'reach' => $metadata['reach'] ?? null,
                    'impressions' => $metadata['impressions'] ?? null,
                    'profile_views' => $metadata['profile_views'] ?? null,
                    'website_clicks' => $metadata['website_clicks'] ?? null,
                    'engagement_rate' => $metadata['engagement_rate'] ?? null,
                    'posts_count' => $metadata['posts_count'] ?? null,
                    'extra_metrics' => [
                        'source' => 'local_metadata',
                        'account_id' => $account->id,
                        'collected_at' => now()->toIso8601String(),
                    ],
                ]);

                $snapshotsCreated++;
            }

            Log::info('SmCollectMetricsJob: metrics collected', [
                'brand_id' => $this->brand->id,
                'accounts_processed' => $accounts->count(),
                'snapshots_created' => $snapshotsCreated,
            ]);

            $this->broadcastTaskCompleted(true);
        } catch (\Exception $e) {
            Log::error('SmCollectMetricsJob: failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmCollectMetricsJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
