<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case WaveSpeed = 'wavespeed';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Gemini',
            self::WaveSpeed => 'WaveSpeed AI',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
