<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class JsonField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if ($value === null || $value === '') {
            return !$field->is_required;
        }

        // Already an array/object - valid
        if (is_array($value) || is_object($value)) {
            return true;
        }

        // String - try to decode
        if (is_string($value)) {
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
        }

        return false;
    }

    public function parse(mixed $value, Field $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }

    public function format(mixed $value, Field $field): mixed
    {
        return $value;
    }

    public function getValidationRules(Field $field): array
    {
        return $field->is_required
            ? ['required', 'json']
            : ['nullable'];
    }

    public function getDefaultOptions(): array
    {
        return [];
    }
}
