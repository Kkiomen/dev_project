<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'scheduled_date' => $this->scheduled_date->format('Y-m-d'),
            'scheduled_time' => $this->scheduled_time,
            'title' => $this->title,
            'keywords' => $this->keywords ?? [],
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'social_post_id' => $this->socialPost?->public_id,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
