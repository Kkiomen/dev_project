<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'filename' => $this->filename,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_formatted' => $this->size_formatted,
            'is_image' => $this->is_image,
            'is_pdf' => $this->is_pdf,
            'width' => $this->width,
            'height' => $this->height,
            'position' => $this->position,
            'created_at' => $this->created_at,
        ];
    }
}
