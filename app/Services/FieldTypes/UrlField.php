<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class UrlField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if (!parent::validate($value, $field)) {
            return false;
        }

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public function parse(mixed $value, Field $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        // Add https:// if no protocol specified
        if (!preg_match('/^https?:\/\//i', $value)) {
            $value = 'https://' . $value;
        }

        return $value;
    }

    public function getValidationRules(Field $field): array
    {
        return $field->is_required
            ? ['required', 'url']
            : ['nullable', 'url'];
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    public function getDefaultValue(Field $field): mixed
    {
        return '';
    }
}
