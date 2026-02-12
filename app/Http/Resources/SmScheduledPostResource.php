<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmScheduledPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'brand_id' => $this->brand_id,
            'social_post_id' => $this->social_post_id,
            'platform' => $this->platform,
            'scheduled_at' => $this->scheduled_at,
            'published_at' => $this->published_at,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'approval_notes' => $this->approval_notes,
            'approved_at' => $this->approved_at,
            'retry_count' => $this->retry_count,
            'max_retries' => $this->max_retries,
            'error_message' => $this->error_message,
            'external_post_id' => $this->external_post_id,
            'created_at' => $this->created_at,
            'social_post' => $this->whenLoaded('socialPost'),
            'approver' => $this->whenLoaded('approver'),
        ];
    }
}
