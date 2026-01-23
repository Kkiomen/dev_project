<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class CheckboxField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if ($value === null) {
            return !$field->is_required;
        }

        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    public function parse(mixed $value, Field $field): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false'], true)) {
            return false;
        }

        return (bool) $value;
    }

    public function format(mixed $value, Field $field): bool
    {
        return (bool) $value;
    }

    public function getValidationRules(Field $field): array
    {
        return ['nullable', 'boolean'];
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    public function getDefaultValue(Field $field): mixed
    {
        return false;
    }
}
