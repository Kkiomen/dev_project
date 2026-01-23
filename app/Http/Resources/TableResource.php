<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'base_id' => $this->base->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'position' => $this->position,
            'fields_count' => $this->whenCounted('fields'),
            'rows_count' => $this->whenCounted('rows'),
            'fields' => FieldResource::collection($this->whenLoaded('fields')),
            'rows' => RowResource::collection($this->whenLoaded('rows')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
