<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Pending = 'pending';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Used => 'Used',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Used => 'green',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
