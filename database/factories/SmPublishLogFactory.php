<?php

namespace Database\Factories;

use App\Models\SmPublishLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmPublishLogFactory extends Factory
{
    protected $model = SmPublishLog::class;

    public function definition(): array
    {
        return [
            'sm_scheduled_post_id' => 1,
            'action' => 'publish',
            'http_status' => 200,
            'request_payload' => [],
            'response_payload' => ['id' => 'ext_123'],
            'error_message' => null,
            'duration_ms' => fake()->numberBetween(200, 5000),
        ];
    }
}
