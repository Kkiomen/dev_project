<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CiCostTracking;
use App\Services\ApiUsageLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiUsageStatsController extends Controller
{
    public function __construct(
        private readonly ApiUsageLoggerService $usageLogger,
    ) {}

    public function index(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $days = min((int) $request->input('days', 30), 90);
        $from = now()->subDays($days);

        $providerStats = $this->usageLogger->getUsageStatsForBrand($brand, $from);
        $dailyUsage = $this->usageLogger->getDailyUsageForBrand($brand, $days);
        $operationBreakdown = $this->usageLogger->getUsageByOperationForBrand($brand, $from);

        $apifyCost = CiCostTracking::getOrCreateForBrand($brand->id);

        return response()->json([
            'providers' => $providerStats,
            'daily' => $dailyUsage,
            'operations' => $operationBreakdown,
            'apify' => [
                'total_cost' => $apifyCost->total_cost,
                'budget_limit' => $apifyCost->budget_limit,
                'remaining' => $apifyCost->getRemainingBudget(),
                'total_runs' => $apifyCost->total_runs,
                'total_results' => $apifyCost->total_results,
                'cost_breakdown' => $apifyCost->cost_breakdown ?? [],
                'period' => $apifyCost->period,
            ],
            'days' => $days,
        ]);
    }
}
