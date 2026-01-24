<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'platform' => $this->platform->value,
            'platform_label' => $this->platform->label(),
            'platform_color' => $this->platform->color(),
            'enabled' => $this->enabled,
            'platform_caption' => $this->platform_caption,
            'effective_caption' => $this->getEffectiveCaption(),
            'has_override' => $this->hasOverride(),
            'video_title' => $this->video_title,
            'video_description' => $this->video_description,
            'hashtags' => $this->hashtags,
            'formatted_hashtags' => $this->getFormattedHashtags(),
            'link_preview' => $this->link_preview,
            'publish_status' => $this->publish_status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
