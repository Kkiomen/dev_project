<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevTaskMention extends Model
{
    protected $fillable = [
        'dev_task_id',
        'user_id',
        'log_id',
        'notified',
        'read_at',
    ];

    protected $casts = [
        'notified' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DevTask::class, 'dev_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(DevTaskLog::class, 'log_id');
    }

    public function markAsRead(): void
    {
        $this->read_at = now();
        $this->save();
    }

    public function markAsNotified(): void
    {
        $this->notified = true;
        $this->save();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeUnnotified($query)
    {
        return $query->where('notified', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
