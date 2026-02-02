<?php

namespace App\Events;

use App\Http\Resources\SocialPostResource;
use App\Models\SocialPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CalendarPostUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SocialPost $post
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->post->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.updated';
    }

    public function broadcastWith(): array
    {
        $this->post->load(['platformPosts', 'media']);

        return [
            'post' => new SocialPostResource($this->post),
        ];
    }
}
