<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmGeneratedAssetResource;
use App\Models\Brand;
use App\Models\SmGeneratedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class SmGeneratedAssetController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smGeneratedAssets()->with('designTemplate');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('social_post_id')) {
            $query->whereHas('socialPost', fn ($q) => $q->where('public_id', $request->input('social_post_id')));
        }

        $assets = $query->orderByDesc('created_at')->paginate($request->input('per_page', 20));

        return SmGeneratedAssetResource::collection($assets);
    }

    public function store(Request $request, Brand $brand): SmGeneratedAssetResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'generation_prompt' => ['required', 'string', 'max:2000'],
            'ai_provider' => ['nullable', 'string', 'max:50'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'generation_params' => ['nullable', 'array'],
            'sm_design_template_id' => ['nullable', 'exists:sm_design_templates,public_id'],
            'social_post_id' => ['nullable', 'exists:social_posts,public_id'],
        ]);

        $asset = $brand->smGeneratedAssets()->create([
            'type' => $validated['type'],
            'file_path' => '',
            'generation_prompt' => $validated['generation_prompt'],
            'ai_provider' => $validated['ai_provider'] ?? null,
            'ai_model' => $validated['ai_model'] ?? null,
            'generation_params' => $validated['generation_params'] ?? null,
            'status' => 'pending',
        ]);

        // AI generation would be dispatched as a job here
        // GenerateAssetJob::dispatch($asset);

        return new SmGeneratedAssetResource($asset);
    }

    public function show(Request $request, Brand $brand, SmGeneratedAsset $smGeneratedAsset): SmGeneratedAssetResource
    {
        $this->authorize('view', $brand);

        $smGeneratedAsset->load('designTemplate');

        return new SmGeneratedAssetResource($smGeneratedAsset);
    }

    public function destroy(Request $request, Brand $brand, SmGeneratedAsset $smGeneratedAsset): JsonResponse
    {
        $this->authorize('update', $brand);

        if ($smGeneratedAsset->file_path) {
            Storage::disk($smGeneratedAsset->disk)->delete($smGeneratedAsset->file_path);
        }
        if ($smGeneratedAsset->thumbnail_path) {
            Storage::disk($smGeneratedAsset->disk)->delete($smGeneratedAsset->thumbnail_path);
        }

        $smGeneratedAsset->delete();

        return response()->json(['message' => 'Asset deleted']);
    }
}
