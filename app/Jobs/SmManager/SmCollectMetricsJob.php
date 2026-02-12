<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Models\SmAnalyticsSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmCollectMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand
    ) {}

    /**
     * Collect analytics metrics for a brand from connected platform accounts.
     *
     * NOTE: This is a placeholder. Actual platform API calls will be added
     * when OAuth integration is implemented. Currently creates snapshots
     * from locally available SmAccount metadata.
     */
    public function handle(): void
    {
        try {
            $accounts = $this->brand->smAccounts()
                ->where('status', 'active')
                ->get();

            if ($accounts->isEmpty()) {
                Log::info('SmCollectMetricsJob: no active accounts found', [
                    'brand_id' => $this->brand->id,
                ]);

                return;
            }

            $snapshotsCreated = 0;

            foreach ($accounts as $account) {
                // TODO: Replace with actual platform API calls when OAuth is implemented
                // For now, create a snapshot from the account's local metadata
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
        } catch (\Exception $e) {
            Log::error('SmCollectMetricsJob: failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

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
