<?php

namespace App\Http\Requests\Api;

use App\Enums\LayerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateLayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layers' => ['required', 'array', 'min:1'],
            'layers.*.id' => ['required', 'string'],
            'layers.*.layer_key' => ['nullable', 'string', 'max:100'],
            'layers.*.name' => ['sometimes', 'string', 'max:255'],
            'layers.*.type' => ['sometimes', 'string', Rule::in(LayerType::values())],
            'layers.*.position' => ['nullable', 'integer', 'min:0'],
            'layers.*.visible' => ['nullable', 'boolean'],
            'layers.*.locked' => ['nullable', 'boolean'],
            'layers.*.x' => ['nullable', 'numeric'],
            'layers.*.y' => ['nullable', 'numeric'],
            'layers.*.width' => ['nullable', 'numeric', 'min:0'],
            'layers.*.height' => ['nullable', 'numeric', 'min:0'],
            'layers.*.rotation' => ['nullable', 'numeric', 'min:-360', 'max:360'],
            'layers.*.scale_x' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'layers.*.scale_y' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'layers.*.properties' => ['nullable', 'array'],
        ];
    }
}
