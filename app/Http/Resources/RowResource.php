<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $values = [];

        if ($this->relationLoaded('cells')) {
            $cells = $this->cells->keyBy('field_id');
            $fields = $this->table->fields;

            foreach ($fields as $field) {
                $cell = $cells->get($field->id);
                $values[$field->public_id] = $cell?->getValue() ?? $field->getDefaultValue();
            }
        }

        return [
            'id' => $this->public_id,
            'table_id' => $this->table->public_id,
            'position' => $this->position,
            'values' => $values,
            'cells' => CellResource::collection($this->whenLoaded('cells')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
