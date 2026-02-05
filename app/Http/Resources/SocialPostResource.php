<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'text_prompt' => $this->text_prompt,
            'main_caption' => $this->main_caption,
            'image_prompt' => $this->image_prompt,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'scheduled_at' => $this->scheduled_at,
            'published_at' => $this->published_at,
            'settings' => $this->settings,
            'position' => $this->position,
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'can_schedule' => $this->canSchedule(),
            'first_media_url' => $this->getFirstMediaUrl(),
            'first_media_id' => $this->getFirstMediaId(),
            'enabled_platforms' => $this->getEnabledPlatforms(),
            'platform_posts' => PlatformPostResource::collection($this->whenLoaded('platformPosts')),
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
            'media_count' => $this->whenCounted('media'),
            'approvals' => PostApprovalResource::collection($this->whenLoaded('approvals')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
