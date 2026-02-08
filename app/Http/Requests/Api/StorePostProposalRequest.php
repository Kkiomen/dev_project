<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePostProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['nullable', 'string', 'exists:brands,public_id'],
            'scheduled_date' => ['required', 'date', 'date_format:Y-m-d'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:255'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
