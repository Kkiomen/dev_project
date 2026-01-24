<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PillarTracking extends Model
{
    use HasFactory;

    protected $table = 'pillar_tracking';

    protected $fillable = [
        'brand_id',
        'pillar_name',
        'week_number',
        'year',
        'planned_count',
        'published_count',
        'target_percentage',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'year' => 'integer',
        'planned_count' => 'integer',
        'published_count' => 'integer',
        'target_percentage' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Scopes
    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeForWeek(Builder $query, int $weekNumber, int $year): Builder
    {
        return $query->where('week_number', $weekNumber)->where('year', $year);
    }

    public function scopeForCurrentWeek(Builder $query): Builder
    {
        $now = Carbon::now();
        return $query->forWeek($now->weekOfYear, $now->year);
    }

    // Static methods
    public static function getOrCreateForWeek(Brand $brand, string $pillarName, Carbon $date): self
    {
        $weekNumber = $date->weekOfYear;
        $year = $date->year;

        // Get target percentage from brand's content pillars
        $pillar = $brand->getPillarByName($pillarName);
        $targetPercentage = $pillar['percentage'] ?? 0;

        return static::firstOrCreate(
            [
                'brand_id' => $brand->id,
                'pillar_name' => $pillarName,
                'week_number' => $weekNumber,
                'year' => $year,
            ],
            [
                'target_percentage' => $targetPercentage,
                'planned_count' => 0,
                'published_count' => 0,
            ]
        );
    }

    // Helper methods
    public function incrementPlanned(int $count = 1): self
    {
        $this->planned_count += $count;
        $this->save();

        return $this;
    }

    public function incrementPublished(int $count = 1): self
    {
        $this->published_count += $count;
        $this->save();

        return $this;
    }

    public function decrementPlanned(int $count = 1): self
    {
        $this->planned_count = max(0, $this->planned_count - $count);
        $this->save();

        return $this;
    }

    /**
     * Get the actual percentage of planned posts for this pillar
     * relative to total planned posts for the week.
     */
    public function getActualPercentage(int $totalPlannedForWeek): float
    {
        if ($totalPlannedForWeek === 0) {
            return 0;
        }

        return round(($this->planned_count / $totalPlannedForWeek) * 100, 2);
    }

    /**
     * Get how much this pillar is under/over represented.
     * Negative = underrepresented, positive = overrepresented.
     */
    public function getPercentageDelta(int $totalPlannedForWeek): float
    {
        return $this->getActualPercentage($totalPlannedForWeek) - $this->target_percentage;
    }

    /**
     * Check if this pillar is underrepresented.
     */
    public function isUnderrepresented(int $totalPlannedForWeek): bool
    {
        return $this->getPercentageDelta($totalPlannedForWeek) < 0;
    }
}
