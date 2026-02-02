<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case Meeting = 'meeting';
    case Birthday = 'birthday';
    case Reminder = 'reminder';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Meeting',
            self::Birthday => 'Birthday',
            self::Reminder => 'Reminder',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Meeting => 'users',
            self::Birthday => 'cake',
            self::Reminder => 'bell',
            self::Other => 'calendar',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
