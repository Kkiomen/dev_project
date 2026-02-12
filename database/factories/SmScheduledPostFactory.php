<?php

namespace Database\Factories;

use App\Models\SmScheduledPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmScheduledPostFactory extends Factory
{
    protected $model = SmScheduledPost::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'social_post_id' => null,
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 week'),
            'published_at' => null,
            'status' => 'draft',
            'approval_status' => 'pending',
            'approval_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
            'retry_count' => 0,
            'max_retries' => 3,
            'error_message' => null,
            'external_post_id' => null,
            'platform_response' => null,
        ];
    }
}
