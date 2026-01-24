<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after_or_equal' => __('validation.custom.scheduled_at.future'),
        ];
    }
}
