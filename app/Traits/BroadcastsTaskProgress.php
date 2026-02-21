<?php

namespace App\Traits;

use App\Events\TaskCompleted;
use App\Events\TaskStarted;

trait BroadcastsTaskProgress
{
    abstract protected function taskType(): string;

    abstract protected function taskUserId(): int;

    protected function taskModelId(): string|int
    {
        return $this->{$this->taskModelProperty ?? 'id'} ?? uniqid();
    }

    protected function taskStartData(): array
    {
        return [];
    }

    protected function broadcastTaskStarted(): void
    {
        try {
            broadcast(new TaskStarted(
                $this->taskUserId(),
                $this->taskType() . '_' . $this->taskModelId(),
                $this->taskType(),
                $this->taskStartData()
            ));
        } catch (\Throwable) {
        }
    }

    protected function broadcastTaskCompleted(bool $success = true, ?string $error = null, array $data = []): void
    {
        try {
            broadcast(new TaskCompleted(
                $this->taskUserId(),
                $this->taskType() . '_' . $this->taskModelId(),
                $this->taskType(),
                $success,
                $error,
                $data
            ));
        } catch (\Throwable) {
        }
    }
}
