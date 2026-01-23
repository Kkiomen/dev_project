<?php

namespace Database\Factories;

use App\Models\Base;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BaseFactory extends Factory
{
    protected $model = Base::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'icon' => 'database',
        ];
    }
}
