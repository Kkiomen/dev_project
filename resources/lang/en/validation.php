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
];
