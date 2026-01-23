<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'base_id' => $this->base?->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'background_color' => $this->background_color,
            'background_image' => $this->background_image,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null,
            'settings' => $this->settings,
            'position' => $this->position,
            'layers_count' => $this->whenCounted('layers'),
            'layers' => LayerResource::collection($this->whenLoaded('layers')),
            'fonts' => TemplateFontResource::collection($this->whenLoaded('fonts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
