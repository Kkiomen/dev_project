<?php

use App\Models\Base;
use App\Models\Table;
use App\Models\Field;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->base = Base::factory()->create(['user_id' => $this->user->id]);
    $this->table = Table::factory()->create(['base_id' => $this->base->id]);
});

describe('Field API', function () {

    it('can list fields in a table', function () {
        Field::factory()->count(3)->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/tables/{$this->table->public_id}/fields")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'table_id', 'name', 'type', 'type_label', 'options', 'is_required', 'is_primary', 'position', 'width'],
                ],
            ]);
    });

    it('can create a text field', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/tables/{$this->table->public_id}/fields", [
                'name' => 'Description',
                'type' => 'text',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Description')
            ->assertJsonPath('data.type', 'text');

        $this->assertDatabaseHas('fields', [
            'table_id' => $this->table->id,
            'name' => 'Description',
            'type' => 'text',
        ]);
    });

    it('can create a select field with choices', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/tables/{$this->table->public_id}/fields", [
                'name' => 'Status',
                'type' => 'select',
                'options' => [
                    'choices' => [
                        ['name' => 'To Do', 'color' => '#EF4444'],
                        ['name' => 'In Progress', 'color' => '#F97316'],
                        ['name' => 'Done', 'color' => '#22C55E'],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Status')
            ->assertJsonPath('data.type', 'select')
            ->assertJsonCount(3, 'data.options.choices');

        $field = Field::where('name', 'Status')->first();
        expect($field->options['choices'])->toHaveCount(3);
        expect($field->options['choices'][0]['name'])->toBe('To Do');
        expect($field->options['choices'][0])->toHaveKey('id'); // ID should be auto-generated
    });

    it('can create all field types', function () {
        $types = ['text', 'number', 'date', 'checkbox', 'select', 'multi_select', 'attachment', 'url', 'json'];

        Sanctum::actingAs($this->user);
        foreach ($types as $type) {
            $this->postJson("/api/v1/tables/{$this->table->public_id}/fields", [
                    'name' => "Field {$type}",
                    'type' => $type,
                ])
                ->assertCreated()
                ->assertJsonPath('data.type', $type);
        }

        expect(Field::where('table_id', $this->table->id)->count())->toBe(9);
    });

    it('validates field type', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/tables/{$this->table->public_id}/fields", [
                'name' => 'Invalid',
                'type' => 'invalid_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('can update a field', function () {
        $field = Field::factory()->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/fields/{$field->public_id}", [
                'name' => 'Updated Name',
                'width' => 300,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.width', 300);
    });

    it('can add choice to select field', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'type' => 'select',
            'options' => ['choices' => []],
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/fields/{$field->public_id}/choices", [
                'name' => 'New Choice',
                'color' => '#3B82F6',
            ])
            ->assertOk();

        $field->refresh();
        expect($field->options['choices'])->toHaveCount(1);
        expect($field->options['choices'][0]['name'])->toBe('New Choice');
    });

    it('can delete a field', function () {
        $field = Field::factory()->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/fields/{$field->public_id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('fields', ['id' => $field->id]);
    });

    it('cannot delete the last primary field', function () {
        $field = Field::factory()->create([
            'table_id' => $this->table->id,
            'is_primary' => true,
        ]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/fields/{$field->public_id}")
            ->assertUnprocessable();
    });

});
