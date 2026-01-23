<?php

use App\Models\Base;
use App\Models\Table;
use App\Models\Field;
use App\Models\Row;
use App\Models\User;
use App\Services\RowSortService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->base = Base::factory()->create(['user_id' => $this->user->id]);
    $this->table = Table::factory()->create(['base_id' => $this->base->id]);
    $this->sortService = app(RowSortService::class);
});

describe('RowSortService', function () {

    describe('text field sorting', function () {

        beforeEach(function () {
            $this->textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Name',
                'type' => 'text',
            ]);

            $this->rowA = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            $this->rowA->setCellValue($this->textField->id, 'Charlie');

            $this->rowB = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            $this->rowB->setCellValue($this->textField->id, 'Alice');

            $this->rowC = Row::factory()->create(['table_id' => $this->table->id, 'position' => 2]);
            $this->rowC->setCellValue($this->textField->id, 'Bob');
        });

        it('sorts ascending by text field', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->textField->public_id, 'direction' => 'asc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->rowB->id); // Alice
            expect($result[1]->id)->toBe($this->rowC->id); // Bob
            expect($result[2]->id)->toBe($this->rowA->id); // Charlie
        });

        it('sorts descending by text field', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->textField->public_id, 'direction' => 'desc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->rowA->id); // Charlie
            expect($result[1]->id)->toBe($this->rowC->id); // Bob
            expect($result[2]->id)->toBe($this->rowB->id); // Alice
        });

    });

    describe('number field sorting', function () {

        beforeEach(function () {
            $this->numberField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Amount',
                'type' => 'number',
            ]);

            $this->row100 = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            $this->row100->setCellValue($this->numberField->id, 100);

            $this->row300 = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            $this->row300->setCellValue($this->numberField->id, 300);

            $this->row200 = Row::factory()->create(['table_id' => $this->table->id, 'position' => 2]);
            $this->row200->setCellValue($this->numberField->id, 200);
        });

        it('sorts ascending by number field', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->numberField->public_id, 'direction' => 'asc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->row100->id); // 100
            expect($result[1]->id)->toBe($this->row200->id); // 200
            expect($result[2]->id)->toBe($this->row300->id); // 300
        });

        it('sorts descending by number field', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->numberField->public_id, 'direction' => 'desc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->row300->id); // 300
            expect($result[1]->id)->toBe($this->row200->id); // 200
            expect($result[2]->id)->toBe($this->row100->id); // 100
        });

    });

    describe('date field sorting', function () {

        beforeEach(function () {
            $this->dateField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Created',
                'type' => 'date',
            ]);

            $this->rowOld = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            $this->rowOld->setCellValue($this->dateField->id, '2024-01-15');

            $this->rowNew = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            $this->rowNew->setCellValue($this->dateField->id, '2024-03-20');

            $this->rowMid = Row::factory()->create(['table_id' => $this->table->id, 'position' => 2]);
            $this->rowMid->setCellValue($this->dateField->id, '2024-02-10');
        });

        it('sorts ascending by date field', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->dateField->public_id, 'direction' => 'asc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->rowOld->id); // Jan
            expect($result[1]->id)->toBe($this->rowMid->id); // Feb
            expect($result[2]->id)->toBe($this->rowNew->id); // Mar
        });

        it('sorts descending by date field', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->dateField->public_id, 'direction' => 'desc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->rowNew->id); // Mar
            expect($result[1]->id)->toBe($this->rowMid->id); // Feb
            expect($result[2]->id)->toBe($this->rowOld->id); // Jan
        });

    });

    describe('null value handling', function () {

        beforeEach(function () {
            $this->textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Name',
                'type' => 'text',
            ]);

            $this->rowWithValue = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            $this->rowWithValue->setCellValue($this->textField->id, 'Alice');

            $this->rowNull = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            // No value set

            $this->rowWithValue2 = Row::factory()->create(['table_id' => $this->table->id, 'position' => 2]);
            $this->rowWithValue2->setCellValue($this->textField->id, 'Bob');
        });

        it('places null values last when sorting ascending', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->textField->public_id, 'direction' => 'asc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->rowWithValue->id); // Alice
            expect($result[1]->id)->toBe($this->rowWithValue2->id); // Bob
            expect($result[2]->id)->toBe($this->rowNull->id); // null
        });

        it('places null values first when sorting descending', function () {
            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $this->textField->public_id, 'direction' => 'desc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->id)->toBe($this->rowNull->id); // null
            expect($result[1]->id)->toBe($this->rowWithValue2->id); // Bob
            expect($result[2]->id)->toBe($this->rowWithValue->id); // Alice
        });

    });

    describe('default behavior', function () {

        it('uses default position ordering when no sort provided', function () {
            Row::factory()->create(['table_id' => $this->table->id, 'position' => 2]);
            Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);

            $query = $this->table->rows();
            $sortData = [];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result)->toHaveCount(3);
            expect($result[0]->position)->toBe(0);
            expect($result[1]->position)->toBe(1);
            expect($result[2]->position)->toBe(2);
        });

        it('uses asc as default direction', function () {
            $textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'type' => 'text',
            ]);

            $rowC = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            $rowC->setCellValue($textField->id, 'Charlie');

            $rowA = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            $rowA->setCellValue($textField->id, 'Alice');

            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $textField->public_id], // no direction specified
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result[0]->id)->toBe($rowA->id); // Alice first (ascending)
        });

    });

    describe('edge cases', function () {

        it('ignores invalid field_id', function () {
            Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);

            $query = $this->table->rows();
            $sortData = [
                ['field_id' => 'invalid_field_id', 'direction' => 'asc'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            // Should fall back to position ordering
            expect($result)->toHaveCount(2);
            expect($result[0]->position)->toBe(0);
            expect($result[1]->position)->toBe(1);
        });

        it('normalizes invalid direction to asc', function () {
            $textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'type' => 'text',
            ]);

            $rowB = Row::factory()->create(['table_id' => $this->table->id, 'position' => 0]);
            $rowB->setCellValue($textField->id, 'B');

            $rowA = Row::factory()->create(['table_id' => $this->table->id, 'position' => 1]);
            $rowA->setCellValue($textField->id, 'A');

            $query = $this->table->rows();
            $sortData = [
                ['field_id' => $textField->public_id, 'direction' => 'invalid'],
            ];

            $result = $this->sortService->apply($query, $this->table, $sortData)->get();

            expect($result[0]->id)->toBe($rowA->id); // A first (ascending)
        });

    });

});
