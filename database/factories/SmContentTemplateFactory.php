<?php

namespace Database\Factories;

use App\Models\SmContentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmContentTemplateFactory extends Factory
{
    protected $model = SmContentTemplate::class;

    public function definition(): array
    {
        return [
            'brand_id' => null,
            'name' => fake()->sentence(3),
            'category' => fake()->randomElement(['promotional', 'educational', 'engagement', 'announcement']),
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube', null]),
            'prompt_template' => 'Write a {{content_type}} post about {{topic}} for {{platform}}',
            'variables' => ['content_type', 'topic', 'platform'],
            'content_type' => fake()->randomElement(['post', 'carousel', 'reel', 'story']),
            'is_system' => false,
            'is_active' => true,
            'usage_count' => 0,
        ];
    }
}
