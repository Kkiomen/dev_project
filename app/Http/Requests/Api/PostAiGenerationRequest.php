<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PostAiGenerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:500'],
            'tone' => ['required', 'string', 'in:professional,casual,playful,inspirational'],
            'platform' => ['required', 'string', 'in:facebook,instagram,youtube'],
            'length' => ['required', 'string', 'in:short,medium,long'],
            'language' => ['required', 'string', 'in:pl,en'],
            'customPrompt' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
