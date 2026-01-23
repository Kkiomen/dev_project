<?php

namespace App\Http\Requests\Api;

use App\Enums\LayerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layer_key' => ['nullable', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(LayerType::values())],
            'visible' => ['nullable', 'boolean'],
            'locked' => ['nullable', 'boolean'],
            'x' => ['nullable', 'numeric'],
            'y' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'rotation' => ['nullable', 'numeric', 'min:-360', 'max:360'],
            'scale_x' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'scale_y' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'properties' => ['nullable', 'array'],
        ];
    }
}
