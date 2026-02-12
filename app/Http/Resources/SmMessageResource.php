<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'platform' => $this->platform,
            'external_thread_id' => $this->external_thread_id,
            'from_handle' => $this->from_handle,
            'from_name' => $this->from_name,
            'from_avatar' => $this->from_avatar,
            'text' => $this->text,
            'direction' => $this->direction,
            'is_read' => $this->is_read,
            'auto_replied' => $this->auto_replied,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
