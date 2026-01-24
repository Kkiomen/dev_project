<?php

namespace App\Enums;

enum PublishStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Pending => 'Pending',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::Pending => 'yellow',
            self::Published => 'green',
            self::Failed => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
