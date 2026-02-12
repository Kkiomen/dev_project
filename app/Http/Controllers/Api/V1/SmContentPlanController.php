<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmContentPlanResource;
use App\Http\Resources\SmContentPlanSlotResource;
use App\Jobs\SmManager\SmGeneratePostContentJob;
use App\Models\Brand;
use App\Models\SmContentPlan;
use App\Models\SmContentPlanSlot;
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
            'status' => ['sometimes', 'string', 'in:planned,content_ready,media_ready,approved,published,skipped'],
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

        SmGeneratePostContentJob::dispatch($slot);

        return response()->json([
            'message' => 'Content generation started',
            'slot_id' => $slot->id,
        ]);
    }

    public function generateAllContent(Request $request, Brand $brand, SmContentPlan $smContentPlan): JsonResponse
    {
        $this->authorize('update', $brand);

        $plannedSlots = $smContentPlan->slots()->planned()->get();

        foreach ($plannedSlots as $slot) {
            SmGeneratePostContentJob::dispatch($slot);
        }

        return response()->json([
            'message' => 'Content generation started for all planned slots',
            'count' => $plannedSlots->count(),
        ]);
    }
}
