<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'values' => ['sometimes', 'array'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Get values to update, preserving boolean false.
     */
    public function getValues(): array
    {
        return $this->input('values', []);
    }
}
