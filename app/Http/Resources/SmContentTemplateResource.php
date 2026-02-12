<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmContentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'category' => $this->category,
            'platform' => $this->platform,
            'prompt_template' => $this->prompt_template,
            'variables' => $this->variables,
            'content_type' => $this->content_type,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at,
        ];
    }
}
