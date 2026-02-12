<?php

namespace Database\Factories;

use App\Models\SmBrandKit;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmBrandKitFactory extends Factory
{
    protected $model = SmBrandKit::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'colors' => [
                'primary' => fake()->hexColor(),
                'secondary' => fake()->hexColor(),
                'accent' => fake()->hexColor(),
            ],
            'fonts' => [
                'heading' => ['family' => 'Inter', 'weight' => 700],
                'body' => ['family' => 'Open Sans', 'weight' => 400],
            ],
            'logo_path' => null,
            'logo_dark_path' => null,
            'style_preset' => 'modern',
            'tone_of_voice' => 'professional',
            'voice_attributes' => ['informative', 'friendly'],
            'content_pillars' => ['education', 'industry_news'],
            'hashtag_groups' => [
                'branded' => ['#brand'],
                'industry' => ['#tech'],
            ],
            'brand_guidelines_notes' => null,
        ];
    }
}
