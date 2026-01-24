<?php

namespace App\Services;

use App\Enums\ApiProvider;
use App\Models\AiOperationLog;
use App\Models\Brand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApiUsageLoggerService
{
    /**
     * Generate a new request ID for grouping related API calls.
     */
    public function generateRequestId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Calculate OpenAI API cost based on token usage.
     */
    public function calculateOpenAiCost(int $promptTokens, int $completionTokens, string $model): float
    {
        $pricing = config("api_pricing.openai.{$model}", config('api_pricing.openai.default'));

        $inputCost = ($promptTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($completionTokens / 1_000_000) * $pricing['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Get total cost for a brand within a date range.
     */
    public function getTotalCostForBrand(Brand $brand, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $query = AiOperationLog::forBrand($brand)->completed();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return (float) $query->sum('cost');
    }

    /**
     * Get total tokens used for a brand within a date range.
     */
    public function getTotalTokensForBrand(Brand $brand, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        $query = AiOperationLog::forBrand($brand)->completed();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return (int) $query->sum('tokens_used');
    }

    /**
     * Get usage statistics for a brand.
     */
    public function getUsageStatsForBrand(Brand $brand, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = AiOperationLog::forBrand($brand);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $stats = $query->selectRaw('
            provider,
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_requests,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_requests,
            SUM(tokens_used) as total_tokens,
            SUM(prompt_tokens) as total_prompt_tokens,
            SUM(completion_tokens) as total_completion_tokens,
            SUM(cost) as total_cost,
            AVG(duration_ms) as avg_duration_ms
        ')->groupBy('provider')->get();

        return $stats->map(function ($item) {
            return [
                'provider' => $item->provider,
                'total_requests' => (int) $item->total_requests,
                'completed_requests' => (int) $item->completed_requests,
                'failed_requests' => (int) $item->failed_requests,
                'total_tokens' => (int) $item->total_tokens,
                'total_prompt_tokens' => (int) $item->total_prompt_tokens,
                'total_completion_tokens' => (int) $item->total_completion_tokens,
                'total_cost' => (float) $item->total_cost,
                'avg_duration_ms' => (float) $item->avg_duration_ms,
            ];
        })->keyBy('provider')->toArray();
    }

    /**
     * Get usage by operation type for a brand.
     */
    public function getUsageByOperationForBrand(Brand $brand, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): Collection
    {
        $query = AiOperationLog::forBrand($brand)->completed();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->selectRaw('
            operation,
            COUNT(*) as request_count,
            SUM(tokens_used) as total_tokens,
            SUM(cost) as total_cost
        ')->groupBy('operation')->get();
    }

    /**
     * Get daily usage for a brand.
     */
    public function getDailyUsageForBrand(Brand $brand, int $days = 30): Collection
    {
        return AiOperationLog::forBrand($brand)
            ->completed()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as request_count,
                SUM(tokens_used) as total_tokens,
                SUM(cost) as total_cost
            ')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get all logs for a specific request ID.
     */
    public function getLogsByRequestId(string $requestId): Collection
    {
        return AiOperationLog::forRequestId($requestId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get recent failed operations for a brand.
     */
    public function getRecentFailuresForBrand(Brand $brand, int $limit = 10): Collection
    {
        return AiOperationLog::forBrand($brand)
            ->failed()
            ->latest()
            ->limit($limit)
            ->get();
    }
}
