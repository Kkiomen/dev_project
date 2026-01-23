<?php

namespace App\Services\FieldTypes;

use App\Models\Field;

interface FieldTypeInterface
{
    public function validate(mixed $value, Field $field): bool;

    public function parse(mixed $value, Field $field): mixed;

    public function format(mixed $value, Field $field): mixed;

    public function getDefaultValue(Field $field): mixed;

    public function getValidationRules(Field $field): array;

    public function getDefaultOptions(): array;
}
