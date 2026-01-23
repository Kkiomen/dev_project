<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'height' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => ['nullable', 'string', 'max:500'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
