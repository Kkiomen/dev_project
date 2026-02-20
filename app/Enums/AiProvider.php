<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case WaveSpeed = 'wavespeed';
    case GetLate = 'getlate';
    case Apify = 'apify';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Gemini',
            self::WaveSpeed => 'WaveSpeed AI',
            self::GetLate => 'GetLate',
            self::Apify => 'Apify',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
