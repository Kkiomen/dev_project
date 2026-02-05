<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevTaskTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'stopped_at' => $this->stopped_at?->toIso8601String(),
            'duration_minutes' => $this->duration,
            'formatted_duration' => $this->formatted_duration,
            'description' => $this->description,
            'is_running' => $this->is_running,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->public_id,
                'identifier' => $this->task->identifier,
                'title' => $this->task->title,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
