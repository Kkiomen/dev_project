<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

abstract class AbstractFieldType implements FieldTypeInterface
{
    public function validate(mixed $value, Field $field): bool
    {
        if ($value === null || $value === '') {
            return !$field->is_required;
        }
        return true;
    }

    public function parse(mixed $value, Field $field): mixed
    {
        return $value;
    }

    public function format(mixed $value, Field $field): mixed
    {
        return $value;
    }

    public function getDefaultValue(Field $field): mixed
    {
        return null;
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    public function getValidationRules(Field $field): array
    {
        return $field->is_required ? ['required'] : ['nullable'];
    }
}
