<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class TextField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if (!parent::validate($value, $field)) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        $maxLength = $field->options['max_length'] ?? null;
        if ($maxLength && strlen($value) > $maxLength) {
            return false;
        }

        return is_string($value) || is_numeric($value);
    }

    public function parse(mixed $value, Field $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    public function getValidationRules(Field $field): array
    {
        $rules = $field->is_required ? ['required', 'string'] : ['nullable', 'string'];

        if ($maxLength = $field->options['max_length'] ?? null) {
            $rules[] = "max:{$maxLength}";
        }

        return $rules;
    }

    public function getDefaultOptions(): array
    {
        return [
            'rich_text' => false,
            'max_length' => null,
        ];
    }

    public function getDefaultValue(Field $field): mixed
    {
        return '';
    }
}
