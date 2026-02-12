<?php

namespace Database\Factories;

use App\Models\SmCrisisAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmCrisisAlertFactory extends Factory
{
    protected $model = SmCrisisAlert::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'trigger_type' => fake()->randomElement(['negative_sentiment_spike', 'mention_spike', 'flagged_comments']),
            'description' => fake()->sentence(),
            'related_items' => [],
            'is_resolved' => false,
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }
}
