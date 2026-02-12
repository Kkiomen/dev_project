<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Services\SmManager\SmPerformanceScorerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmScorePostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand,
        protected int $limit = 10
    ) {}

    public function handle(SmPerformanceScorerService $scorer): void
    {
        try {
            $result = $scorer->scoreBatch($this->brand, $this->limit);

            if (!$result['success'] && isset($result['error_code'])) {
                Log::warning('SmScorePostsJob: batch scoring stopped', [
                    'brand_id' => $this->brand->id,
                    'error_code' => $result['error_code'],
                    'scored' => $result['scored'] ?? 0,
                    'errors' => $result['errors'] ?? 0,
                ]);

                throw new \RuntimeException($result['error_code'] . ': ' . ($result['error'] ?? 'Scoring batch stopped'));
            }

            Log::info('SmScorePostsJob: batch scoring completed', [
                'brand_id' => $this->brand->id,
                'limit' => $this->limit,
                'scored' => $result['scored'] ?? 0,
                'errors' => $result['errors'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('SmScorePostsJob: failed', [
                'brand_id' => $this->brand->id,
                'limit' => $this->limit,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmScorePostsJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'limit' => $this->limit,
            'error' => $exception->getMessage(),
        ]);
    }
}
