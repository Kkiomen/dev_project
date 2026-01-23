<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modifications' => ['sometimes', 'array'],
            'modifications.*' => ['array'],
            'format' => ['sometimes', 'string', 'in:png,jpeg,webp'],
            'quality' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'scale' => ['sometimes', 'numeric', 'min:0.1', 'max:4'],
        ];
    }
}
