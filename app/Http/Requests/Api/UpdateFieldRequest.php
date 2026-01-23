<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'options.choices' => ['nullable', 'array'],
            'options.choices.*.id' => ['nullable', 'string'],
            'options.choices.*.name' => ['required_with:options.choices', 'string', 'max:255'],
            'options.choices.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_required' => ['nullable', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'width' => ['nullable', 'integer', 'min:50', 'max:1000'],
        ];
    }
}
