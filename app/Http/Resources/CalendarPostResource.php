<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'scheduled_at' => $this->scheduled_at,
            'scheduled_date' => $this->scheduled_at?->format('Y-m-d'),
            'scheduled_time' => $this->scheduled_at?->format('H:i'),
            'first_media_url' => $this->getFirstMediaUrl(),
            'enabled_platforms' => $this->getEnabledPlatforms(),
            'media_count' => $this->media_count ?? $this->media->count(),
        ];
    }
}
