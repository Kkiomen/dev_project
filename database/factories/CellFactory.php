<?php

namespace Database\Factories;

use App\Models\Cell;
use App\Models\Field;
use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

class CellFactory extends Factory
{
    protected $model = Cell::class;

    public function definition(): array
    {
        return [
            'row_id' => Row::factory(),
            'field_id' => Field::factory(),
        ];
    }
}
