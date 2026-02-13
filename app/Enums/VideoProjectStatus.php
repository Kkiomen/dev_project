<?php

namespace App\Enums;

enum VideoProjectStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Transcribing = 'transcribing';
    case Transcribed = 'transcribed';
    case Editing = 'editing';
    case Rendering = 'rendering';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Uploading => 'Uploading',
            self::Transcribing => 'Transcribing',
            self::Transcribed => 'Transcribed',
            self::Editing => 'Editing',
            self::Rendering => 'Rendering',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Uploading => 'blue',
            self::Transcribing => 'indigo',
            self::Transcribed => 'purple',
            self::Editing => 'yellow',
            self::Rendering => 'orange',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::Uploading, self::Transcribing, self::Editing, self::Rendering]);
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Transcribed, self::Completed]);
    }

    public function canExport(): bool
    {
        return in_array($this, [self::Transcribed, self::Completed]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
