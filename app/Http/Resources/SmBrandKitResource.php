<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmBrandKitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'colors' => $this->colors ?? [],
            'fonts' => $this->fonts ?? [],
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'logo_dark_path' => $this->logo_dark_path,
            'logo_dark_url' => $this->logo_dark_path ? asset('storage/' . $this->logo_dark_path) : null,
            'style_preset' => $this->style_preset,
            'tone_of_voice' => $this->tone_of_voice,
            'voice_attributes' => $this->voice_attributes ?? [],
            'content_pillars' => $this->content_pillars ?? [],
            'hashtag_groups' => $this->hashtag_groups ?? [],
            'brand_guidelines_notes' => $this->brand_guidelines_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
