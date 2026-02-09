<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RssArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'feed_id' => $this->feed?->public_id,
            'feed_name' => $this->feed?->name,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'author' => $this->author,
            'image_url' => $this->image_url,
            'categories' => $this->categories,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
        ];
    }
}
