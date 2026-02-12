<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmCrisisAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'severity' => $this->severity,
            'trigger_type' => $this->trigger_type,
            'description' => $this->description,
            'related_items' => $this->related_items,
            'is_resolved' => $this->is_resolved,
            'resolved_at' => $this->resolved_at,
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at,
        ];
    }
}
