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
    $this->textField = Field::factory()->create([
        'table_id' => $this->table->id,
        'name' => 'Name',
        'type' => 'text',
        'is_primary' => true,
    ]);
    $this->numberField = Field::factory()->create([
        'table_id' => $this->table->id,
        'name' => 'Amount',
        'type' => 'number',
    ]);
});

describe('Row API', function () {

    it('can list rows in a table', function () {
        Row::factory()->count(3)->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'table_id', 'position', 'values', 'created_at', 'updated_at'],
                ],
            ]);
    });

    it('can create a row with values', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/tables/{$this->table->public_id}/rows", [
                'values' => [
                    $this->textField->public_id => 'Test Name',
                    $this->numberField->public_id => 123.45,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath("data.values.{$this->textField->public_id}", 'Test Name');

        $this->assertDatabaseHas('rows', ['table_id' => $this->table->id]);

        $row = Row::where('table_id', $this->table->id)->first();
        expect($row->getCellValue($this->textField->id))->toBe('Test Name');
    });

    it('can create an empty row', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/tables/{$this->table->public_id}/rows", [])
            ->assertCreated();

        $this->assertDatabaseHas('rows', ['table_id' => $this->table->id]);
    });

    it('can show a row with all values', function () {
        $row = Row::factory()->create(['table_id' => $this->table->id]);
        $row->setCellValue($this->textField->id, 'Test Value');

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/rows/{$row->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $row->public_id)
            ->assertJsonPath("data.values.{$this->textField->public_id}", 'Test Value');
    });

    it('can update a row', function () {
        $row = Row::factory()->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/rows/{$row->public_id}", [
                'values' => [
                    $this->textField->public_id => 'Updated Name',
                    $this->numberField->public_id => 999,
                ],
            ])
            ->assertOk()
            ->assertJsonPath("data.values.{$this->textField->public_id}", 'Updated Name');

        $row->refresh();
        expect($row->getCellValue($this->textField->id))->toBe('Updated Name');
        expect($row->getCellValue($this->numberField->id))->toBe(999.0);
    });

    it('can delete a row', function () {
        $row = Row::factory()->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/rows/{$row->public_id}")
            ->assertNoContent();

        $this->assertSoftDeleted('rows', ['id' => $row->id]);
    });

    it('can bulk create rows', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/tables/{$this->table->public_id}/rows/bulk", [
                'rows' => [
                    ['values' => [$this->textField->public_id => 'Row 1']],
                    ['values' => [$this->textField->public_id => 'Row 2']],
                    ['values' => [$this->textField->public_id => 'Row 3']],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data');

        expect(Row::where('table_id', $this->table->id)->count())->toBe(3);
    });

    it('can bulk delete rows', function () {
        $rows = Row::factory()->count(3)->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/tables/{$this->table->public_id}/rows/bulk", [
                'ids' => $rows->pluck('public_id')->toArray(),
            ])
            ->assertNoContent();

        expect(Row::where('table_id', $this->table->id)->whereNull('deleted_at')->count())->toBe(0);
    });

});
