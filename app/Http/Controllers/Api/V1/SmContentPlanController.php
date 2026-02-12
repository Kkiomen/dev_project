<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmContentPlanResource;
use App\Http\Resources\SmContentPlanSlotResource;
use App\Jobs\SmManager\SmGeneratePostContentJob;
use App\Models\Brand;
use App\Models\SmContentPlan;
use App\Models\SmContentPlanSlot;
use App\Services\SmManager\SmContentPlanGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmContentPlanController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smContentPlans()->with('strategy');

        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        $plans = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return SmContentPlanResource::collection($plans);
    }

    public function show(Request $request, Brand $brand, SmContentPlan $smContentPlan): SmContentPlanResource
    {
        $this->authorize('view', $brand);

        $smContentPlan->load(['strategy', 'slots' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('scheduled_time')]);

        return new SmContentPlanResource($smContentPlan);
    }

    public function current(Request $request, Brand $brand): SmContentPlanResource
    {
        $this->authorize('view', $brand);

        $plan = $brand->smContentPlans()
            ->forMonth(now()->month, now()->year)
            ->with(['strategy', 'slots' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('scheduled_time')])
            ->first();

        if (!$plan) {
            $strategy = $brand->smStrategies()->active()->latest()->first();

            $plan = $brand->smContentPlans()->create([
                'sm_strategy_id' => $strategy?->id,
                'month' => now()->month,
                'year' => now()->year,
                'status' => 'draft',
            ]);

            $plan->load(['strategy', 'slots']);
        }

        return new SmContentPlanResource($plan);
    }

    public function updateSlot(Request $request, Brand $brand, SmContentPlan $smContentPlan, SmContentPlanSlot $slot): SmContentPlanSlotResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'scheduled_date' => ['sometimes', 'date'],
            'scheduled_time' => ['nullable', 'string', 'size:5'],
            'platform' => ['sometimes', 'string'],
            'content_type' => ['sometimes', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pillar' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:planned,generating,content_ready,media_ready,approved,published,skipped'],
        ]);

        $slot->update($validated);

        return new SmContentPlanSlotResource($slot);
    }

    public function addSlot(Request $request, Brand $brand, SmContentPlan $smContentPlan): SmContentPlanSlotResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'string', 'size:5'],
            'platform' => ['required', 'string'],
            'content_type' => ['required', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pillar' => ['nullable', 'string', 'max:255'],
        ]);

        $slot = $smContentPlan->slots()->create(array_merge($validated, [
            'status' => 'planned',
            'position' => $smContentPlan->slots()->max('position') + 1,
        ]));

        $smContentPlan->recalculateSlotCounts();

        return new SmContentPlanSlotResource($slot);
    }

    public function removeSlot(Request $request, Brand $brand, SmContentPlan $smContentPlan, SmContentPlanSlot $slot): JsonResponse
    {
        $this->authorize('update', $brand);

        $slot->delete();
        $smContentPlan->recalculateSlotCounts();

        return response()->json(['message' => 'Slot removed']);
    }

    public function generateSlotContent(Request $request, Brand $brand, SmContentPlan $smContentPlan, SmContentPlanSlot $slot): JsonResponse
    {
        $this->authorize('update', $brand);

        if ($slot->status !== 'planned') {
            return response()->json(['message' => 'Slot is not in planned status'], 422);
        }

        $slot->update(['status' => 'generating']);

        SmGeneratePostContentJob::dispatch($slot);

        return response()->json([
            'message' => 'Content generation started',
            'slot_id' => $slot->id,
        ]);
    }

    public function slotStatus(Request $request, Brand $brand, SmContentPlan $smContentPlan, SmContentPlanSlot $slot): JsonResponse
    {
        $this->authorize('view', $brand);

        return response()->json([
            'status' => $slot->status,
            'has_content' => $slot->hasContent(),
            'social_post_id' => $slot->socialPost?->public_id,
        ]);
    }

    public function generate(Request $request, Brand $brand, SmContentPlanGeneratorService $generator): JsonResponse
    {
        $this->authorize('update', $brand);

        $strategy = $brand->smStrategies()->active()->latest()->first();

        if (!$strategy) {
            return response()->json(['error' => 'no_active_strategy', 'message' => 'No active strategy found'], 422);
        }

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $fromDate = $request->input('from_date')
            ? \Carbon\Carbon::parse($request->input('from_date'))
            : null;

        $result = $generator->generateMonthlyPlan($brand, $strategy, (int) $month, (int) $year, $fromDate);

        if (!$result['success']) {
            $status = ($result['error_code'] ?? '') === 'no_api_key' ? 422 : 500;

            return response()->json([
                'error' => $result['error_code'] ?? 'generation_failed',
                'message' => $result['error'] ?? 'Failed to generate content plan',
            ], $status);
        }

        $result['plan']->load(['strategy', 'slots' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('scheduled_time')]);

        return response()->json(['data' => new SmContentPlanResource($result['plan'])]);
    }

    public function generateTopicProposition(Request $request, Brand $brand, SmContentPlan $smContentPlan, SmContentPlanGeneratorService $generator): JsonResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'platform' => ['required', 'string'],
            'content_type' => ['required', 'string'],
            'date' => ['required', 'date'],
            'pillar' => ['nullable', 'string'],
        ]);

        $result = $generator->generateTopicProposition($brand, $validated);

        return response()->json($result);
    }

    public function generateAllContent(Request $request, Brand $brand, SmContentPlan $smContentPlan): JsonResponse
    {
        $this->authorize('update', $brand);

        $plannedSlots = $smContentPlan->slots()
            ->where('status', 'planned')
            ->get();

        if ($plannedSlots->isEmpty()) {
            return response()->json([
                'message' => 'No planned slots to generate',
                'count' => 0,
            ]);
        }

        foreach ($plannedSlots as $slot) {
            $slot->update(['status' => 'generating']);
            SmGeneratePostContentJob::dispatch($slot);
        }

        return response()->json([
            'message' => 'Content generation started for all planned slots',
            'count' => $plannedSlots->count(),
        ]);
    }
}
