<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmGeneratedAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->type,
            'url' => $this->getUrl(),
            'thumbnail_url' => $this->getThumbnailUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'generation_prompt' => $this->generation_prompt,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'status' => $this->status,
            'error_message' => $this->when($this->isFailed(), $this->error_message),
            'position' => $this->position,
            'template' => $this->when(
                $this->relationLoaded('designTemplate') && $this->designTemplate,
                fn () => new SmDesignTemplateResource($this->designTemplate)
            ),
            'created_at' => $this->created_at,
        ];
    }
}
