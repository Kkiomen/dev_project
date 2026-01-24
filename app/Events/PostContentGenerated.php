<?php

namespace App\Events;

use App\Http\Resources\SocialPostResource;
use App\Models\SocialPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostContentGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SocialPost $post,
        public array $content
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->post->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post-content.generated';
    }

    public function broadcastWith(): array
    {
        $this->post->load(['platformPosts', 'media']);

        return [
            'post' => new SocialPostResource($this->post),
            'content' => $this->content,
        ];
    }
}
