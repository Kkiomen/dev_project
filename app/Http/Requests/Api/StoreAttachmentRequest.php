<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cell = $this->route('cell');
        $field = $cell?->field;
        $options = $field?->options ?? [];

        $maxSizeKb = (($options['max_size'] ?? 10 * 1024 * 1024) / 1024);

        $rules = [
            'required',
            'file',
            "max:{$maxSizeKb}",
        ];

        // Convert allowed_types to mimes
        $allowedTypes = $options['allowed_types'] ?? ['image/*', 'application/pdf'];
        $mimes = $this->convertToMimes($allowedTypes);

        if ($mimes) {
            $rules[] = "mimes:{$mimes}";
        }

        return [
            'file' => $rules,
        ];
    }

    private function convertToMimes(array $types): ?string
    {
        $mimes = [];

        foreach ($types as $type) {
            if ($type === 'image/*') {
                $mimes = array_merge($mimes, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            } elseif ($type === 'application/pdf') {
                $mimes[] = 'pdf';
            } elseif ($type === 'text/*') {
                $mimes = array_merge($mimes, ['txt', 'csv', 'json', 'xml']);
            }
        }

        return $mimes ? implode(',', array_unique($mimes)) : null;
    }
}
