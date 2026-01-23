<?php

namespace App\Services\FieldTypes;

use App\Models\Field;
use App\Models\Attachment;

class AttachmentField extends AbstractFieldType
{
    public function validate(mixed $value, Field $field): bool
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return !$field->is_required;
        }

        if (!is_array($value)) {
            return false;
        }

        $maxFiles = $field->options['max_files'] ?? 10;
        return count($value) <= $maxFiles;
    }

    public function parse(mixed $value, Field $field): ?array
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return null;
        }
        return array_values((array) $value);
    }

    public function format(mixed $value, Field $field): array
    {
        if ($value === null || empty($value)) {
            return [];
        }

        // Value contains attachment public IDs
        return Attachment::whereIn('public_id', (array) $value)
            ->ordered()
            ->get()
            ->map(fn($attachment) => [
                'id' => $attachment->public_id,
                'filename' => $attachment->filename,
                'url' => $attachment->url,
                'thumbnail_url' => $attachment->thumbnail_url,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'size_formatted' => $attachment->size_formatted,
                'is_image' => $attachment->is_image,
                'width' => $attachment->width,
                'height' => $attachment->height,
            ])
            ->toArray();
    }

    public function getValidationRules(Field $field): array
    {
        $rules = $field->is_required
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        $maxFiles = $field->options['max_files'] ?? 10;
        $rules[] = "max:{$maxFiles}";

        return $rules;
    }

    public function getDefaultOptions(): array
    {
        return [
            'max_files' => 10,
            'allowed_types' => ['image/*', 'application/pdf'],
            'max_size' => 10 * 1024 * 1024, // 10MB
        ];
    }

    public function getDefaultValue(Field $field): array
    {
        return [];
    }
}
