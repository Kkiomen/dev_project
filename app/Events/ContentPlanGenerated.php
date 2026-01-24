<?php

namespace App\Events;

use App\Models\Brand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPlanGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Brand $brand,
        public array $plan,
        public array $posts
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('brand.' . $this->brand->public_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'content-plan.generated';
    }

    public function broadcastWith(): array
    {
        return [
            'brand_id' => $this->brand->public_id,
            'posts_count' => count($this->posts),
            'plan' => $this->plan,
        ];
    }
}
