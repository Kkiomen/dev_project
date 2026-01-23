<?php

use App\Enums\FieldType;
use App\Enums\FilterOperator;

describe('FilterOperator Enum', function () {

    it('has all expected operators', function () {
        $operators = FilterOperator::cases();

        expect($operators)->toHaveCount(23);
        expect(FilterOperator::EQUALS->value)->toBe('equals');
        expect(FilterOperator::CONTAINS->value)->toBe('contains');
        expect(FilterOperator::BETWEEN->value)->toBe('between');
        expect(FilterOperator::IS_TRUE->value)->toBe('is_true');
    });

    describe('forFieldType()', function () {

        it('returns correct operators for text field', function () {
            $operators = FilterOperator::forFieldType(FieldType::TEXT);

            expect($operators)->toContain(FilterOperator::EQUALS);
            expect($operators)->toContain(FilterOperator::CONTAINS);
            expect($operators)->toContain(FilterOperator::STARTS_WITH);
            expect($operators)->toContain(FilterOperator::ENDS_WITH);
            expect($operators)->toContain(FilterOperator::IS_EMPTY);
            expect($operators)->not->toContain(FilterOperator::IS_TRUE);
            expect($operators)->not->toContain(FilterOperator::BETWEEN);
        });

        it('returns correct operators for number field', function () {
            $operators = FilterOperator::forFieldType(FieldType::NUMBER);

            expect($operators)->toContain(FilterOperator::EQUALS);
            expect($operators)->toContain(FilterOperator::GREATER_THAN);
            expect($operators)->toContain(FilterOperator::LESS_THAN);
            expect($operators)->toContain(FilterOperator::BETWEEN);
            expect($operators)->not->toContain(FilterOperator::CONTAINS);
        });

        it('returns correct operators for date field', function () {
            $operators = FilterOperator::forFieldType(FieldType::DATE);

            expect($operators)->toContain(FilterOperator::BEFORE);
            expect($operators)->toContain(FilterOperator::AFTER);
            expect($operators)->toContain(FilterOperator::ON_OR_BEFORE);
            expect($operators)->toContain(FilterOperator::BETWEEN);
        });

        it('returns correct operators for checkbox field', function () {
            $operators = FilterOperator::forFieldType(FieldType::CHECKBOX);

            expect($operators)->toHaveCount(2);
            expect($operators)->toContain(FilterOperator::IS_TRUE);
            expect($operators)->toContain(FilterOperator::IS_FALSE);
        });

        it('returns correct operators for select field', function () {
            $operators = FilterOperator::forFieldType(FieldType::SELECT);

            expect($operators)->toContain(FilterOperator::EQUALS);
            expect($operators)->toContain(FilterOperator::IS_ANY_OF);
            expect($operators)->toContain(FilterOperator::IS_NONE_OF);
            expect($operators)->toContain(FilterOperator::IS_EMPTY);
        });

        it('returns correct operators for multi_select field', function () {
            $operators = FilterOperator::forFieldType(FieldType::MULTI_SELECT);

            expect($operators)->toContain(FilterOperator::CONTAINS_ANY);
            expect($operators)->toContain(FilterOperator::CONTAINS_ALL);
            expect($operators)->toContain(FilterOperator::IS_EMPTY);
            expect($operators)->not->toContain(FilterOperator::EQUALS);
        });

    });

    describe('requiresValue()', function () {

        it('returns false for IS_EMPTY operator', function () {
            expect(FilterOperator::IS_EMPTY->requiresValue())->toBeFalse();
        });

        it('returns false for IS_NOT_EMPTY operator', function () {
            expect(FilterOperator::IS_NOT_EMPTY->requiresValue())->toBeFalse();
        });

        it('returns false for IS_TRUE operator', function () {
            expect(FilterOperator::IS_TRUE->requiresValue())->toBeFalse();
        });

        it('returns false for IS_FALSE operator', function () {
            expect(FilterOperator::IS_FALSE->requiresValue())->toBeFalse();
        });

        it('returns true for EQUALS operator', function () {
            expect(FilterOperator::EQUALS->requiresValue())->toBeTrue();
        });

        it('returns true for CONTAINS operator', function () {
            expect(FilterOperator::CONTAINS->requiresValue())->toBeTrue();
        });

    });

    describe('requiresArrayValue()', function () {

        it('returns true for BETWEEN operator', function () {
            expect(FilterOperator::BETWEEN->requiresArrayValue())->toBeTrue();
        });

        it('returns true for IS_ANY_OF operator', function () {
            expect(FilterOperator::IS_ANY_OF->requiresArrayValue())->toBeTrue();
        });

        it('returns true for CONTAINS_ALL operator', function () {
            expect(FilterOperator::CONTAINS_ALL->requiresArrayValue())->toBeTrue();
        });

        it('returns false for EQUALS operator', function () {
            expect(FilterOperator::EQUALS->requiresArrayValue())->toBeFalse();
        });

        it('returns false for CONTAINS operator', function () {
            expect(FilterOperator::CONTAINS->requiresArrayValue())->toBeFalse();
        });

    });

});
