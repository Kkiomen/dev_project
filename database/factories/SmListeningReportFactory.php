<?php

namespace Database\Factories;

use App\Models\SmListeningReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmListeningReportFactory extends Factory
{
    protected $model = SmListeningReport::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'share_of_voice' => ['brand' => 45, 'competitor_1' => 30, 'competitor_2' => 25],
            'sentiment_breakdown' => ['positive' => 60, 'neutral' => 30, 'negative' => 10],
            'top_mentions' => [],
            'trending_keywords' => [],
            'ai_summary' => null,
            'status' => 'ready',
            'generated_at' => now(),
        ];
    }
}
