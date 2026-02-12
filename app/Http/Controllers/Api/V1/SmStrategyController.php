<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmStrategyResource;
use App\Models\Brand;
use App\Models\SmStrategy;
use App\Services\SmManager\SmStrategyGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmStrategyController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $strategies = $brand->smStrategies()
            ->orderByDesc('created_at')
            ->get();

        return SmStrategyResource::collection($strategies);
    }

    public function show(Request $request, Brand $brand): SmStrategyResource
    {
        $this->authorize('view', $brand);

        $strategy = $brand->smStrategies()->active()->latest()->first()
            ?? $brand->smStrategies()->latest()->first();

        if (!$strategy) {
            $strategy = $brand->smStrategies()->create([
                'status' => 'draft',
                'content_pillars' => [],
                'posting_frequency' => [],
                'target_audience' => [],
                'goals' => [],
                'content_mix' => [
                    'educational' => 40,
                    'entertaining' => 25,
                    'promotional' => 20,
                    'engaging' => 15,
                ],
            ]);
        }

        return new SmStrategyResource($strategy);
    }

    public function update(Request $request, Brand $brand): SmStrategyResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'content_pillars' => ['nullable', 'array'],
            'content_pillars.*.name' => ['required_with:content_pillars', 'string', 'max:255'],
            'content_pillars.*.description' => ['nullable', 'string', 'max:500'],
            'content_pillars.*.percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'posting_frequency' => ['nullable', 'array'],
            'target_audience' => ['nullable', 'array'],
            'goals' => ['nullable', 'array'],
            'goals.*.goal' => ['required_with:goals', 'string', 'max:255'],
            'goals.*.metric' => ['nullable', 'string', 'max:100'],
            'goals.*.target_value' => ['nullable', 'string', 'max:100'],
            'goals.*.timeframe' => ['nullable', 'string', 'max:100'],
            'competitor_handles' => ['nullable', 'array'],
            'content_mix' => ['nullable', 'array'],
            'optimal_times' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:draft,active,paused,archived'],
        ]);

        $strategy = $brand->smStrategies()->latest()->first();

        if (!$strategy) {
            $strategy = $brand->smStrategies()->create(array_merge($validated, ['status' => $validated['status'] ?? 'draft']));
        } else {
            $strategy->update($validated);
        }

        if (isset($validated['status']) && $validated['status'] === 'active' && !$strategy->activated_at) {
            $strategy->activate();
        }

        return new SmStrategyResource($strategy->fresh());
    }

    public function generate(Request $request, Brand $brand, SmStrategyGeneratorService $generator): JsonResponse
    {
        $this->authorize('update', $brand);

        $result = $generator->generateStrategy($brand);

        if (!$result['success']) {
            $status = ($result['error_code'] ?? '') === 'no_api_key' ? 422 : 500;
            return response()->json(['success' => false, 'error' => $result['error']], $status);
        }

        $strategy = $brand->smStrategies()->latest()->first();

        if ($strategy) {
            $strategy->update($result['strategy']);
        } else {
            $strategy = $brand->smStrategies()->create(array_merge($result['strategy'], ['status' => 'draft']));
        }

        return response()->json([
            'success' => true,
            'data' => new SmStrategyResource($strategy->fresh()),
        ]);
    }

    public function activate(Request $request, Brand $brand): SmStrategyResource
    {
        $this->authorize('update', $brand);

        $strategy = $brand->smStrategies()->latest()->firstOrFail();

        // Deactivate any previously active strategy
        $brand->smStrategies()->where('status', 'active')->update(['status' => 'paused']);

        $strategy->activate();

        return new SmStrategyResource($strategy);
    }
}
