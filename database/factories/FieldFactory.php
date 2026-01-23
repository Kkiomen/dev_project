<?php

namespace Database\Factories;

use App\Enums\FieldType;
use App\Models\Field;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldFactory extends Factory
{
    protected $model = Field::class;

    public function definition(): array
    {
        return [
            'table_id' => Table::factory(),
            'name' => fake()->word(),
            'type' => FieldType::TEXT,
            'options' => null,
            'is_required' => false,
            'is_primary' => false,
            'position' => 0,
            'width' => 200,
        ];
    }

    public function text(): static
    {
        return $this->state(['type' => FieldType::TEXT]);
    }

    public function number(): static
    {
        return $this->state(['type' => FieldType::NUMBER]);
    }

    public function date(): static
    {
        return $this->state(['type' => FieldType::DATE]);
    }

    public function checkbox(): static
    {
        return $this->state(['type' => FieldType::CHECKBOX]);
    }

    public function select(): static
    {
        return $this->state([
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'color' => '#EF4444'],
                    ['id' => 'opt2', 'name' => 'Option 2', 'color' => '#22C55E'],
                ],
            ],
        ]);
    }

    public function multiSelect(): static
    {
        return $this->state([
            'type' => FieldType::MULTI_SELECT,
            'options' => [
                'choices' => [
                    ['id' => 'tag1', 'name' => 'Tag 1', 'color' => '#EF4444'],
                    ['id' => 'tag2', 'name' => 'Tag 2', 'color' => '#22C55E'],
                ],
            ],
        ]);
    }

    public function attachment(): static
    {
        return $this->state(['type' => FieldType::ATTACHMENT]);
    }

    public function url(): static
    {
        return $this->state(['type' => FieldType::URL]);
    }

    public function json(): static
    {
        return $this->state(['type' => FieldType::JSON]);
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }
}
