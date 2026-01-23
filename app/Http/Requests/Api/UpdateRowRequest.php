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
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
