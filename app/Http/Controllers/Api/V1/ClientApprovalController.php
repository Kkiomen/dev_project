<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApprovalResponseRequest;
use App\Http\Resources\PostApprovalResource;
use App\Http\Resources\SocialPostResource;
use App\Models\ApprovalToken;
use App\Models\PostApproval;
use App\Models\SocialPost;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    public function validate(string $token): JsonResponse
    {
        $approvalToken = $this->approvalService->validateToken($token);

        if (!$approvalToken) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        return response()->json([
            'valid' => true,
            'client_name' => $approvalToken->client_name,
            'expires_at' => $approvalToken->expires_at,
        ]);
    }

    public function posts(string $token): AnonymousResourceCollection|JsonResponse
    {
        $approvalToken = $this->approvalService->validateToken($token);

        if (!$approvalToken) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $posts = $this->approvalService->getPendingPostsForToken($approvalToken);

        return SocialPostResource::collection($posts);
    }

    public function show(string $token, SocialPost $post): SocialPostResource|JsonResponse
    {
        $approvalToken = $this->approvalService->validateToken($token);

        if (!$approvalToken) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // Verify the post belongs to the token's user
        if ($post->user_id !== $approvalToken->user_id) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        // Verify there's an approval record for this post and token
        $approval = PostApproval::where('social_post_id', $post->id)
            ->where('approval_token_id', $approvalToken->id)
            ->first();

        if (!$approval) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        return new SocialPostResource(
            $post->load(['platformPosts', 'media', 'approvals' => function ($query) use ($approvalToken) {
                $query->where('approval_token_id', $approvalToken->id);
            }])
        );
    }

    public function respond(ApprovalResponseRequest $request, string $token, SocialPost $post): JsonResponse
    {
        $approvalToken = $this->approvalService->validateToken($token);

        if (!$approvalToken) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // Verify the post belongs to the token's user
        if ($post->user_id !== $approvalToken->user_id) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        // Find the approval record
        $approval = PostApproval::where('social_post_id', $post->id)
            ->where('approval_token_id', $approvalToken->id)
            ->first();

        if (!$approval) {
            return response()->json([
                'message' => 'Approval record not found',
            ], 404);
        }

        if (!$approval->isPending()) {
            return response()->json([
                'message' => 'This post has already been reviewed',
            ], 422);
        }

        $this->approvalService->submitResponse(
            $approval,
            $request->boolean('approved'),
            $request->get('notes')
        );

        return response()->json([
            'message' => $request->boolean('approved')
                ? 'Post approved successfully'
                : 'Feedback submitted successfully',
            'is_approved' => $request->boolean('approved'),
        ]);
    }

    public function history(string $token): AnonymousResourceCollection|JsonResponse
    {
        $approvalToken = $this->approvalService->validateToken($token);

        if (!$approvalToken) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $history = $this->approvalService->getApprovalHistory($approvalToken);

        return PostApprovalResource::collection($history);
    }
}
