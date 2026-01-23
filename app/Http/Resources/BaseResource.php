<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'tables_count' => $this->whenCounted('tables'),
            'tables' => TableResource::collection($this->whenLoaded('tables')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
