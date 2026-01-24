<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReorderMediaRequest;
use App\Http\Resources\PostMediaResource;
use App\Models\PostMedia;
use App\Models\SocialPost;
use App\Services\PostMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostMediaController extends Controller
{
    public function __construct(
        protected PostMediaService $mediaService
    ) {}

    public function index(Request $request, SocialPost $post): AnonymousResourceCollection
    {
        $this->authorize('view', $post);

        return PostMediaResource::collection(
            $post->media()->ordered()->get()
        );
    }

    public function store(Request $request, SocialPost $post): PostMediaResource
    {
        $this->authorize('uploadMedia', $post);

        $request->validate([
            'file' => ['required', 'file', 'max:102400', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm'],
        ]);

        $media = $this->mediaService->upload($request->file('file'), $post);

        return new PostMediaResource($media);
    }

    public function destroy(Request $request, PostMedia $media): JsonResponse
    {
        $post = $media->socialPost;
        $this->authorize('uploadMedia', $post);

        $this->mediaService->delete($media);

        return response()->json(['message' => 'Media deleted successfully']);
    }

    public function reorder(ReorderMediaRequest $request, SocialPost $post): AnonymousResourceCollection
    {
        $this->authorize('update', $post);

        $this->mediaService->reorder($post, $request->media_ids);

        return PostMediaResource::collection(
            $post->media()->ordered()->get()
        );
    }

    public function validate(Request $request, PostMedia $media, string $platform): JsonResponse
    {
        $errors = $this->mediaService->validateMediaForPlatform($media, $platform);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
}
