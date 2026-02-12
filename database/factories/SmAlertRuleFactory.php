<?php

namespace Database\Factories;

use App\Models\SmAlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmAlertRuleFactory extends Factory
{
    protected $model = SmAlertRule::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'alert_type' => fake()->randomElement(['mention_spike', 'negative_sentiment', 'competitor_mention']),
            'threshold' => fake()->numberBetween(5, 50),
            'timeframe' => '1_hour',
            'notify_via' => ['email'],
            'is_active' => true,
            'last_triggered_at' => null,
        ];
    }
}
