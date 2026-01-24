<?php

namespace App\Enums;

enum BrandTone: string
{
    case Professional = 'professional';
    case Casual = 'casual';
    case Playful = 'playful';
    case Formal = 'formal';
    case Friendly = 'friendly';
    case Authoritative = 'authoritative';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Professional',
            self::Casual => 'Casual',
            self::Playful => 'Playful',
            self::Formal => 'Formal',
            self::Friendly => 'Friendly',
            self::Authoritative => 'Authoritative',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Professional => 'Balanced, business-appropriate communication',
            self::Casual => 'Relaxed, conversational style',
            self::Playful => 'Fun, energetic, and light-hearted',
            self::Formal => 'Traditional, structured communication',
            self::Friendly => 'Warm, approachable, and personal',
            self::Authoritative => 'Expert, confident, and commanding',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
