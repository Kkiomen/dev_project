<?php

namespace Database\Factories;

use App\Models\Base;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'base_id' => Base::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'position' => 0,
        ];
    }
}
