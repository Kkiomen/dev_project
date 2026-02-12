<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmScheduledPostResource;
use App\Models\Brand;
use App\Models\SmScheduledPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmScheduledPostController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smScheduledPosts();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        $posts = $query->with('socialPost.generatedAssets')
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return SmScheduledPostResource::collection($posts);
    }

    public function show(Request $request, Brand $brand, SmScheduledPost $smScheduledPost): SmScheduledPostResource
    {
        $this->authorize('view', $brand);

        $smScheduledPost->load('socialPost');

        return new SmScheduledPostResource($smScheduledPost);
    }

    public function approve(Request $request, Brand $brand, SmScheduledPost $smScheduledPost): SmScheduledPostResource
    {
        $this->authorize('update', $brand);

        $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $smScheduledPost->approve($request->user()->id, $request->approval_notes);

        return new SmScheduledPostResource($smScheduledPost);
    }

    public function reject(Request $request, Brand $brand, SmScheduledPost $smScheduledPost): SmScheduledPostResource
    {
        $this->authorize('update', $brand);

        $request->validate([
            'approval_notes' => ['required', 'string', 'max:1000'],
        ]);

        $smScheduledPost->reject($request->user()->id, $request->approval_notes);

        return new SmScheduledPostResource($smScheduledPost);
    }

    public function destroy(Request $request, Brand $brand, SmScheduledPost $smScheduledPost): JsonResponse
    {
        $this->authorize('update', $brand);

        $smScheduledPost->delete();

        return response()->json(null, 204);
    }
}
