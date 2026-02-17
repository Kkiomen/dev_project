<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmContentPlanResource;
use App\Http\Resources\SmContentPlanSlotResource;
use App\Jobs\SmManager\SmGenerateContentPlanJob;
use App\Jobs\SmManager\SmGeneratePostContentJob;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Enums\AiProvider;
use App\Models\SmContentPlan;
use App\Models\SmContentPlanSlot;
use App\Services\SmManager\SmContentPlanGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

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
            ->with([
                'strategy',
                'slots' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('scheduled_time'),
                'slots.socialPost.generatedAssets' => fn ($q) => $q->where('type', 'image')->where('status', 'completed')->orderBy('position')->limit(1),
            ])
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

    public function generate(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $strategy = $brand->smStrategies()->active()->latest()->first();

        if (!$strategy) {
            return response()->json(['error' => 'no_active_strategy', 'message' => 'No active strategy found'], 422);
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return response()->json(['error' => 'no_api_key', 'message' => 'No OpenAI API key configured for this brand'], 422);
        }

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $fromDate = $request->input('from_date');

        $plan = $brand->smContentPlans()
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($plan) {
            $plan->slots()->delete();
            $plan->update([
                'sm_strategy_id' => $strategy->id,
                'status' => 'generating',
                'summary' => null,
                'total_slots' => 0,
                'completed_slots' => 0,
                'generated_at' => null,
            ]);
        } else {
            $plan = $brand->smContentPlans()->create([
                'sm_strategy_id' => $strategy->id,
                'month' => $month,
                'year' => $year,
                'status' => 'generating',
            ]);
        }

        Cache::put("content_plan_gen:{$plan->id}", ['step' => 'queued', 'slots_created' => 0], now()->addMinutes(10));

        SmGenerateContentPlanJob::dispatch($plan, $brand, $strategy, $fromDate);

        $plan->load('strategy');

        return response()->json([
            'data' => new SmContentPlanResource($plan),
            'generating' => true,
        ]);
    }

    public function generateStatus(Request $request, Brand $brand, SmContentPlan $smContentPlan): JsonResponse
    {
        $this->authorize('view', $brand);

        $cacheKey = "content_plan_gen:{$smContentPlan->id}";
        $progress = Cache::get($cacheKey, ['step' => 'unknown']);

        $smContentPlan->refresh();

        $response = [
            'status' => $smContentPlan->status,
            'step' => $progress['step'] ?? 'unknown',
            'slots_created' => $progress['slots_created'] ?? 0,
            'total_slots' => $progress['total_slots'] ?? 0,
            'error' => $progress['error'] ?? null,
        ];

        if ($smContentPlan->status !== 'generating') {
            $response['total_slots'] = $smContentPlan->total_slots;
            $response['slots_created'] = $smContentPlan->total_slots;
        }

        return response()->json($response);
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
