<?php

namespace App\Enums;

enum ApiProvider: string
{
    case OPENAI = 'openai';
    case PEXELS = 'pexels';
    case FACEBOOK = 'facebook';
    case UNSPLASH = 'unsplash';
    case WAVESPEED = 'wavespeed';
    case GETLATE = 'getlate';
    case APIFY = 'apify';

    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::PEXELS => 'Pexels',
            self::FACEBOOK => 'Facebook/Instagram',
            self::UNSPLASH => 'Unsplash',
            self::WAVESPEED => 'WaveSpeed AI',
            self::GETLATE => 'GetLate',
            self::APIFY => 'Apify',
        };
    }

    public function isAiProvider(): bool
    {
        return match ($this) {
            self::OPENAI, self::WAVESPEED => true,
            default => false,
        };
    }
}
