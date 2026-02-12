<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmPostAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'social_post_id' => $this->social_post_id,
            'platform' => $this->platform,
            'likes' => $this->likes,
            'comments' => $this->comments,
            'shares' => $this->shares,
            'saves' => $this->saves,
            'reach' => $this->reach,
            'impressions' => $this->impressions,
            'clicks' => $this->clicks,
            'video_views' => $this->video_views,
            'engagement_rate' => $this->engagement_rate,
            'total_engagement' => $this->getTotalEngagement(),
            'extra_metrics' => $this->extra_metrics,
            'collected_at' => $this->collected_at,
        ];
    }
}
