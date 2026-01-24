<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'platform_caption' => ['nullable', 'string', 'max:5000'],
            'video_title' => ['nullable', 'string', 'max:100'],
            'video_description' => ['nullable', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:100'],
            'link_preview' => ['nullable', 'array'],
        ];
    }
}
