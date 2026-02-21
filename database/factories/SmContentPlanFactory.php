<?php

namespace Database\Factories;

use App\Models\SmContentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmContentPlanFactory extends Factory
{
    protected $model = SmContentPlan::class;

    private static int $monthSequence = 0;

    public function definition(): array
    {
        self::$monthSequence++;
        $month = ((self::$monthSequence - 1) % 12) + 1;
        $year = 2026 + intdiv(self::$monthSequence - 1, 12);

        return [
            'brand_id' => 1,
            'sm_strategy_id' => null,
            'month' => $month,
            'year' => $year,
            'status' => 'active',
            'summary' => null,
            'total_slots' => 20,
            'completed_slots' => 0,
            'generated_at' => now(),
        ];
    }
}
