<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmContentPlanSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'scheduled_time' => $this->scheduled_time,
            'platform' => $this->platform,
            'content_type' => $this->content_type,
            'topic' => $this->topic,
            'description' => $this->description,
            'pillar' => $this->pillar,
            'status' => $this->status,
            'has_content' => $this->hasContent(),
            'social_post_id' => $this->socialPost?->public_id,
            'position' => $this->position,
            'created_at' => $this->created_at,
        ];
    }
}
