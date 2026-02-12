<?php

namespace Database\Factories;

use App\Models\SmContentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmContentPlanFactory extends Factory
{
    protected $model = SmContentPlan::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'sm_strategy_id' => null,
            'month' => fake()->numberBetween(1, 12),
            'year' => 2026,
            'status' => 'active',
            'summary' => null,
            'total_slots' => 20,
            'completed_slots' => 0,
            'generated_at' => now(),
        ];
    }
}
