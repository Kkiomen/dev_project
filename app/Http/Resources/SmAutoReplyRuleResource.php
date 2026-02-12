<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmAutoReplyRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'trigger_type' => $this->trigger_type,
            'trigger_value' => $this->trigger_value,
            'response_template' => $this->response_template,
            'requires_approval' => $this->requires_approval,
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at,
        ];
    }
}
