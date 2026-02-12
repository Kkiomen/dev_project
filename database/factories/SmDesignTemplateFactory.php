<?php

namespace Database\Factories;

use App\Models\SmDesignTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmDesignTemplateFactory extends Factory
{
    protected $model = SmDesignTemplate::class;

    public function definition(): array
    {
        return [
            'brand_id' => null,
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(['post', 'story', 'carousel', 'cover']),
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube', null]),
            'canvas_json' => ['width' => 1080, 'height' => 1080, 'elements' => []],
            'width' => 1080,
            'height' => 1080,
            'thumbnail_path' => null,
            'category' => fake()->randomElement(['promotional', 'educational', 'engagement']),
            'tags' => ['social', 'template'],
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
