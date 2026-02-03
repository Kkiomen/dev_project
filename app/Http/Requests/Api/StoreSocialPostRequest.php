<?php

namespace App\Http\Requests\Api;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'main_caption' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'settings' => ['nullable', 'array'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string', Rule::in(Platform::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after_or_equal' => __('validation.custom.scheduled_at.future'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert empty string to null for scheduled_at
        if ($this->scheduled_at === '' || $this->scheduled_at === 'null') {
            $this->merge(['scheduled_at' => null]);
        }
    }
}
