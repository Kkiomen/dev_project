<?php

namespace Database\Factories;

use App\Enums\LayerType;
use App\Models\Layer;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

class LayerFactory extends Factory
{
    protected $model = Layer::class;

    public function definition(): array
    {
        $type = fake()->randomElement(LayerType::cases());

        return [
            'template_id' => Template::factory(),
            'name' => fake()->words(2, true),
            'type' => $type,
            'position' => fake()->numberBetween(0, 10),
            'visible' => true,
            'locked' => false,
            'x' => fake()->numberBetween(0, 500),
            'y' => fake()->numberBetween(0, 500),
            'width' => fake()->numberBetween(100, 400),
            'height' => fake()->numberBetween(100, 400),
            'rotation' => 0,
            'scale_x' => 1,
            'scale_y' => 1,
            'properties' => $type->defaultProperties(),
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LayerType::TEXT,
            'name' => 'Text Layer',
            'properties' => [
                'text' => fake()->sentence(),
                'fontFamily' => 'Arial',
                'fontSize' => 24,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'fill' => '#000000',
                'align' => 'left',
                'lineHeight' => 1.2,
            ],
        ]);
    }

    public function rectangle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LayerType::RECTANGLE,
            'name' => 'Rectangle',
            'properties' => [
                'fill' => fake()->hexColor(),
                'stroke' => null,
                'strokeWidth' => 0,
                'cornerRadius' => 0,
            ],
        ]);
    }

    public function ellipse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LayerType::ELLIPSE,
            'name' => 'Ellipse',
            'properties' => [
                'fill' => fake()->hexColor(),
                'stroke' => null,
                'strokeWidth' => 0,
            ],
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LayerType::IMAGE,
            'name' => 'Image',
            'properties' => [
                'src' => null,
                'fit' => 'cover',
            ],
        ]);
    }
}
