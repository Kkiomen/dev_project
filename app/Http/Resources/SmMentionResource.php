<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmMentionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'platform' => $this->platform,
            'source_url' => $this->source_url,
            'author_handle' => $this->author_handle,
            'author_name' => $this->author_name,
            'text' => $this->text,
            'sentiment' => $this->sentiment,
            'reach' => $this->reach,
            'engagement' => $this->engagement,
            'mentioned_at' => $this->mentioned_at,
            'created_at' => $this->created_at,
            'keyword' => $this->whenLoaded('keyword'),
        ];
    }
}
