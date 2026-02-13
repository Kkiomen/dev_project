<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmStrategyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'active_platforms' => $this->active_platforms,
            'content_pillars' => $this->content_pillars,
            'posting_frequency' => $this->posting_frequency,
            'target_audience' => $this->target_audience,
            'goals' => $this->goals,
            'competitor_handles' => $this->competitor_handles,
            'content_mix' => $this->content_mix,
            'optimal_times' => $this->optimal_times,
            'ai_recommendations' => $this->ai_recommendations,
            'content_language' => $this->brand->getLanguage(),
            'status' => $this->status,
            'total_weekly_posts' => $this->getTotalWeeklyPosts(),
            'activated_at' => $this->activated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
