<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePlatformPostRequest;
use App\Http\Resources\PlatformPostResource;
use App\Models\SocialPost;
use App\Services\ContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformPostController extends Controller
{
    public function __construct(
        protected ContentSyncService $contentSyncService
    ) {}

    public function update(
        UpdatePlatformPostRequest $request,
        SocialPost $post,
        string $platform
    ): PlatformPostResource {
        $this->authorize('update', $post);

        $platformEnum = Platform::tryFrom($platform);

        if (!$platformEnum) {
            abort(404, 'Platform not found');
        }

        $platformPost = $post->platformPosts()
            ->where('platform', $platformEnum->value)
            ->firstOrFail();

        $platformPost->update($request->validated());

        return new PlatformPostResource($platformPost);
    }

    public function sync(Request $request, SocialPost $post, string $platform): PlatformPostResource
    {
        $this->authorize('update', $post);

        $platformEnum = Platform::tryFrom($platform);

        if (!$platformEnum) {
            abort(404, 'Platform not found');
        }

        $platformPost = $post->platformPosts()
            ->where('platform', $platformEnum->value)
            ->firstOrFail();

        // Reset override and re-sync from main
        $platformPost->syncFromMain();

        // Re-apply platform-specific formatting
        $formatted = match ($platformEnum) {
            Platform::Instagram => $this->contentSyncService->formatForInstagram($post->main_caption),
            Platform::Facebook => $this->contentSyncService->formatForFacebook($post->main_caption),
            Platform::YouTube => $this->contentSyncService->formatForYouTube($post->main_caption),
        };

        $platformPost->update($formatted);

        return new PlatformPostResource($platformPost);
    }

    public function toggle(Request $request, SocialPost $post, string $platform): JsonResponse
    {
        $this->authorize('update', $post);

        $platformEnum = Platform::tryFrom($platform);

        if (!$platformEnum) {
            abort(404, 'Platform not found');
        }

        $platformPost = $post->platformPosts()
            ->where('platform', $platformEnum->value)
            ->firstOrFail();

        $platformPost->update(['enabled' => !$platformPost->enabled]);

        return response()->json([
            'enabled' => $platformPost->enabled,
            'message' => $platformPost->enabled ? 'Platform enabled' : 'Platform disabled',
        ]);
    }
}
