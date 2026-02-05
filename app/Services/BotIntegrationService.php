<?php

namespace App\Services;

use App\Models\DevTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotIntegrationService
{
    public function triggerBot(DevTask $task): array
    {
        $url = config('services.dev_bot.url');
        $timeout = config('services.dev_bot.timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->post($url, [
                    'task_id' => $task->public_id,
                    'identifier' => $task->identifier,
                    'title' => $task->title,
                    'pm_description' => $task->pm_description,
                    'tech_description' => $task->tech_description,
                    'implementation_plan' => $task->implementation_plan,
                    'priority' => $task->priority,
                    'labels' => $task->labels,
                ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('DevBot trigger failed', [
                'task_id' => $task->public_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function generatePlan(DevTask $task): array
    {
        $url = config('services.dev_bot.url') . '/generate-plan';
        $timeout = config('services.dev_bot.timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->post($url, [
                    'task_id' => $task->public_id,
                    'identifier' => $task->identifier,
                    'title' => $task->title,
                    'pm_description' => $task->pm_description,
                    'tech_description' => $task->tech_description,
                ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('DevBot plan generation failed', [
                'task_id' => $task->public_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }
}
