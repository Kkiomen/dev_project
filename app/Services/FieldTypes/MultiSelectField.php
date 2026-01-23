<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

class MultiSelectField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return !$field->is_required;
        }

        if (!is_array($value)) {
            return false;
        }

        $choiceIds = collect($field->options['choices'] ?? [])->pluck('id')->toArray();

        foreach ($value as $v) {
            if (!in_array($v, $choiceIds, true)) {
                return false;
            }
        }

        return true;
    }

    public function parse(mixed $value, Field $field): ?array
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return null;
        }

        if (!is_array($value)) {
            return [$value];
        }

        return array_values($value);
    }

    public function format(mixed $value, Field $field): array
    {
        if ($value === null) {
            return [];
        }

        $choices = collect($field->options['choices'] ?? []);

        return collect((array) $value)
            ->map(fn($id) => $choices->firstWhere('id', $id))
            ->filter()
            ->values()
            ->toArray();
    }

    public function getValidationRules(Field $field): array
    {
        $choiceIds = collect($field->options['choices'] ?? [])->pluck('id')->toArray();

        $rules = $field->is_required
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        if (!empty($choiceIds)) {
            $rules['*'] = 'in:' . implode(',', $choiceIds);
        }

        return $rules;
    }

    public function getDefaultOptions(): array
    {
        return [
            'choices' => [],
        ];
    }

    public function getDefaultValue(Field $field): mixed
    {
        return [];
    }
}
