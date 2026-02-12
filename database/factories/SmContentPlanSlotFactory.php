<?php

namespace Database\Factories;

use App\Models\SmContentPlanSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmContentPlanSlotFactory extends Factory
{
    protected $model = SmContentPlanSlot::class;

    public function definition(): array
    {
        return [
            'sm_content_plan_id' => 1,
            'scheduled_date' => fake()->dateTimeBetween('now', '+1 month'),
            'scheduled_time' => '10:00',
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'content_type' => fake()->randomElement(['post', 'carousel', 'reel', 'story']),
            'topic' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'pillar' => fake()->randomElement(['education', 'entertainment', 'promotion']),
            'status' => 'planned',
            'social_post_id' => null,
            'position' => 0,
        ];
    }
}
