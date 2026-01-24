<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\ApprovalToken;
use App\Models\PostApproval;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Support\Str;

class ApprovalService
{
    public function createToken(User $user, array $data): ApprovalToken
    {
        return ApprovalToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
        ]);
    }

    public function validateToken(string $token): ?ApprovalToken
    {
        return ApprovalToken::findValidByToken($token);
    }

    public function requestApproval(SocialPost $post, ApprovalToken $token): PostApproval
    {
        // Update post status to pending approval
        $post->update(['status' => PostStatus::PendingApproval]);

        // Create or update approval record
        return PostApproval::updateOrCreate(
            [
                'social_post_id' => $post->id,
                'approval_token_id' => $token->id,
            ],
            [
                'is_approved' => null,
                'feedback_notes' => null,
                'responded_at' => null,
            ]
        );
    }

    public function submitResponse(PostApproval $approval, bool $approved, ?string $notes = null): PostApproval
    {
        return $approval->respond($approved, $notes);
    }

    public function getPendingPostsForToken(ApprovalToken $token): \Illuminate\Database\Eloquent\Collection
    {
        return SocialPost::query()
            ->where('user_id', $token->user_id)
            ->where('status', PostStatus::PendingApproval)
            ->whereHas('approvals', function ($query) use ($token) {
                $query->where('approval_token_id', $token->id)
                    ->whereNull('is_approved');
            })
            ->with(['platformPosts', 'media', 'approvals' => function ($query) use ($token) {
                $query->where('approval_token_id', $token->id);
            }])
            ->ordered()
            ->get();
    }

    public function getApprovalHistory(ApprovalToken $token, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return PostApproval::query()
            ->where('approval_token_id', $token->id)
            ->whereNotNull('responded_at')
            ->with(['socialPost.platformPosts', 'socialPost.media'])
            ->orderByDesc('responded_at')
            ->limit($limit)
            ->get();
    }

    public function revokeToken(ApprovalToken $token): void
    {
        $token->revoke();
    }

    public function regenerateToken(ApprovalToken $token): ApprovalToken
    {
        return $token->regenerate();
    }

    public function bulkRequestApproval(array $postIds, ApprovalToken $token): array
    {
        $approvals = [];

        foreach ($postIds as $postId) {
            $post = SocialPost::findByPublicIdOrFail($postId);

            if ($post->user_id !== $token->user_id) {
                continue;
            }

            $approvals[] = $this->requestApproval($post, $token);
        }

        return $approvals;
    }

    public function getTokenStats(ApprovalToken $token): array
    {
        $approvals = $token->approvals();

        return [
            'total' => $approvals->count(),
            'pending' => $approvals->clone()->pending()->count(),
            'approved' => $approvals->clone()->approved()->count(),
            'rejected' => $approvals->clone()->rejected()->count(),
        ];
    }
}
