<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'table_id' => $this->table->public_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon' => $this->type->icon(),
            'options' => $this->options,
            'is_required' => $this->is_required,
            'is_primary' => $this->is_primary,
            'position' => $this->position,
            'width' => $this->width,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
