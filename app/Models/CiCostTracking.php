<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CiCostTracking extends Model
{
    use HasFactory;

    protected $table = 'ci_cost_tracking';

    protected $fillable = [
        'brand_id',
        'period',
        'total_cost',
        'budget_limit',
        'total_runs',
        'total_results',
        'cost_breakdown',
    ];

    protected $casts = [
        'total_cost' => 'float',
        'budget_limit' => 'float',
        'total_runs' => 'integer',
        'total_results' => 'integer',
        'cost_breakdown' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isBudgetExceeded(): bool
    {
        return $this->total_cost >= $this->budget_limit;
    }

    public function getRemainingBudget(): float
    {
        return max(0, $this->budget_limit - $this->total_cost);
    }

    public function addCost(float $cost, string $actorType, int $resultsCount = 0): self
    {
        $breakdown = $this->cost_breakdown ?? [];
        $breakdown[$actorType] = ($breakdown[$actorType] ?? 0) + $cost;

        $this->update([
            'total_cost' => $this->total_cost + $cost,
            'total_runs' => $this->total_runs + 1,
            'total_results' => $this->total_results + $resultsCount,
            'cost_breakdown' => $breakdown,
        ]);

        return $this;
    }

    public static function getOrCreateForBrand(int $brandId, ?string $period = null): self
    {
        $period = $period ?? now()->format('Y-m');

        return self::firstOrCreate(
            ['brand_id' => $brandId, 'period' => $period],
            ['budget_limit' => 5.00, 'cost_breakdown' => []]
        );
    }

    public function scopeForBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeCurrentPeriod(Builder $query): Builder
    {
        return $query->where('period', now()->format('Y-m'));
    }
}
