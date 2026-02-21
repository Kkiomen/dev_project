<?php

namespace App\Jobs\SmManager;

use App\Models\SmPipeline;
use App\Models\SmPipelineRun;
use App\Services\Pipeline\PipelineExecutionService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmExecutePipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public SmPipeline $pipeline,
        public SmPipelineRun $run,
    ) {}

    protected function taskType(): string { return 'pipeline_execution'; }
    protected function taskUserId(): int { return $this->pipeline->brand->user_id; }
    protected function taskModelId(): string|int { return $this->run->id; }

    public function handle(PipelineExecutionService $executionService): void
    {
        $this->pipeline->refresh();
        $this->run->refresh();

        // Skip if run was already processed
        if ($this->run->isFinished()) {
            return;
        }

        $this->broadcastTaskStarted();

        $brand = $this->pipeline->brand;
        $inputData = $this->run->input_data;

        try {
            $executionService->execute($this->pipeline, $brand, $inputData);

            $this->broadcastTaskCompleted(true, null, [
                'pipeline_name' => $this->pipeline->name ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('Pipeline job execution failed', [
                'pipeline_id' => $this->pipeline->id,
                'run_id' => $this->run->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->run->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);

        Log::error('Pipeline job failed permanently', [
            'pipeline_id' => $this->pipeline->id,
            'run_id' => $this->run->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
