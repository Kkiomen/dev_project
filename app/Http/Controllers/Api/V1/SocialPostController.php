<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RescheduleSocialPostRequest;
use App\Http\Requests\Api\StoreSocialPostRequest;
use App\Http\Requests\Api\UpdateSocialPostRequest;
use App\Http\Resources\CalendarPostResource;
use App\Http\Resources\SocialPostResource;
use App\Models\ApprovalToken;
use App\Models\SocialPost;
use App\Services\ApprovalService;
use App\Services\ContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SocialPostController extends Controller
{
    public function __construct(
        protected ContentSyncService $contentSyncService,
        protected ApprovalService $approvalService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SocialPost::forUser($request->user())
            ->with(['platformPosts', 'media'])
            ->withCount('media');

        // Filter by status
        if ($request->has('status')) {
            $status = PostStatus::tryFrom($request->get('status'));
            if ($status) {
                $query->withStatus($status);
            }
        }

        // Filter by date range
        if ($request->has('start') && $request->has('end')) {
            $query->scheduledBetween($request->get('start'), $request->get('end'));
        }

        $posts = $query->ordered()->paginate($request->get('per_page', 20));

        return SocialPostResource::collection($posts);
    }

    public function calendar(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $posts = SocialPost::forUser($request->user())
            ->with(['platformPosts', 'media'])
            ->withCount('media')
            ->scheduledBetween($request->get('start'), $request->get('end'))
            ->ordered()
            ->get();

        return CalendarPostResource::collection($posts);
    }

    public function store(StoreSocialPostRequest $request): SocialPostResource
    {
        $post = $request->user()->socialPosts()->create([
            'title' => $request->title,
            'main_caption' => $request->main_caption,
            'scheduled_at' => $request->scheduled_at,
            'settings' => $request->settings,
            'status' => PostStatus::Draft,
        ]);

        // Create platform posts
        $post->createPlatformPosts();

        // Sync content to platforms
        $this->contentSyncService->syncToPlatforms($post);

        // Enable/disable platforms based on request
        if ($request->has('platforms')) {
            foreach ($post->platformPosts as $platformPost) {
                $platformPost->update([
                    'enabled' => in_array($platformPost->platform->value, $request->platforms),
                ]);
            }
        }

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }

    public function show(Request $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('view', $post);

        return new SocialPostResource(
            $post->load(['platformPosts', 'media', 'approvals.approvalToken'])
        );
    }

    public function update(UpdateSocialPostRequest $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('update', $post);

        $post->update($request->validated());

        // Re-sync to platforms if main caption changed
        if ($request->has('main_caption')) {
            $this->contentSyncService->syncToPlatforms($post);
        }

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }

    public function destroy(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function reschedule(RescheduleSocialPostRequest $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('update', $post);

        $post->update([
            'scheduled_at' => $request->scheduled_at,
            'status' => $post->status === PostStatus::Approved ? PostStatus::Scheduled : $post->status,
        ]);

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }

    public function duplicate(Request $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('duplicate', $post);

        $newPost = $post->duplicate();

        return new SocialPostResource($newPost);
    }

    public function requestApproval(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorize('requestApproval', $post);

        $request->validate([
            'token_id' => ['required', 'string', 'exists:approval_tokens,public_id'],
        ]);

        $token = ApprovalToken::findByPublicIdOrFail($request->token_id);

        if ($token->user_id !== $request->user()->id) {
            abort(403, 'Invalid approval token');
        }

        $approval = $this->approvalService->requestApproval($post, $token);

        return response()->json([
            'message' => 'Approval requested successfully',
            'approval_url' => $token->getApprovalUrl(),
        ]);
    }

    public function publish(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorize('update', $post);

        $request->validate([
            'platform' => ['required', 'string', 'in:facebook,instagram,youtube'],
        ]);

        $platform = $request->platform;

        // Find the platform post
        $platformPost = $post->platformPosts()->where('platform', $platform)->first();

        if (! $platformPost) {
            return response()->json(['message' => 'Platform not found for this post'], 404);
        }

        if (! $platformPost->enabled) {
            return response()->json(['message' => 'Platform is not enabled for this post'], 400);
        }

        // Update status to scheduled/publishing
        $post->update([
            'status' => PostStatus::Scheduled,
        ]);

        // TODO: Dispatch job to actually publish to the platform
        // For now, we just mark it as scheduled

        return response()->json([
            'success' => true,
            'message' => 'Post scheduled for publishing to '.$platform,
            'data' => new SocialPostResource($post->load(['platformPosts', 'media'])),
        ]);
    }
}
