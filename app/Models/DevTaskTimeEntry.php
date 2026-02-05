<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevTaskTimeEntry extends Model
{
    use HasPublicId;

    protected $fillable = [
        'dev_task_id',
        'user_id',
        'started_at',
        'stopped_at',
        'duration_minutes',
        'description',
        'is_running',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'duration_minutes' => 'integer',
        'is_running' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DevTask::class, 'dev_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stop(): void
    {
        $this->stopped_at = now();
        $this->is_running = false;
        $this->duration_minutes = (int) $this->started_at->diffInMinutes($this->stopped_at);
        $this->save();
    }

    public function getDurationAttribute(): int
    {
        if ($this->is_running) {
            return (int) $this->started_at->diffInMinutes(now());
        }

        return $this->duration_minutes ?? 0;
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = $this->duration;
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $mins);
        }

        return sprintf('%dm', $mins);
    }

    public function scopeRunning($query)
    {
        return $query->where('is_running', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }
}
