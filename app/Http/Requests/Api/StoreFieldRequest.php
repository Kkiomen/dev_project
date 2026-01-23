<?php

namespace App\Http\Requests\Api;

use App\Enums\FieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(FieldType::class)],
            'options' => ['nullable', 'array'],
            'options.choices' => ['nullable', 'array'],
            'options.choices.*.name' => ['required_with:options.choices', 'string', 'max:255'],
            'options.choices.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_required' => ['nullable', 'boolean'],
            'is_primary' => ['nullable', 'boolean'],
            'width' => ['nullable', 'integer', 'min:50', 'max:1000'],
        ];
    }
}
