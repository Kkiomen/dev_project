<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmContentPlan extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_content_plans';

    protected $fillable = [
        'brand_id',
        'sm_strategy_id',
        'month',
        'year',
        'status',
        'summary',
        'total_slots',
        'completed_slots',
        'generated_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'month' => 'integer',
        'year' => 'integer',
        'total_slots' => 'integer',
        'completed_slots' => 'integer',
        'generated_at' => 'datetime',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(SmStrategy::class, 'sm_strategy_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(SmContentPlanSlot::class, 'sm_content_plan_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getCompletionPercentage(): int
    {
        if ($this->total_slots === 0) {
            return 0;
        }

        return (int) round(($this->completed_slots / $this->total_slots) * 100);
    }

    public function getPeriodLabel(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));
    }

    public function recalculateSlotCounts(): self
    {
        $this->total_slots = $this->slots()->count();
        $this->completed_slots = $this->slots()->where('status', 'published')->count();
        $this->save();

        return $this;
    }
}
