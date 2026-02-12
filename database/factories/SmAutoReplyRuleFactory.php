<?php

namespace Database\Factories;

use App\Models\SmAutoReplyRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmAutoReplyRuleFactory extends Factory
{
    protected $model = SmAutoReplyRule::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'trigger_type' => fake()->randomElement(['keyword', 'sentiment', 'all']),
            'trigger_value' => 'thanks',
            'response_template' => 'Thank you for your message! We will get back to you soon.',
            'requires_approval' => false,
            'is_active' => true,
            'usage_count' => 0,
        ];
    }
}
