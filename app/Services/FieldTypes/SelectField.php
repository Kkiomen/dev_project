<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class SelectField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if (!parent::validate($value, $field)) {
            return false;
        }

        if ($value === null || $value === '') {
            return true;
        }

        $choiceIds = collect($field->options['choices'] ?? [])->pluck('id')->toArray();
        return in_array($value, $choiceIds, true);
    }

    public function parse(mixed $value, Field $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    public function format(mixed $value, Field $field): ?array
    {
        if ($value === null) {
            return null;
        }

        $choice = collect($field->options['choices'] ?? [])->firstWhere('id', $value);
        return $choice ?: null;
    }

    public function getValidationRules(Field $field): array
    {
        $choiceIds = collect($field->options['choices'] ?? [])->pluck('id')->toArray();
        $rules = $field->is_required
            ? ['required', 'string']
            : ['nullable', 'string'];

        if (!empty($choiceIds)) {
            $rules[] = 'in:' . implode(',', $choiceIds);
        }

        return $rules;
    }

    public function getDefaultOptions(): array
    {
        return [
            'choices' => [],
        ];
    }
}
