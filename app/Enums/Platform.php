<?php

namespace App\Enums;

enum Platform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case YouTube = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::YouTube => 'YouTube',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Facebook => 'facebook',
            self::Instagram => 'instagram',
            self::YouTube => 'youtube',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Facebook => '#1877F2',
            self::Instagram => '#E4405F',
            self::YouTube => '#FF0000',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
