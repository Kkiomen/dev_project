<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevTaskSubtask extends Model
{
    use HasPublicId;

    protected $fillable = [
        'dev_task_id',
        'title',
        'is_completed',
        'completed_at',
        'completed_by',
        'position',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'position' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DevTask::class, 'dev_task_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function toggle(): void
    {
        $this->is_completed = !$this->is_completed;
        $this->completed_at = $this->is_completed ? now() : null;
        $this->completed_by = $this->is_completed ? auth()->id() : null;
        $this->save();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }
}
