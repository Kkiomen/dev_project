<?php

namespace App\Jobs\SmManager;

use App\Models\SmPipeline;
use App\Models\SmPipelineRun;
use App\Services\Pipeline\PipelineExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmExecutePipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public SmPipeline $pipeline,
        public SmPipelineRun $run,
    ) {}

    public function handle(PipelineExecutionService $executionService): void
    {
        $this->pipeline->refresh();
        $this->run->refresh();

        // Skip if run was already processed
        if ($this->run->isFinished()) {
            return;
        }

        $brand = $this->pipeline->brand;
        $inputData = $this->run->input_data;

        try {
            $executionService->execute($this->pipeline, $brand, $inputData);
        } catch (\Throwable $e) {
            Log::error('Pipeline job execution failed', [
                'pipeline_id' => $this->pipeline->id,
                'run_id' => $this->run->id,
                'error' => $e->getMessage(),
            ]);
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
