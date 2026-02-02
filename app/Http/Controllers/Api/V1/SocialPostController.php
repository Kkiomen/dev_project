<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Events\CalendarPostUpdated;
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

        $post->load(['platformPosts', 'media']);

        // Broadcast the update for real-time sync (e.g., verification page)
        broadcast(new CalendarPostUpdated($post));

        return new SocialPostResource($post);
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

    public function approve(Request $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('update', $post);

        if ($post->status !== PostStatus::PendingApproval && $post->status !== PostStatus::Draft) {
            abort(400, 'Post cannot be approved in current status');
        }

        $post->update([
            'status' => PostStatus::Approved,
        ]);

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }

    public function reject(Request $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('update', $post);

        $request->validate([
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($post->status !== PostStatus::PendingApproval) {
            abort(400, 'Only pending posts can be rejected');
        }

        $post->update([
            'status' => PostStatus::Draft,
        ]);

        // TODO: Store feedback in a related model if needed

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }

    public function batchApprove(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array', 'min:1'],
            'post_ids.*' => ['string'],
        ]);

        $posts = SocialPost::forUser($request->user())
            ->whereIn('public_id', $request->post_ids)
            ->whereIn('status', [PostStatus::Draft, PostStatus::PendingApproval])
            ->get();

        $approvedCount = 0;
        foreach ($posts as $post) {
            $post->update(['status' => PostStatus::Approved]);
            $approvedCount++;
        }

        return response()->json([
            'success' => true,
            'approved_count' => $approvedCount,
            'message' => "{$approvedCount} posts approved",
        ]);
    }

    public function batchReject(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array', 'min:1'],
            'post_ids.*' => ['string'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $posts = SocialPost::forUser($request->user())
            ->whereIn('public_id', $request->post_ids)
            ->where('status', PostStatus::PendingApproval)
            ->get();

        $rejectedCount = 0;
        foreach ($posts as $post) {
            $post->update(['status' => PostStatus::Draft]);
            $rejectedCount++;
        }

        return response()->json([
            'success' => true,
            'rejected_count' => $rejectedCount,
            'message' => "{$rejectedCount} posts rejected",
        ]);
    }

    public function pendingApproval(Request $request): AnonymousResourceCollection
    {
        $query = SocialPost::forUser($request->user())
            ->with(['platformPosts', 'media', 'brand'])
            ->withCount('media')
            ->whereIn('status', [PostStatus::Draft, PostStatus::PendingApproval]);

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('public_id', $request->brand_id);
            });
        }

        // Filter by date range
        if ($request->has('start')) {
            $query->where('scheduled_at', '>=', $request->start);
        }
        if ($request->has('end')) {
            $query->where('scheduled_at', '<=', $request->end);
        }

        $posts = $query->ordered()->paginate($request->get('per_page', 20));

        return SocialPostResource::collection($posts);
    }

    /**
     * Get verified (approved) posts ready for publishing.
     * Used by n8n to fetch posts that should be published to social media.
     */
    public function verified(Request $request): AnonymousResourceCollection
    {
        $query = SocialPost::forUser($request->user())
            ->with(['platformPosts', 'media', 'brand'])
            ->withCount('media')
            ->where('status', PostStatus::Approved);

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('public_id', $request->brand_id);
            });
        }

        // Filter by scheduled date (posts ready to publish now or in the past)
        if ($request->boolean('ready_to_publish')) {
            $query->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
        }

        // Filter by date range
        if ($request->has('start')) {
            $query->where('scheduled_at', '>=', $request->start);
        }
        if ($request->has('end')) {
            $query->where('scheduled_at', '<=', $request->end);
        }

        $posts = $query->ordered()->paginate($request->get('per_page', 50));

        return SocialPostResource::collection($posts);
    }

    /**
     * Mark post as published after successful publishing via n8n.
     */
    public function markPublished(Request $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('update', $post);

        if ($post->status !== PostStatus::Approved && $post->status !== PostStatus::Scheduled) {
            abort(400, __('posts.cannot_mark_published'));
        }

        $request->validate([
            'platform' => ['nullable', 'string', 'in:facebook,instagram,youtube'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'url', 'max:500'],
        ]);

        $post->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        // Update platform post if specified
        if ($request->has('platform')) {
            $platformPost = $post->platformPosts()->where('platform', $request->platform)->first();
            if ($platformPost) {
                $platformPost->update([
                    'publish_status' => 'published',
                    'published_at' => now(),
                    'external_id' => $request->external_id,
                    'external_url' => $request->external_url,
                ]);
            }
        }

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }

    /**
     * Mark post as failed after unsuccessful publishing via n8n.
     */
    public function markFailed(Request $request, SocialPost $post): SocialPostResource
    {
        $this->authorize('update', $post);

        $request->validate([
            'platform' => ['nullable', 'string', 'in:facebook,instagram,youtube'],
            'error_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $post->update([
            'status' => PostStatus::Failed,
        ]);

        // Update platform post if specified
        if ($request->has('platform')) {
            $platformPost = $post->platformPosts()->where('platform', $request->platform)->first();
            if ($platformPost) {
                $platformPost->update([
                    'publish_status' => 'failed',
                    'error_message' => $request->error_message,
                ]);
            }
        }

        return new SocialPostResource($post->load(['platformPosts', 'media']));
    }
}
