<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmDesignTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'type' => $this->type,
            'platform' => $this->platform,
            'canvas_json' => $this->canvas_json,
            'width' => $this->width,
            'height' => $this->height,
            'dimensions' => $this->getDimensions(),
            'thumbnail_url' => $this->getThumbnailUrl(),
            'category' => $this->category,
            'tags' => $this->tags,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
