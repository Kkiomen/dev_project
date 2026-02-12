<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmContentPlanSlot extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_content_plan_slots';

    protected $fillable = [
        'sm_content_plan_id',
        'scheduled_date',
        'scheduled_time',
        'platform',
        'content_type',
        'topic',
        'description',
        'pillar',
        'status',
        'social_post_id',
        'position',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'position' => 'integer',
    ];

    // Relationships
    public function contentPlan(): BelongsTo
    {
        return $this->belongsTo(SmContentPlan::class, 'sm_content_plan_id');
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    // Scopes
    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time');
    }

    // Helpers
    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function hasContent(): bool
    {
        return $this->social_post_id !== null;
    }

    public function markContentReady(): self
    {
        $this->status = 'content_ready';
        $this->save();

        return $this;
    }

    public function getScheduledDateTime(): ?string
    {
        if (!$this->scheduled_time) {
            return $this->scheduled_date->format('Y-m-d');
        }

        return $this->scheduled_date->format('Y-m-d') . ' ' . $this->scheduled_time;
    }
}
