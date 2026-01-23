<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'width' => fake()->randomElement([1080, 1200, 1920]),
            'height' => fake()->randomElement([1080, 1200, 1920]),
            'background_color' => fake()->hexColor(),
        ];
    }

    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => 1080,
            'height' => 1080,
            'name' => 'Instagram Post',
        ]);
    }

    public function story(): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => 1080,
            'height' => 1920,
            'name' => 'Instagram Story',
        ]);
    }
}
