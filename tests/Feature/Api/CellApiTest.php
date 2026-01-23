<?php

use App\Models\Base;
use App\Models\Table;
use App\Models\Field;
use App\Models\Row;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->base = Base::factory()->create(['user_id' => $this->user->id]);
    $this->table = Table::factory()->create(['base_id' => $this->base->id]);
    $this->row = Row::factory()->create(['table_id' => $this->table->id]);
});

describe('Cell API', function () {

    it('can update a text cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'text',
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => 'Hello World',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.value', 'Hello World');

        expect($this->row->getCellValue($field->id))->toBe('Hello World');
    });

    it('can update a number cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'number',
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => 42.5,
            ])
            ->assertSuccessful();

        expect($this->row->fresh()->getCellValue($field->id))->toBe(42.5);
    });

    it('can update a date cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'date',
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => '2024-06-15 10:30:00',
            ])
            ->assertSuccessful();

        expect($this->row->fresh()->getCellValue($field->id))->toContain('2024-06-15');
    });

    it('can update a checkbox cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'checkbox',
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => true,
            ])
            ->assertSuccessful();

        expect($this->row->fresh()->getCellValue($field->id))->toBeTrue();
    });

    it('can update a select cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'select',
            'options' => [
                'choices' => [
                    ['id' => 'choice1', 'name' => 'Option 1', 'color' => '#FF0000'],
                    ['id' => 'choice2', 'name' => 'Option 2', 'color' => '#00FF00'],
                ],
            ],
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => 'choice1',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.value.id', 'choice1')
            ->assertJsonPath('data.value.name', 'Option 1');
    });

    it('can update a multi_select cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'multi_select',
            'options' => [
                'choices' => [
                    ['id' => 'tag1', 'name' => 'Tag 1', 'color' => '#FF0000'],
                    ['id' => 'tag2', 'name' => 'Tag 2', 'color' => '#00FF00'],
                    ['id' => 'tag3', 'name' => 'Tag 3', 'color' => '#0000FF'],
                ],
            ],
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => ['tag1', 'tag3'],
            ])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.value');

        $value = $this->row->fresh()->getCellValue($field->id);
        expect($value)->toHaveCount(2);
        expect($value[0]['id'])->toBe('tag1');
    });

    it('can update a url cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'url',
        ]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => 'https://example.com',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.value', 'https://example.com');
    });

    it('can update a json cell', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'json',
        ]);

        $jsonData = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]];

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => $jsonData,
            ])
            ->assertSuccessful();

        expect($this->row->fresh()->getCellValue($field->id))->toBe($jsonData);
    });

    it('can clear a cell value', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'text',
        ]);

        $this->row->setCellValue($field->id, 'Initial Value');

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells/{$field->public_id}", [
                'value' => null,
            ])
            ->assertSuccessful();

        // Value should be cleared (empty or null)
        $value = $this->row->fresh()->getCellValue($field->id);
        expect($value === null || $value === '')->toBeTrue();
    });

    it('can bulk update cells', function () {
        $field1 = Field::factory()->create(['table_id' => $this->table->id, 'type' => 'text']);
        $field2 = Field::factory()->create(['table_id' => $this->table->id, 'type' => 'number']);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$this->row->public_id}/cells", [
                'values' => [
                    $field1->public_id => 'Bulk Text',
                    $field2->public_id => 123,
                ],
            ])
            ->assertSuccessful();

        $this->row->refresh();
        expect($this->row->getCellValue($field1->id))->toBe('Bulk Text');
        expect($this->row->getCellValue($field2->id))->toBe(123.0);
    });

});
