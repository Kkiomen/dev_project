<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class NumberField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if (!parent::validate($value, $field)) {
            return false;
        }

        if ($value === null || $value === '') {
            return true;
        }

        return is_numeric($value);
    }

    public function parse(mixed $value, Field $field): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $precision = $field->options['precision'] ?? 6;
        return round((float) $value, $precision);
    }

    public function format(mixed $value, Field $field): mixed
    {
        if ($value === null) {
            return null;
        }

        // Return raw value for API - formatting is done on frontend
        return (float) $value;
    }

    public function getValidationRules(Field $field): array
    {
        $rules = $field->is_required ? ['required', 'numeric'] : ['nullable', 'numeric'];

        if (isset($field->options['min'])) {
            $rules[] = 'min:' . $field->options['min'];
        }

        if (isset($field->options['max'])) {
            $rules[] = 'max:' . $field->options['max'];
        }

        return $rules;
    }

    public function getDefaultOptions(): array
    {
        return [
            'precision' => 2,
            'format' => 'decimal', // decimal, currency, percent
            'currency' => 'PLN',
            'min' => null,
            'max' => null,
        ];
    }
}
