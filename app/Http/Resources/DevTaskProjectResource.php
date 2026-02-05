<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevTaskProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prefix' => $this->prefix,
            'name' => $this->name,
            'next_sequence' => $this->next_sequence,
            'tasks_count' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at,
        ];
    }
}
