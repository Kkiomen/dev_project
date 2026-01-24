<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->type,
            'filename' => $this->filename,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => $this->file_size_for_humans,
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->getAspectRatio(),
            'position' => $this->position,
            'is_image' => $this->isImage(),
            'is_video' => $this->isVideo(),
            'created_at' => $this->created_at,
        ];
    }
}
