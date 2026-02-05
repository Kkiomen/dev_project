<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DevTask extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'identifier',
        'project',
        'sequence_number',
        'title',
        'pm_description',
        'tech_description',
        'implementation_plan',
        'status',
        'position',
        'priority',
        'created_by',
        'assigned_to',
        'labels',
        'started_at',
        'completed_at',
        'due_date',
        'estimated_hours',
        'actual_hours',
    ];

    protected $casts = [
        'position' => 'integer',
        'sequence_number' => 'integer',
        'labels' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_date' => 'datetime',
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
    ];

    protected $appends = [
        'subtask_progress',
        'total_time_spent',
        'is_overdue',
        'is_due_soon',
    ];

    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REVIEW = 'review';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_BACKLOG,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REVIEW,
        self::STATUS_DONE,
    ];

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected static function booted(): void
    {
        static::creating(function (DevTask $task) {
            if (empty($task->identifier)) {
                $project = DevTaskProject::findByPrefixOrFail($task->project);
                $task->identifier = $project->getNextIdentifier();
                $task->sequence_number = (int) explode('-', $task->identifier)[1];
            }

            if (is_null($task->position)) {
                $task->position = static::where('status', $task->status)->max('position') + 1;
            }
        });

        static::updating(function (DevTask $task) {
            if ($task->isDirty('status')) {
                $originalStatus = $task->getOriginal('status');
                $newStatus = $task->status;

                if ($newStatus === self::STATUS_IN_PROGRESS && !$task->started_at) {
                    $task->started_at = now();
                }

                if ($newStatus === self::STATUS_DONE && !$task->completed_at) {
                    $task->completed_at = now();
                }

                $task->logs()->create([
                    'type' => 'status_change',
                    'content' => "Status changed from {$originalStatus} to {$newStatus}",
                    'user_id' => auth()->id(),
                    'metadata' => [
                        'from' => $originalStatus,
                        'to' => $newStatus,
                    ],
                ]);
            }
        });
    }

    public function projectModel(): BelongsTo
    {
        return $this->belongsTo(DevTaskProject::class, 'project', 'prefix');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DevTaskLog::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(DevTaskSubtask::class)->ordered();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DevTaskAttachment::class)->ordered();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(DevTaskTimeEntry::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(DevTaskMention::class);
    }

    public function getSubtaskProgressAttribute(): array
    {
        $total = $this->subtasks()->count();
        $completed = $this->subtasks()->completed()->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    public function getTotalTimeSpentAttribute(): int
    {
        return $this->timeEntries()
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date || $this->status === self::STATUS_DONE) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->due_date || $this->status === self::STATUS_DONE || $this->is_overdue) {
            return false;
        }

        return $this->due_date->diffInHours(now()) <= 48;
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function moveToStatus(string $newStatus, ?int $position = null): void
    {
        $this->status = $newStatus;
        $this->position = $position ?? static::where('status', $newStatus)->max('position') + 1;
        $this->save();
    }

    public function moveToPosition(int $newPosition): void
    {
        $oldPosition = $this->position;

        if ($newPosition === $oldPosition) {
            return;
        }

        if ($newPosition < $oldPosition) {
            static::where('status', $this->status)
                ->whereBetween('position', [$newPosition, $oldPosition - 1])
                ->increment('position');
        } else {
            static::where('status', $this->status)
                ->whereBetween('position', [$oldPosition + 1, $newPosition])
                ->decrement('position');
        }

        $this->update(['position' => $newPosition]);
    }
}
