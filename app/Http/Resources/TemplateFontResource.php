<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateFontResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template->public_id,
            'font_family' => $this->font_family,
            'font_file' => $this->font_file,
            'font_url' => asset('storage/' . $this->font_file),
            'font_weight' => $this->font_weight,
            'font_style' => $this->font_style,
            'font_face_name' => $this->font_face_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
