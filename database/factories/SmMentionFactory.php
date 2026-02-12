<?php

namespace Database\Factories;

use App\Models\SmMention;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmMentionFactory extends Factory
{
    protected $model = SmMention::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'sm_monitored_keyword_id' => null,
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'source_url' => fake()->url(),
            'author_handle' => fake()->userName(),
            'author_name' => fake()->name(),
            'text' => fake()->sentence(),
            'sentiment' => fake()->randomElement(['positive', 'neutral', 'negative']),
            'reach' => fake()->numberBetween(0, 50000),
            'engagement' => fake()->numberBetween(0, 5000),
            'mentioned_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
