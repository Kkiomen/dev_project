<?php

namespace Database\Factories;

use App\Models\SmStrategy;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmStrategyFactory extends Factory
{
    protected $model = SmStrategy::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'content_pillars' => ['education', 'entertainment', 'promotion'],
            'posting_frequency' => ['instagram' => 5, 'facebook' => 3, 'linkedin' => 2],
            'target_audience' => ['age' => '25-35', 'interests' => ['tech', 'business']],
            'goals' => ['increase_engagement', 'grow_followers'],
            'competitor_handles' => ['@competitor1', '@competitor2'],
            'content_mix' => ['educational' => 40, 'promotional' => 30, 'entertaining' => 30],
            'optimal_times' => [
                'instagram' => ['09:00', '18:00'],
                'facebook' => ['12:00', '19:00'],
            ],
            'ai_recommendations' => null,
            'status' => 'draft',
            'activated_at' => null,
        ];
    }
}
