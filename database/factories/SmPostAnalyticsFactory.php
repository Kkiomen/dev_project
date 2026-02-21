<?php

namespace Database\Factories;

use App\Models\SmPostAnalytics;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmPostAnalyticsFactory extends Factory
{
    protected $model = SmPostAnalytics::class;

    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'likes' => fake()->numberBetween(0, 5000),
            'comments' => fake()->numberBetween(0, 500),
            'shares' => fake()->numberBetween(0, 200),
            'saves' => fake()->numberBetween(0, 300),
            'reach' => fake()->numberBetween(100, 50000),
            'impressions' => fake()->numberBetween(200, 100000),
            'clicks' => fake()->numberBetween(0, 2000),
            'video_views' => fake()->numberBetween(0, 50000),
            'engagement_rate' => fake()->randomFloat(4, 0, 15),
            'extra_metrics' => null,
            'collected_at' => now(),
        ];
    }
}
