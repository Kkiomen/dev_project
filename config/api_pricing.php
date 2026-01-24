<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI Model Pricing
    |--------------------------------------------------------------------------
    |
    | Prices are per 1 million tokens in USD.
    | Input = prompt tokens, Output = completion tokens
    |
    */

    'openai' => [
        'gpt-4o' => [
            'input' => 2.50,
            'output' => 10.00,
        ],
        'gpt-4o-mini' => [
            'input' => 0.15,
            'output' => 0.60,
        ],
        'gpt-4-turbo' => [
            'input' => 10.00,
            'output' => 30.00,
        ],
        'gpt-4' => [
            'input' => 30.00,
            'output' => 60.00,
        ],
        'gpt-3.5-turbo' => [
            'input' => 0.50,
            'output' => 1.50,
        ],
        // Default fallback for unknown models
        'default' => [
            'input' => 10.00,
            'output' => 30.00,
        ],
    ],
];
