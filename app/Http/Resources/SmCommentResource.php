<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'platform' => $this->platform,
            'external_post_id' => $this->external_post_id,
            'author_handle' => $this->author_handle,
            'author_name' => $this->author_name,
            'author_avatar' => $this->author_avatar,
            'text' => $this->text,
            'sentiment' => $this->sentiment,
            'is_replied' => $this->is_replied,
            'reply_text' => $this->reply_text,
            'replied_at' => $this->replied_at,
            'is_hidden' => $this->is_hidden,
            'is_flagged' => $this->is_flagged,
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}
