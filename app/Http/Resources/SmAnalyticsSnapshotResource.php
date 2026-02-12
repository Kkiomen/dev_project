<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmAnalyticsSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'platform' => $this->platform,
            'snapshot_date' => $this->snapshot_date,
            'followers' => $this->followers,
            'following' => $this->following,
            'reach' => $this->reach,
            'impressions' => $this->impressions,
            'profile_views' => $this->profile_views,
            'website_clicks' => $this->website_clicks,
            'engagement_rate' => $this->engagement_rate,
            'posts_count' => $this->posts_count,
            'extra_metrics' => $this->extra_metrics,
        ];
    }
}
