<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'template_id' => $this->template->public_id,
            'modifications' => $this->modifications,
            'file_path' => $this->file_path,
            'file_url' => $this->url,
            'file_size' => $this->file_size,
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
