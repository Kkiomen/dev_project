<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Cell;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'cell_id' => Cell::factory(),
            'filename' => fake()->word() . '.jpg',
            'path' => 'attachments/test/' . fake()->uuid() . '.jpg',
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 1000000),
            'width' => 800,
            'height' => 600,
            'thumbnail_path' => null,
            'metadata' => null,
            'position' => 0,
        ];
    }

    public function image(): static
    {
        return $this->state([
            'mime_type' => 'image/jpeg',
            'width' => 800,
            'height' => 600,
        ]);
    }

    public function pdf(): static
    {
        return $this->state([
            'filename' => fake()->word() . '.pdf',
            'path' => 'attachments/test/' . fake()->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}
