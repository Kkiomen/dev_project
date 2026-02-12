<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmStrategy extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_strategies';

    protected $fillable = [
        'brand_id',
        'content_pillars',
        'posting_frequency',
        'target_audience',
        'goals',
        'competitor_handles',
        'content_mix',
        'optimal_times',
        'ai_recommendations',
        'status',
        'activated_at',
    ];

    protected $casts = [
        'content_pillars' => 'array',
        'posting_frequency' => 'array',
        'target_audience' => 'array',
        'goals' => 'array',
        'competitor_handles' => 'array',
        'content_mix' => 'array',
        'optimal_times' => 'array',
        'activated_at' => 'datetime',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentPlans(): HasMany
    {
        return $this->hasMany(SmContentPlan::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function activate(): self
    {
        $this->status = 'active';
        $this->activated_at = now();
        $this->save();

        return $this;
    }

    public function pause(): self
    {
        $this->status = 'paused';
        $this->save();

        return $this;
    }

    public function getTotalWeeklyPosts(): int
    {
        if (!$this->posting_frequency) {
            return 0;
        }

        return array_sum($this->posting_frequency);
    }
}
