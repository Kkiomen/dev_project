<?php

return [
    'required' => 'Pole :attribute jest wymagane.',
    'min' => [
        'numeric' => 'Pole :attribute musi być większe lub równe :min.',
        'file' => 'Plik :attribute musi mieć co najmniej :min kilobajtów.',
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
        'array' => 'Pole :attribute musi mieć co najmniej :min elementów.',
    ],
    'max' => [
        'numeric' => 'Pole :attribute musi być mniejsze lub równe :max.',
        'file' => 'Plik :attribute nie może być większy niż :max kilobajtów.',
        'string' => 'Pole :attribute nie może mieć więcej niż :max znaków.',
        'array' => 'Pole :attribute nie może mieć więcej niż :max elementów.',
    ],
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'string' => 'Pole :attribute musi być tekstem.',
    'array' => 'Pole :attribute musi być tablicą.',
    'email' => 'Pole :attribute musi być prawidłowym adresem email.',
    'url' => 'Pole :attribute musi być prawidłowym adresem URL.',
    'in' => 'Wybrana wartość dla :attribute jest nieprawidłowa.',
    'exists' => 'Wybrana wartość dla :attribute nie istnieje.',
    'unique' => 'Wartość :attribute jest już zajęta.',
];
