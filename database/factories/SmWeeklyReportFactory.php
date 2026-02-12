<?php

namespace Database\Factories;

use App\Models\SmWeeklyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmWeeklyReportFactory extends Factory
{
    protected $model = SmWeeklyReport::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'summary' => ['total_posts' => 10, 'total_engagement' => 500],
            'top_posts' => [],
            'recommendations' => 'Focus on video content',
            'growth_metrics' => ['followers_change' => 150, 'engagement_rate_change' => 0.5],
            'platform_breakdown' => [],
            'status' => 'ready',
            'generated_at' => now(),
        ];
    }
}
