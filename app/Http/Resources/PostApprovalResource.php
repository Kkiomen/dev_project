<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'is_approved' => $this->is_approved,
            'is_pending' => $this->isPending(),
            'feedback_notes' => $this->feedback_notes,
            'responded_at' => $this->responded_at,
            'social_post' => new SocialPostResource($this->whenLoaded('socialPost')),
            'token' => new ApprovalTokenResource($this->whenLoaded('approvalToken')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
