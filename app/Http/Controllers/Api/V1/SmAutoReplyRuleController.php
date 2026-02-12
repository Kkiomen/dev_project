<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmAutoReplyRuleResource;
use App\Models\Brand;
use App\Models\SmAutoReplyRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmAutoReplyRuleController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $rules = $brand->smAutoReplyRules()
            ->orderByDesc('created_at')
            ->get();

        return SmAutoReplyRuleResource::collection($rules);
    }

    public function store(Request $request, Brand $brand): SmAutoReplyRuleResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'trigger_type' => ['required', 'string', 'max:255'],
            'trigger_value' => ['required', 'string', 'max:1000'],
            'response_template' => ['required', 'string', 'max:2000'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = $brand->smAutoReplyRules()->create($validated);

        return new SmAutoReplyRuleResource($rule);
    }

    public function show(Request $request, Brand $brand, SmAutoReplyRule $smAutoReplyRule): SmAutoReplyRuleResource
    {
        $this->authorize('view', $brand);

        return new SmAutoReplyRuleResource($smAutoReplyRule);
    }

    public function update(Request $request, Brand $brand, SmAutoReplyRule $smAutoReplyRule): SmAutoReplyRuleResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'trigger_type' => ['sometimes', 'string', 'max:255'],
            'trigger_value' => ['sometimes', 'string', 'max:1000'],
            'response_template' => ['sometimes', 'string', 'max:2000'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $smAutoReplyRule->update($validated);

        return new SmAutoReplyRuleResource($smAutoReplyRule);
    }

    public function destroy(Request $request, Brand $brand, SmAutoReplyRule $smAutoReplyRule): JsonResponse
    {
        $this->authorize('update', $brand);

        $smAutoReplyRule->delete();

        return response()->json(null, 204);
    }
}
