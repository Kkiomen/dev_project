<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardColumnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'board_id' => $this->board?->public_id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'card_limit' => $this->card_limit,
            'cards_count' => $this->whenCounted('cards'),
            'cards' => BoardCardResource::collection($this->whenLoaded('cards')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
