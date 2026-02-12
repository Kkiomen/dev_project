<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmContentTemplateResource;
use App\Models\Brand;
use App\Models\SmContentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmContentTemplateController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = SmContentTemplate::forBrand($brand->id)->active();

        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->has('platform')) {
            $query->forPlatform($request->input('platform'));
        }

        $templates = $query->orderBy('is_system', 'desc')
            ->orderBy('usage_count', 'desc')
            ->get();

        return SmContentTemplateResource::collection($templates);
    }

    public function store(Request $request, Brand $brand): SmContentTemplateResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:50'],
            'prompt_template' => ['required', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'variables.*.name' => ['required_with:variables', 'string'],
            'variables.*.type' => ['required_with:variables', 'string'],
            'variables.*.default' => ['nullable', 'string'],
            'variables.*.description' => ['nullable', 'string'],
            'content_type' => ['nullable', 'string', 'max:50'],
        ]);

        $template = $brand->smContentTemplates()->create($validated);

        return new SmContentTemplateResource($template);
    }

    public function show(Request $request, Brand $brand, SmContentTemplate $smContentTemplate): SmContentTemplateResource
    {
        $this->authorize('view', $brand);

        return new SmContentTemplateResource($smContentTemplate);
    }

    public function update(Request $request, Brand $brand, SmContentTemplate $smContentTemplate): SmContentTemplateResource
    {
        $this->authorize('update', $brand);

        if ($smContentTemplate->is_system) {
            return response()->json(['message' => 'Cannot modify system templates'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:50'],
            'prompt_template' => ['sometimes', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'content_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $smContentTemplate->update($validated);

        return new SmContentTemplateResource($smContentTemplate);
    }

    public function destroy(Request $request, Brand $brand, SmContentTemplate $smContentTemplate): JsonResponse
    {
        $this->authorize('update', $brand);

        if ($smContentTemplate->is_system) {
            return response()->json(['message' => 'Cannot delete system templates'], 403);
        }

        $smContentTemplate->delete();

        return response()->json(['message' => 'Template deleted']);
    }
}
