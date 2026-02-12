<?php

namespace Database\Factories;

use App\Models\SmAnalyticsSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmAnalyticsSnapshotFactory extends Factory
{
    protected $model = SmAnalyticsSnapshot::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'snapshot_date' => today(),
            'followers' => fake()->numberBetween(100, 100000),
            'following' => fake()->numberBetween(50, 5000),
            'reach' => fake()->numberBetween(500, 50000),
            'impressions' => fake()->numberBetween(1000, 100000),
            'profile_views' => fake()->numberBetween(10, 5000),
            'website_clicks' => fake()->numberBetween(0, 1000),
            'engagement_rate' => fake()->randomFloat(4, 0, 15),
            'posts_count' => fake()->numberBetween(0, 50),
            'extra_metrics' => null,
        ];
    }
}
