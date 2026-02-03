<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MoveBoardCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'column_id' => ['required', 'string', 'exists:board_columns,public_id'],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
