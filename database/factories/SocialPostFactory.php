<?php

namespace Database\Factories;

use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialPostFactory extends Factory
{
    protected $model = SocialPost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'main_caption' => fake()->paragraph(),
            'status' => 'draft',
            'scheduled_at' => null,
            'published_at' => null,
            'settings' => null,
            'position' => 0,
        ];
    }
}
