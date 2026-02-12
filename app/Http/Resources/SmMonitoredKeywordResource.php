<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmMonitoredKeywordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'keyword' => $this->keyword,
            'platform' => $this->platform,
            'category' => $this->category,
            'is_active' => $this->is_active,
            'mention_count' => $this->mention_count,
            'last_mention_at' => $this->last_mention_at,
            'created_at' => $this->created_at,
            'mentions' => $this->whenLoaded('mentions'),
        ];
    }
}
