<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmPublishLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_post_id' => $this->scheduled_post_id,
            'action' => $this->action,
            'http_status' => $this->http_status,
            'error_message' => $this->error_message,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at,
        ];
    }
}
