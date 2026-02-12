<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmDesignTemplateResource;
use App\Models\Brand;
use App\Models\SmDesignTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SmDesignTemplateController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = SmDesignTemplate::forBrand($brand->id)->active();

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('platform')) {
            $query->forPlatform($request->input('platform'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        $templates = $query->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return SmDesignTemplateResource::collection($templates);
    }

    public function store(Request $request, Brand $brand): SmDesignTemplateResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['post', 'story', 'carousel_slide', 'cover', 'reel_cover'])],
            'platform' => ['nullable', 'string', Rule::in(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'])],
            'canvas_json' => ['nullable', 'array'],
            'width' => ['nullable', 'integer', 'min:100', 'max:4096'],
            'height' => ['nullable', 'integer', 'min:100', 'max:4096'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $template = $brand->smDesignTemplates()->create($validated);

        return new SmDesignTemplateResource($template);
    }

    public function show(Request $request, Brand $brand, SmDesignTemplate $smDesignTemplate): SmDesignTemplateResource
    {
        $this->authorize('view', $brand);

        return new SmDesignTemplateResource($smDesignTemplate);
    }

    public function update(Request $request, Brand $brand, SmDesignTemplate $smDesignTemplate): SmDesignTemplateResource
    {
        $this->authorize('update', $brand);

        if ($smDesignTemplate->is_system) {
            return response()->json(['message' => 'Cannot modify system templates'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(['post', 'story', 'carousel_slide', 'cover', 'reel_cover'])],
            'platform' => ['nullable', 'string', Rule::in(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'])],
            'canvas_json' => ['nullable', 'array'],
            'width' => ['nullable', 'integer', 'min:100', 'max:4096'],
            'height' => ['nullable', 'integer', 'min:100', 'max:4096'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $smDesignTemplate->update($validated);

        return new SmDesignTemplateResource($smDesignTemplate);
    }

    public function destroy(Request $request, Brand $brand, SmDesignTemplate $smDesignTemplate): JsonResponse
    {
        $this->authorize('update', $brand);

        if ($smDesignTemplate->is_system) {
            return response()->json(['message' => 'Cannot delete system templates'], 403);
        }

        $smDesignTemplate->delete();

        return response()->json(['message' => 'Template deleted']);
    }
}
