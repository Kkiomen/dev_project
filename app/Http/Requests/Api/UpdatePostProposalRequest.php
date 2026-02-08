<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['sometimes', 'nullable', 'string', 'exists:brands,public_id'],
            'scheduled_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'scheduled_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'title' => ['sometimes', 'string', 'max:255'],
            'keywords' => ['sometimes', 'nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
