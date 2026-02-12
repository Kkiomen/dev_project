<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmPerformanceScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'social_post_id' => $this->social_post_id,
            'score' => $this->score,
            'score_label' => $this->getScoreLabel(),
            'analysis' => $this->analysis,
            'recommendations' => $this->recommendations,
            'ai_model' => $this->ai_model,
            'created_at' => $this->created_at,
        ];
    }
}
