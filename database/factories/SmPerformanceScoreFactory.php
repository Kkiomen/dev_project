<?php

namespace Database\Factories;

use App\Models\SmPerformanceScore;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmPerformanceScoreFactory extends Factory
{
    protected $model = SmPerformanceScore::class;

    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'score' => fake()->numberBetween(0, 100),
            'analysis' => ['engagement' => 'good', 'reach' => 'average'],
            'recommendations' => 'Post at different times',
            'ai_model' => 'gpt-4o',
        ];
    }
}
