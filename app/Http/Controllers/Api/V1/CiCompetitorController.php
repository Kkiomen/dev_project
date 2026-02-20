<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CiCompetitor;
use App\Models\CiCompetitorPost;
use App\Models\CiCostTracking;
use App\Models\CiInsight;
use App\Models\CiScrapeRun;
use App\Models\CiTrendingTopic;
use App\Services\Apify\ApifyService;
use App\Services\Apify\CompetitorAnalysisService;
use App\Services\Apify\ContentInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CiCompetitorController extends Controller
{
    public function index(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $competitors = CiCompetitor::forBrand($brand->id)
            ->active()
            ->with(['accounts'])
            ->withCount('posts')
            ->latest()
            ->get();

        return response()->json(['data' => $competitors]);
    }

    public function store(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*.platform' => ['required', 'string', 'in:instagram,tiktok,linkedin,youtube,twitter'],
            'accounts.*.handle' => ['required', 'string', 'max:255'],
        ]);

        $competitor = CiCompetitor::create([
            'brand_id' => $brand->id,
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['accounts'] as $accountData) {
            $competitor->accounts()->create([
                'platform' => $accountData['platform'],
                'handle' => ltrim($accountData['handle'], '@'),
            ]);
        }

        $competitor->load('accounts');

        return response()->json(['data' => $competitor], 201);
    }

    public function show(Request $request, Brand $brand, CiCompetitor $competitor): JsonResponse
    {
        $this->authorize('view', $brand);

        $competitor->load(['accounts', 'posts' => fn ($q) => $q->latest('posted_at')->limit(20)]);
        $competitor->loadCount('posts');

        return response()->json(['data' => $competitor]);
    }

    public function update(Request $request, Brand $brand, CiCompetitor $competitor): JsonResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $competitor->update($validated);

        return response()->json(['data' => $competitor]);
    }

    public function destroy(Request $request, Brand $brand, CiCompetitor $competitor): JsonResponse
    {
        $this->authorize('update', $brand);

        $competitor->delete();

        return response()->json(['message' => 'Competitor deleted']);
    }

    public function posts(Request $request, Brand $brand, CiCompetitor $competitor): JsonResponse
    {
        $this->authorize('view', $brand);

        $query = $competitor->posts()->latest('posted_at');

        if ($request->filled('platform')) {
            $query->byPlatform($request->input('platform'));
        }

        $posts = $query->paginate($request->input('per_page', 20));

        return response()->json($posts);
    }

    public function insights(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $query = CiInsight::forBrand($brand->id)->active();

        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        if ($request->boolean('unactioned_only', true)) {
            $query->unactioned();
        }

        $insights = $query->orderByDesc('priority')->paginate($request->input('per_page', 20));

        return response()->json($insights);
    }

    public function actionInsight(Request $request, Brand $brand, CiInsight $insight): JsonResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'action_taken' => ['required', 'string', 'in:applied,dismissed'],
        ]);

        $insight->markAsActioned($validated['action_taken']);

        return response()->json(['data' => $insight]);
    }

    public function benchmarks(Request $request, Brand $brand, CompetitorAnalysisService $analysisService): JsonResponse
    {
        $this->authorize('view', $brand);

        $benchmarks = $analysisService->calculateBenchmarks($brand);

        return response()->json(['data' => $benchmarks]);
    }

    public function trends(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $query = CiTrendingTopic::forBrand($brand->id)->active();

        if ($request->filled('platform')) {
            $query->forPlatform($request->input('platform'));
        }

        if ($request->filled('direction')) {
            $query->byDirection($request->input('direction'));
        }

        $trends = $query->orderByDesc('growth_rate')->get();

        return response()->json(['data' => $trends]);
    }

    public function scrape(Request $request, Brand $brand, ApifyService $apifyService): JsonResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:profiles,posts,trends'],
        ]);

        if ($apifyService->isBudgetExceeded($brand)) {
            return response()->json([
                'message' => 'Monthly budget exceeded',
                'remaining_budget' => $apifyService->getRemainingBudget($brand),
            ], 422);
        }

        $type = $validated['type'];

        if ($type === 'profiles') {
            \App\Jobs\Apify\CiScrapeCompetitorsJob::dispatch($brand, 'profiles');
        } elseif ($type === 'posts') {
            \App\Jobs\Apify\CiScrapeCompetitorsJob::dispatch($brand, 'posts');
        } else {
            \App\Jobs\Apify\CiScrapeTrendsJob::dispatch($brand);
        }

        return response()->json(['message' => 'Scrape job dispatched', 'type' => $type]);
    }

    public function scrapeStatus(Request $request, Brand $brand, ApifyService $apifyService): JsonResponse
    {
        $this->authorize('view', $brand);

        $activeRuns = CiScrapeRun::forBrand($brand->id)->active()->get();
        $recentRuns = CiScrapeRun::forBrand($brand->id)->recent(48)->latest()->limit(20)->get();

        return response()->json([
            'data' => [
                'active_runs' => $activeRuns,
                'recent_runs' => $recentRuns,
                'remaining_budget' => $apifyService->getRemainingBudget($brand),
                'budget_exceeded' => $apifyService->isBudgetExceeded($brand),
            ],
        ]);
    }

    public function discoverCompetitors(Request $request, Brand $brand, CompetitorAnalysisService $analysisService): JsonResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'platforms' => ['sometimes', 'array'],
            'platforms.*' => ['string', 'in:instagram,tiktok,linkedin,youtube,twitter'],
        ]);

        try {
            $suggestions = $analysisService->discoverCompetitors($brand, $validated['platforms'] ?? []);

            return response()->json(['data' => $suggestions]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'no_api_key') {
                return response()->json([
                    'message' => 'OpenAI API key not configured',
                    'error_code' => 'no_api_key',
                ], 422);
            }
            throw $e;
        }
    }

    public function cost(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $currentPeriod = CiCostTracking::getOrCreateForBrand($brand->id);
        $history = CiCostTracking::forBrand($brand->id)
            ->orderByDesc('period')
            ->limit(6)
            ->get();

        return response()->json([
            'data' => [
                'current' => $currentPeriod,
                'history' => $history,
            ],
        ]);
    }
}
