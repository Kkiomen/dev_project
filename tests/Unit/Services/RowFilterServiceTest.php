<?php

use App\Models\Base;
use App\Models\Table;
use App\Models\Field;
use App\Models\Row;
use App\Models\User;
use App\Services\RowFilterService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->base = Base::factory()->create(['user_id' => $this->user->id]);
    $this->table = Table::factory()->create(['base_id' => $this->base->id]);
    $this->filterService = app(RowFilterService::class);
});

describe('RowFilterService', function () {

    describe('text field filtering', function () {

        beforeEach(function () {
            $this->textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Name',
                'type' => 'text',
            ]);

            // Create test rows
            $this->row1 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row1->setCellValue($this->textField->id, 'John Doe');

            $this->row2 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row2->setCellValue($this->textField->id, 'Jane Smith');

            $this->row3 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row3->setCellValue($this->textField->id, 'Bob Johnson');

            $this->row4 = Row::factory()->create(['table_id' => $this->table->id]);
            // row4 has no value (empty)
        });

        it('filters with equals operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'equals', 'value' => 'John Doe'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row1->id);
        });

        it('filters with not_equals operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'not_equals', 'value' => 'John Doe'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2);
            expect($result->pluck('id')->toArray())->not->toContain($this->row1->id);
        });

        it('filters with contains operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'contains', 'value' => 'John'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2); // John Doe and Bob Johnson
        });

        it('filters with not_contains operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'not_contains', 'value' => 'John'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row2->id);
        });

        it('filters with starts_with operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'starts_with', 'value' => 'J'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2); // John Doe and Jane Smith
        });

        it('filters with ends_with operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'ends_with', 'value' => 'son'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row3->id);
        });

        it('filters with is_empty operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'is_empty'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row4->id);
        });

        it('filters with is_not_empty operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'is_not_empty'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(3);
        });

    });

    describe('number field filtering', function () {

        beforeEach(function () {
            $this->numberField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Amount',
                'type' => 'number',
            ]);

            $this->row1 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row1->setCellValue($this->numberField->id, 100);

            $this->row2 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row2->setCellValue($this->numberField->id, 250);

            $this->row3 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row3->setCellValue($this->numberField->id, 500);
        });

        it('filters with greater_than operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->numberField->public_id, 'operator' => 'greater_than', 'value' => 200],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2);
        });

        it('filters with less_than operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->numberField->public_id, 'operator' => 'less_than', 'value' => 200],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row1->id);
        });

        it('filters with greater_or_equal operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->numberField->public_id, 'operator' => 'greater_or_equal', 'value' => 250],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2);
        });

        it('filters with between operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->numberField->public_id, 'operator' => 'between', 'value' => [150, 400]],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row2->id);
        });

    });

    describe('checkbox field filtering', function () {

        beforeEach(function () {
            $this->checkboxField = Field::factory()->create([
                'table_id' => $this->table->id,
                'name' => 'Active',
                'type' => 'checkbox',
            ]);

            $this->row1 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row1->setCellValue($this->checkboxField->id, true);

            $this->row2 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row2->setCellValue($this->checkboxField->id, false);

            $this->row3 = Row::factory()->create(['table_id' => $this->table->id]);
            // row3 has no checkbox value
        });

        it('filters with is_true operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->checkboxField->public_id, 'operator' => 'is_true'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row1->id);
        });

        it('filters with is_false operator', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->checkboxField->public_id, 'operator' => 'is_false'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2); // false and no value
        });

    });

    describe('conjunction logic', function () {

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

            $this->row1 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row1->setCellValue($this->textField->id, 'John');
            $this->row1->setCellValue($this->numberField->id, 100);

            $this->row2 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row2->setCellValue($this->textField->id, 'Jane');
            $this->row2->setCellValue($this->numberField->id, 200);

            $this->row3 = Row::factory()->create(['table_id' => $this->table->id]);
            $this->row3->setCellValue($this->textField->id, 'John');
            $this->row3->setCellValue($this->numberField->id, 300);
        });

        it('applies AND conjunction by default', function () {
            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'equals', 'value' => 'John'],
                    ['field_id' => $this->numberField->public_id, 'operator' => 'greater_than', 'value' => 150],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row3->id);
        });

        it('applies explicit AND conjunction', function () {
            $query = $this->table->rows();
            $filterData = [
                'conjunction' => 'and',
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'equals', 'value' => 'John'],
                    ['field_id' => $this->numberField->public_id, 'operator' => 'less_than', 'value' => 200],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->row1->id);
        });

        it('applies OR conjunction', function () {
            $query = $this->table->rows();
            $filterData = [
                'conjunction' => 'or',
                'conditions' => [
                    ['field_id' => $this->textField->public_id, 'operator' => 'equals', 'value' => 'Jane'],
                    ['field_id' => $this->numberField->public_id, 'operator' => 'equals', 'value' => 100],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2); // Jane (200) + John (100)
        });

    });

    describe('edge cases', function () {

        it('returns all rows when no conditions provided', function () {
            Row::factory()->count(3)->create(['table_id' => $this->table->id]);

            $query = $this->table->rows();
            $filterData = ['conditions' => []];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(3);
        });

        it('ignores invalid field_id', function () {
            Row::factory()->count(2)->create(['table_id' => $this->table->id]);

            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => 'invalid_field_id', 'operator' => 'equals', 'value' => 'test'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2); // All rows returned, filter ignored
        });

        it('ignores invalid operator', function () {
            $textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'type' => 'text',
            ]);
            Row::factory()->count(2)->create(['table_id' => $this->table->id]);

            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    ['field_id' => $textField->public_id, 'operator' => 'invalid_operator', 'value' => 'test'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2);
        });

        it('ignores operators not allowed for field type', function () {
            $textField = Field::factory()->create([
                'table_id' => $this->table->id,
                'type' => 'text',
            ]);
            Row::factory()->count(2)->create(['table_id' => $this->table->id]);

            $query = $this->table->rows();
            $filterData = [
                'conditions' => [
                    // is_true is not valid for text fields
                    ['field_id' => $textField->public_id, 'operator' => 'is_true'],
                ],
            ];

            $result = $this->filterService->apply($query, $this->table, $filterData)->get();

            expect($result)->toHaveCount(2);
        });

    });

});
