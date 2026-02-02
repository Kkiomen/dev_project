<?php

namespace App\Models;

use App\Enums\CalendarEventType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'color',
        'event_type',
        'starts_at',
        'ends_at',
        'all_day',
    ];

    protected $casts = [
        'event_type' => CalendarEventType::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeScheduledBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->where(function ($q) use ($start, $end) {
            // Events that start within the range
            $q->whereBetween('starts_at', [$start, $end])
              // Or events that span across the range (start before, end after)
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('starts_at', '<=', $start)
                     ->where('ends_at', '>=', $end);
              })
              // Or events that started before but end within the range
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('starts_at', '<', $start)
                     ->whereBetween('ends_at', [$start, $end]);
              });
        });
    }

    public function scopeOfType(Builder $query, CalendarEventType $type): Builder
    {
        return $query->where('event_type', $type);
    }

    // Helper methods
    public function isAllDay(): bool
    {
        return $this->all_day;
    }

    public function getScheduledDate(): string
    {
        return $this->starts_at->format('Y-m-d');
    }

    public function getScheduledTime(): ?string
    {
        if ($this->all_day) {
            return null;
        }

        return $this->starts_at->format('H:i');
    }
}
