<?php

namespace App\Enums;

enum EmojiUsage: string
{
    case Often = 'often';
    case Sometimes = 'sometimes';
    case Rarely = 'rarely';
    case Never = 'never';

    public function label(): string
    {
        return match ($this) {
            self::Often => 'Often',
            self::Sometimes => 'Sometimes',
            self::Rarely => 'Rarely',
            self::Never => 'Never',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Often => 'Use emojis frequently throughout content',
            self::Sometimes => 'Use emojis occasionally for emphasis',
            self::Rarely => 'Use emojis sparingly, only when necessary',
            self::Never => 'Never use emojis in content',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
