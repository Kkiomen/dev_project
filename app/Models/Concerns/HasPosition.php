<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasPosition
{
    public static function bootHasPosition(): void
    {
        static::creating(function ($model) {
            if (is_null($model->position)) {
                $groupColumn = $model->getPositionGroupColumn();
                $model->position = static::query()
                    ->where($groupColumn, $model->{$groupColumn})
                    ->max('position') + 1;
            }
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    public function moveToPosition(int $newPosition): void
    {
        $groupColumn = $this->getPositionGroupColumn();
        $oldPosition = $this->position;

        if ($newPosition === $oldPosition) {
            return;
        }

        if ($newPosition < $oldPosition) {
            // Moving up - shift others down
            static::where($groupColumn, $this->{$groupColumn})
                ->whereBetween('position', [$newPosition, $oldPosition - 1])
                ->increment('position');
        } else {
            // Moving down - shift others up
            static::where($groupColumn, $this->{$groupColumn})
                ->whereBetween('position', [$oldPosition + 1, $newPosition])
                ->decrement('position');
        }

        $this->update(['position' => $newPosition]);
    }

    abstract protected function getPositionGroupColumn(): string;
}
