<?php

namespace Database\Factories;

use App\Models\SmGeneratedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmGeneratedAssetFactory extends Factory
{
    protected $model = SmGeneratedAsset::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'social_post_id' => null,
            'sm_design_template_id' => null,
            'type' => fake()->randomElement(['image', 'video', 'carousel_slide']),
            'file_path' => 'generated/fake-asset.png',
            'thumbnail_path' => null,
            'disk' => 'public',
            'width' => 1080,
            'height' => 1080,
            'mime_type' => 'image/png',
            'file_size' => fake()->numberBetween(50000, 500000),
            'generation_prompt' => fake()->sentence(),
            'ai_provider' => 'openai',
            'ai_model' => 'dall-e-3',
            'generation_params' => [],
            'status' => 'completed',
            'error_message' => null,
            'position' => 0,
        ];
    }
}
