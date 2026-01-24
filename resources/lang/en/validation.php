<?php

return [
    'required' => 'The :attribute field is required.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'file' => 'The :attribute must be at least :min kilobytes.',
        'string' => 'The :attribute must be at least :min characters.',
        'array' => 'The :attribute must have at least :min items.',
    ],
    'max' => [
        'numeric' => 'The :attribute may not be greater than :max.',
        'file' => 'The :attribute may not be greater than :max kilobytes.',
        'string' => 'The :attribute may not be greater than :max characters.',
        'array' => 'The :attribute may not have more than :max items.',
    ],
    'integer' => 'The :attribute must be an integer.',
    'string' => 'The :attribute must be a string.',
    'array' => 'The :attribute must be an array.',
    'email' => 'The :attribute must be a valid email address.',
    'url' => 'The :attribute must be a valid URL.',
    'in' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute does not exist.',
    'unique' => 'The :attribute has already been taken.',
    'date' => 'The :attribute must be a valid date.',
    'after' => 'The :attribute must be a date after :date.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'before' => 'The :attribute must be a date before :date.',
    'before_or_equal' => 'The :attribute must be a date before or equal to :date.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'scheduled_at' => 'schedule date',
        'title' => 'title',
        'main_caption' => 'caption',
        'platforms' => 'platforms',
        'email' => 'email address',
        'password' => 'password',
        'name' => 'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'scheduled_at' => [
            'future' => 'The schedule date must be in the future.',
        ],
    ],
];
