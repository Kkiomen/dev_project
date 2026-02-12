<?php

namespace Database\Factories;

use App\Models\SmMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmMessageFactory extends Factory
{
    protected $model = SmMessage::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'external_thread_id' => fake()->uuid(),
            'from_handle' => fake()->userName(),
            'from_name' => fake()->name(),
            'from_avatar' => null,
            'text' => fake()->sentence(),
            'direction' => fake()->randomElement(['inbound', 'outbound']),
            'is_read' => false,
            'auto_replied' => false,
            'sent_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
