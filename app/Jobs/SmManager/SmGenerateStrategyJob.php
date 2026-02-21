<?php

namespace App\Jobs\SmManager;

use App\Models\Brand;
use App\Models\SmStrategy;
use App\Services\SmManager\SmStrategyGeneratorService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmGenerateStrategyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Brand $brand
    ) {}

    protected function taskType(): string { return 'strategy_generation'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->brand->id; }

    public function handle(SmStrategyGeneratorService $service): void
    {
        $this->broadcastTaskStarted();

        try {
            $activeStrategy = $this->brand->smStrategies()->active()->latest()->first();

            if ($activeStrategy) {
                $result = $service->refineStrategy($this->brand, $activeStrategy, []);
            } else {
                $result = $service->generateStrategy($this->brand);
            }

            if (!$result['success']) {
                Log::warning('SmGenerateStrategyJob: service returned failure', [
                    'brand_id' => $this->brand->id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'error_code' => $result['error_code'] ?? null,
                ]);

                throw new \RuntimeException($result['error'] ?? 'Strategy generation failed');
            }

            $strategyData = $result['strategy'];

            if ($activeStrategy) {
                $activeStrategy->update([
                    'content_pillars' => $strategyData['content_pillars'],
                    'posting_frequency' => $strategyData['posting_frequency'],
                    'target_audience' => $strategyData['target_audience'],
                    'goals' => $strategyData['goals'],
                    'content_mix' => $strategyData['content_mix'],
                    'optimal_times' => $strategyData['optimal_times'],
                    'ai_recommendations' => $strategyData['ai_recommendations'] ?? null,
                ]);

                Log::info('SmGenerateStrategyJob: strategy refined', [
                    'brand_id' => $this->brand->id,
                    'strategy_id' => $activeStrategy->id,
                ]);
            } else {
                $strategy = SmStrategy::create([
                    'brand_id' => $this->brand->id,
                    'content_pillars' => $strategyData['content_pillars'],
                    'posting_frequency' => $strategyData['posting_frequency'],
                    'target_audience' => $strategyData['target_audience'],
                    'goals' => $strategyData['goals'],
                    'content_mix' => $strategyData['content_mix'],
                    'optimal_times' => $strategyData['optimal_times'],
                    'ai_recommendations' => $strategyData['ai_recommendations'] ?? null,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);

                Log::info('SmGenerateStrategyJob: new strategy created', [
                    'brand_id' => $this->brand->id,
                    'strategy_id' => $strategy->id,
                ]);
            }

            $this->broadcastTaskCompleted(true, null, [
                'brand_name' => $this->brand->name,
            ]);
        } catch (\Exception $e) {
            Log::error('SmGenerateStrategyJob: failed', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmGenerateStrategyJob: job failed permanently', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
