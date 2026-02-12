<?php

namespace Database\Factories;

use App\Models\SmMonitoredKeyword;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmMonitoredKeywordFactory extends Factory
{
    protected $model = SmMonitoredKeyword::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'keyword' => fake()->word(),
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube', null]),
            'category' => fake()->randomElement(['brand', 'competitor', 'industry', null]),
            'is_active' => true,
            'mention_count' => fake()->numberBetween(0, 500),
            'last_mention_at' => null,
        ];
    }
}
