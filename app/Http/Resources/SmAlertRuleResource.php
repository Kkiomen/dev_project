<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmAlertRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'alert_type' => $this->alert_type,
            'threshold' => $this->threshold,
            'timeframe' => $this->timeframe,
            'notify_via' => $this->notify_via,
            'is_active' => $this->is_active,
            'last_triggered_at' => $this->last_triggered_at,
            'created_at' => $this->created_at,
        ];
    }
}
