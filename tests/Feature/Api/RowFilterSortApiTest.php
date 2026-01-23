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
});

describe('Row API - Filtering', function () {

    beforeEach(function () {
        $this->textField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Name',
            'type' => 'text',
        ]);
        $this->numberField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Amount',
            'type' => 'number',
        ]);
        $this->checkboxField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Active',
            'type' => 'checkbox',
        ]);

        // Create test rows
        $this->rowJohn = Row::factory()->create(['table_id' => $this->table->id]);
        $this->rowJohn->setCellValue($this->textField->id, 'John Doe');
        $this->rowJohn->setCellValue($this->numberField->id, 100);
        $this->rowJohn->setCellValue($this->checkboxField->id, true);

        $this->rowJane = Row::factory()->create(['table_id' => $this->table->id]);
        $this->rowJane->setCellValue($this->textField->id, 'Jane Smith');
        $this->rowJane->setCellValue($this->numberField->id, 250);
        $this->rowJane->setCellValue($this->checkboxField->id, false);

        $this->rowBob = Row::factory()->create(['table_id' => $this->table->id]);
        $this->rowBob->setCellValue($this->textField->id, 'Bob Johnson');
        $this->rowBob->setCellValue($this->numberField->id, 500);
        $this->rowBob->setCellValue($this->checkboxField->id, true);
    });

    it('can filter rows by text contains', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->textField->public_id, 'operator' => 'contains', 'value' => 'John'],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // John Doe and Bob Johnson
    });

    it('can filter rows by text equals', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->textField->public_id, 'operator' => 'equals', 'value' => 'John Doe'],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->rowJohn->public_id);
    });

    it('can filter rows by number greater_than', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->numberField->public_id, 'operator' => 'greater_than', 'value' => 200],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // Jane (250) and Bob (500)
    });

    it('can filter rows by number between', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->numberField->public_id, 'operator' => 'between', 'value' => [150, 400]],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->rowJane->public_id);
    });

    it('can filter rows by checkbox is_true', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->checkboxField->public_id, 'operator' => 'is_true'],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // John and Bob
    });

    it('can filter with AND conjunction', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conjunction' => 'and',
            'conditions' => [
                ['field_id' => $this->checkboxField->public_id, 'operator' => 'is_true'],
                ['field_id' => $this->numberField->public_id, 'operator' => 'greater_than', 'value' => 200],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->rowBob->public_id);
    });

    it('can filter with OR conjunction', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conjunction' => 'or',
            'conditions' => [
                ['field_id' => $this->textField->public_id, 'operator' => 'equals', 'value' => 'John Doe'],
                ['field_id' => $this->numberField->public_id, 'operator' => 'equals', 'value' => 500],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // John (by name) and Bob (by amount)
    });

    it('returns all rows when no filters provided', function () {
        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('handles is_empty filter', function () {
        // Create a row without text value
        $emptyRow = Row::factory()->create(['table_id' => $this->table->id]);
        $emptyRow->setCellValue($this->numberField->id, 999);
        // textField is not set

        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->textField->public_id, 'operator' => 'is_empty'],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $emptyRow->public_id);
    });

    it('handles is_not_empty filter', function () {
        // Create a row without text value
        Row::factory()->create(['table_id' => $this->table->id]);

        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->textField->public_id, 'operator' => 'is_not_empty'],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertOk()
            ->assertJsonCount(3, 'data'); // Only the original 3 rows with text values
    });

});

describe('Row API - Sorting', function () {

    beforeEach(function () {
        $this->textField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Name',
            'type' => 'text',
        ]);
        $this->numberField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Amount',
            'type' => 'number',
        ]);

        $this->rowCharlie = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
        $this->rowCharlie->setCellValue($this->textField->id, 'Charlie');
        $this->rowCharlie->setCellValue($this->numberField->id, 300);

        $this->rowAlice = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
        $this->rowAlice->setCellValue($this->textField->id, 'Alice');
        $this->rowAlice->setCellValue($this->numberField->id, 100);

        $this->rowBob = Row::factory()->create(['table_id' => $this->table->id, 'position' => 2]);
        $this->rowBob->setCellValue($this->textField->id, 'Bob');
        $this->rowBob->setCellValue($this->numberField->id, 200);
    });

    it('can sort rows by text field ascending', function () {
        Sanctum::actingAs($this->user);

        $sort = json_encode([
            ['field_id' => $this->textField->public_id, 'direction' => 'asc'],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?sort={$sort}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->rowAlice->public_id);
        expect($ids[1])->toBe($this->rowBob->public_id);
        expect($ids[2])->toBe($this->rowCharlie->public_id);
    });

    it('can sort rows by text field descending', function () {
        Sanctum::actingAs($this->user);

        $sort = json_encode([
            ['field_id' => $this->textField->public_id, 'direction' => 'desc'],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?sort={$sort}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->rowCharlie->public_id);
        expect($ids[1])->toBe($this->rowBob->public_id);
        expect($ids[2])->toBe($this->rowAlice->public_id);
    });

    it('can sort rows by number field ascending', function () {
        Sanctum::actingAs($this->user);

        $sort = json_encode([
            ['field_id' => $this->numberField->public_id, 'direction' => 'asc'],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?sort={$sort}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->rowAlice->public_id); // 100
        expect($ids[1])->toBe($this->rowBob->public_id);   // 200
        expect($ids[2])->toBe($this->rowCharlie->public_id); // 300
    });

    it('can sort rows by number field descending', function () {
        Sanctum::actingAs($this->user);

        $sort = json_encode([
            ['field_id' => $this->numberField->public_id, 'direction' => 'desc'],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?sort={$sort}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->rowCharlie->public_id); // 300
        expect($ids[1])->toBe($this->rowBob->public_id);   // 200
        expect($ids[2])->toBe($this->rowAlice->public_id); // 100
    });

    it('uses default position ordering when no sort provided', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->rowCharlie->public_id); // position 0
        expect($ids[1])->toBe($this->rowAlice->public_id);   // position 1
        expect($ids[2])->toBe($this->rowBob->public_id);     // position 2
    });

});

describe('Row API - Filter and Sort combined', function () {

    beforeEach(function () {
        $this->textField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Name',
            'type' => 'text',
        ]);
        $this->numberField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Amount',
            'type' => 'number',
        ]);

        // Create test rows
        $this->row1 = Row::factory()->create(['table_id' => $this->table->id]);
        $this->row1->setCellValue($this->textField->id, 'Alpha');
        $this->row1->setCellValue($this->numberField->id, 100);

        $this->row2 = Row::factory()->create(['table_id' => $this->table->id]);
        $this->row2->setCellValue($this->textField->id, 'Beta');
        $this->row2->setCellValue($this->numberField->id, 200);

        $this->row3 = Row::factory()->create(['table_id' => $this->table->id]);
        $this->row3->setCellValue($this->textField->id, 'Gamma');
        $this->row3->setCellValue($this->numberField->id, 300);

        $this->row4 = Row::factory()->create(['table_id' => $this->table->id]);
        $this->row4->setCellValue($this->textField->id, 'Delta');
        $this->row4->setCellValue($this->numberField->id, 150);
    });

    it('can filter and sort at the same time', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $this->numberField->public_id, 'operator' => 'greater_than', 'value' => 120],
            ],
        ]);

        $sort = json_encode([
            ['field_id' => $this->numberField->public_id, 'direction' => 'desc'],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}&sort={$sort}")
            ->assertOk()
            ->assertJsonCount(3, 'data'); // Beta (200), Gamma (300), Delta (150)

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->row3->public_id); // Gamma - 300
        expect($ids[1])->toBe($this->row2->public_id); // Beta - 200
        expect($ids[2])->toBe($this->row4->public_id); // Delta - 150
    });

    it('can filter multiple conditions and sort', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conjunction' => 'and',
            'conditions' => [
                ['field_id' => $this->numberField->public_id, 'operator' => 'greater_than', 'value' => 100],
                ['field_id' => $this->numberField->public_id, 'operator' => 'less_than', 'value' => 250],
            ],
        ]);

        $sort = json_encode([
            ['field_id' => $this->textField->public_id, 'direction' => 'asc'],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}&sort={$sort}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // Beta (200) and Delta (150)

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        expect($ids[0])->toBe($this->row2->public_id); // Beta (alphabetically first)
        expect($ids[1])->toBe($this->row4->public_id); // Delta
    });

});

describe('Row API - Filter validation', function () {

    it('validates filter operator', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => 'some_field', 'operator' => 'invalid_operator', 'value' => 'test'],
            ],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertStatus(422);
    });

    it('validates sort direction', function () {
        Sanctum::actingAs($this->user);

        $sort = json_encode([
            ['field_id' => 'some_field', 'direction' => 'invalid'],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?sort={$sort}")
            ->assertStatus(422);
    });

    it('validates conjunction value', function () {
        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conjunction' => 'invalid',
            'conditions' => [],
        ]);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}")
            ->assertStatus(422);
    });

    it('handles malformed JSON gracefully', function () {
        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters=not_valid_json")
            ->assertOk(); // Should not error, just ignore invalid filter
    });

});

describe('Row API - Pagination with filters', function () {

    it('paginates filtered results', function () {
        $textField = Field::factory()->create([
            'table_id' => $this->table->id,
            'name' => 'Category',
            'type' => 'text',
        ]);

        // Create 15 rows with category 'A'
        for ($i = 0; $i < 15; $i++) {
            $row = Row::factory()->create(['table_id' => $this->table->id]);
            $row->setCellValue($textField->id, 'Category A');
        }

        // Create 5 rows with category 'B'
        for ($i = 0; $i < 5; $i++) {
            $row = Row::factory()->create(['table_id' => $this->table->id]);
            $row->setCellValue($textField->id, 'Category B');
        }

        Sanctum::actingAs($this->user);

        $filters = json_encode([
            'conditions' => [
                ['field_id' => $textField->public_id, 'operator' => 'equals', 'value' => 'Category A'],
            ],
        ]);

        $response = $this->getJson("/api/v1/tables/{$this->table->public_id}/rows?filters={$filters}&per_page=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15);
    });

});
