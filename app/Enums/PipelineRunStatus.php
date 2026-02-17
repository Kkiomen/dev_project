<?php

namespace App\Enums;

enum PipelineRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isProcessing(): bool
    {
        return $this === self::Running;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
