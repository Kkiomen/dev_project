<?php

namespace App\Enums;

enum FilterOperator: string
{
    // Common operators
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';

    // Text/URL operators
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';

    // Number/Date operators
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case GREATER_OR_EQUAL = 'greater_or_equal';
    case LESS_OR_EQUAL = 'less_or_equal';
    case BETWEEN = 'between';

    // Date specific
    case BEFORE = 'before';
    case AFTER = 'after';
    case ON_OR_BEFORE = 'on_or_before';
    case ON_OR_AFTER = 'on_or_after';

    // Checkbox operators
    case IS_TRUE = 'is_true';
    case IS_FALSE = 'is_false';

    // Select operators
    case IS_ANY_OF = 'is_any_of';
    case IS_NONE_OF = 'is_none_of';

    // Multi-select operators
    case CONTAINS_ANY = 'contains_any';
    case CONTAINS_ALL = 'contains_all';

    /**
     * Get operators available for a specific field type.
     */
    public static function forFieldType(FieldType $fieldType): array
    {
        return match ($fieldType) {
            FieldType::TEXT, FieldType::URL => [
                self::EQUALS,
                self::NOT_EQUALS,
                self::CONTAINS,
                self::NOT_CONTAINS,
                self::STARTS_WITH,
                self::ENDS_WITH,
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
            FieldType::NUMBER => [
                self::EQUALS,
                self::NOT_EQUALS,
                self::GREATER_THAN,
                self::LESS_THAN,
                self::GREATER_OR_EQUAL,
                self::LESS_OR_EQUAL,
                self::BETWEEN,
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
            FieldType::DATE, FieldType::DATETIME => [
                self::EQUALS,
                self::NOT_EQUALS,
                self::BEFORE,
                self::AFTER,
                self::ON_OR_BEFORE,
                self::ON_OR_AFTER,
                self::BETWEEN,
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
            FieldType::CHECKBOX => [
                self::IS_TRUE,
                self::IS_FALSE,
            ],
            FieldType::SELECT => [
                self::EQUALS,
                self::NOT_EQUALS,
                self::IS_ANY_OF,
                self::IS_NONE_OF,
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
            FieldType::MULTI_SELECT => [
                self::CONTAINS_ANY,
                self::CONTAINS_ALL,
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
            FieldType::JSON => [
                self::CONTAINS,
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
            FieldType::ATTACHMENT => [
                self::IS_EMPTY,
                self::IS_NOT_EMPTY,
            ],
        };
    }

    /**
     * Check if operator requires a value.
     */
    public function requiresValue(): bool
    {
        return !in_array($this, [
            self::IS_EMPTY,
            self::IS_NOT_EMPTY,
            self::IS_TRUE,
            self::IS_FALSE,
        ]);
    }

    /**
     * Check if operator requires an array value (for between, is_any_of, etc).
     */
    public function requiresArrayValue(): bool
    {
        return in_array($this, [
            self::BETWEEN,
            self::IS_ANY_OF,
            self::IS_NONE_OF,
            self::CONTAINS_ANY,
            self::CONTAINS_ALL,
        ]);
    }
}
