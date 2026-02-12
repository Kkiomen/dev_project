<?php

namespace Database\Factories;

use App\Models\SmAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmAccountFactory extends Factory
{
    protected $model = SmAccount::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'platform_user_id' => fake()->uuid(),
            'handle' => fake()->userName(),
            'display_name' => fake()->name(),
            'avatar_url' => fake()->imageUrl(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => fake()->dateTimeBetween('now', '+1 year'),
            'metadata' => ['followers_count' => fake()->numberBetween(100, 100000)],
            'status' => 'active',
            'last_synced_at' => now(),
        ];
    }
}
