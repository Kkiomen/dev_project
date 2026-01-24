<?php

namespace App\Events;

use App\Http\Resources\CalendarPostResource;
use App\Models\SocialPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CalendarPostCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SocialPost $post
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('brand.' . $this->post->brand->public_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.created';
    }

    public function broadcastWith(): array
    {
        $this->post->load(['platformPosts', 'media']);

        return [
            'post' => new CalendarPostResource($this->post),
        ];
    }
}
