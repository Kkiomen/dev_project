<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'email_verified_at' => $this->email_verified_at,
            'brands_count' => $this->whenCounted('brands'),
            'posts_count' => $this->whenCounted('socialPosts'),
            'notifications_count' => $this->whenCounted('notifications'),
            'brands' => $this->whenLoaded('brands'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
