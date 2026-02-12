<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'platform' => $this->platform,
            'platform_label' => $this->getPlatformEnum()->label(),
            'platform_color' => $this->getPlatformEnum()->color(),
            'handle' => $this->handle,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status,
            'is_connected' => $this->isConnected(),
            'is_expired' => $this->isExpired(),
            'followers_count' => $this->getFollowersCount(),
            'token_expires_at' => $this->token_expires_at,
            'last_synced_at' => $this->last_synced_at,
            'created_at' => $this->created_at,
        ];
    }
}
