<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'token' => $this->when($this->shouldShowToken($request), $this->token),
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'expires_at' => $this->expires_at,
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'is_expired' => $this->isExpired(),
            'approval_url' => $this->getApprovalUrl(),
            'pending_count' => $this->whenCounted('approvals', fn() => $this->getPendingPostsCount()),
            'stats' => $this->when($this->relationLoaded('approvals'), fn() => [
                'total' => $this->approvals->count(),
                'pending' => $this->approvals->whereNull('is_approved')->count(),
                'approved' => $this->approvals->where('is_approved', true)->count(),
                'rejected' => $this->approvals->where('is_approved', false)->count(),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function shouldShowToken(Request $request): bool
    {
        // Show token only when just created or explicitly requested
        return $request->has('show_token') || $this->wasRecentlyCreated;
    }
}
