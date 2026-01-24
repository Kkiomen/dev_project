<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'yellow',
            self::Approved => 'blue',
            self::Scheduled => 'indigo',
            self::Published => 'green',
            self::Failed => 'red',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved]);
    }

    public function canDelete(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved, self::Failed]);
    }

    public function canSchedule(): bool
    {
        return in_array($this, [self::Approved]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
