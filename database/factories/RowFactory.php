<?php

namespace Database\Factories;

use App\Models\Row;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class RowFactory extends Factory
{
    protected $model = Row::class;

    public function definition(): array
    {
        return [
            'table_id' => Table::factory(),
            'position' => 0,
        ];
    }
}
