<?php

namespace App\Enums;

enum Platform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case LinkedIn = 'linkedin';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X (Twitter)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Facebook => 'facebook',
            self::Instagram => 'instagram',
            self::YouTube => 'youtube',
            self::TikTok => 'tiktok',
            self::LinkedIn => 'linkedin',
            self::X => 'x',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Facebook => '#1877F2',
            self::Instagram => '#E4405F',
            self::YouTube => '#FF0000',
            self::TikTok => '#000000',
            self::LinkedIn => '#0A66C2',
            self::X => '#000000',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
