<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $values = [];
        $fields = [];

        if ($this->relationLoaded('cells')) {
            $cells = $this->cells->keyBy('field_id');
            // Always sort fields by position to ensure consistent order
            $tableFields = $this->table->fields->sortBy('position');

            foreach ($tableFields as $field) {
                $cell = $cells->get($field->id);
                $value = $cell?->getValue() ?? $field->getDefaultValue();
                $values[$field->public_id] = $value;

                // Add field info with camelCase name key for easier access
                $nameKey = \Illuminate\Support\Str::camel($field->name);
                $fields[$nameKey] = [
                    'field_id' => $field->public_id,
                    'name' => $field->name,
                    'type' => $field->type->value,
                    'value' => $value,
                    'cell_id' => $cell?->id,
                ];
            }
        }

        return [
            'id' => $this->public_id,
            'table_id' => $this->table->public_id,
            'position' => $this->position,
            'values' => $values,
            'fields' => $fields,
            'cells' => CellResource::collection($this->whenLoaded('cells')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
