<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmMonitoredKeywordResource;
use App\Models\Brand;
use App\Models\SmMonitoredKeyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmMonitoredKeywordController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smMonitoredKeywords();

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $keywords = $query->orderByDesc('mention_count')
            ->get();

        return SmMonitoredKeywordResource::collection($keywords);
    }

    public function store(Request $request, Brand $brand): SmMonitoredKeywordResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $keyword = $brand->smMonitoredKeywords()->create(array_merge(
            ['is_active' => true],
            $validated,
        ));

        return new SmMonitoredKeywordResource($keyword);
    }

    public function show(Request $request, Brand $brand, SmMonitoredKeyword $smMonitoredKeyword): SmMonitoredKeywordResource
    {
        $this->authorize('view', $brand);

        $smMonitoredKeyword->load('mentions');

        return new SmMonitoredKeywordResource($smMonitoredKeyword);
    }

    public function update(Request $request, Brand $brand, SmMonitoredKeyword $smMonitoredKeyword): SmMonitoredKeywordResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'keyword' => ['sometimes', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $smMonitoredKeyword->update($validated);

        return new SmMonitoredKeywordResource($smMonitoredKeyword);
    }

    public function destroy(Request $request, Brand $brand, SmMonitoredKeyword $smMonitoredKeyword): JsonResponse
    {
        $this->authorize('update', $brand);

        $smMonitoredKeyword->delete();

        return response()->json(null, 204);
    }
}
