<?php

namespace App\Enums;

enum ApiProvider: string
{
    case OPENAI = 'openai';
    case PEXELS = 'pexels';
    case FACEBOOK = 'facebook';
    case UNSPLASH = 'unsplash';

    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::PEXELS => 'Pexels',
            self::FACEBOOK => 'Facebook/Instagram',
            self::UNSPLASH => 'Unsplash',
        };
    }

    public function isAiProvider(): bool
    {
        return match ($this) {
            self::OPENAI => true,
            default => false,
        };
    }
}
