<?php

namespace App\Services\FieldTypes;

use App\Models\Field;
use Carbon\Carbon;

class DateField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if (!parent::validate($value, $field)) {
            return false;
        }

        if ($value === null || $value === '') {
            return true;
        }

        try {
            Carbon::parse($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function parse(mixed $value, Field $field): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $carbon = Carbon::parse($value);

            // If field type is 'date' (not 'datetime'), strip time component
            if ($field->type->value === 'date') {
                $carbon = $carbon->startOfDay();
            }

            return $carbon;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function format(mixed $value, Field $field): ?string
    {
        if ($value === null) {
            return null;
        }

        // Determine format based on field type
        $isDatetime = $field->type->value === 'datetime';
        $format = $isDatetime ? 'Y-m-d H:i:s' : 'Y-m-d';

        if ($value instanceof \DateTime) {
            return $value->format($format);
        }

        return Carbon::parse($value)->format($format);
    }

    public function getValidationRules(Field $field): array
    {
        return $field->is_required ? ['required', 'date'] : ['nullable', 'date'];
    }

    public function getDefaultOptions(): array
    {
        return [
            'format' => 'Y-m-d',
        ];
    }
}
