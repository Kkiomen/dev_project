<?php

namespace App\Models;

use App\Enums\ApifyActorType;
use App\Enums\ScrapeStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CiScrapeRun extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'ci_scrape_runs';

    protected $fillable = [
        'brand_id',
        'actor_type',
        'apify_run_id',
        'status',
        'input_params',
        'results_count',
        'estimated_cost',
        'actual_cost',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'actor_type' => ApifyActorType::class,
        'status' => ScrapeStatus::class,
        'input_params' => 'array',
        'results_count' => 'integer',
        'estimated_cost' => 'float',
        'actual_cost' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function markAsRunning(string $apifyRunId): self
    {
        $this->update([
            'status' => ScrapeStatus::Running,
            'apify_run_id' => $apifyRunId,
            'started_at' => now(),
        ]);

        return $this;
    }

    public function markAsSucceeded(int $resultsCount, ?float $actualCost = null): self
    {
        $this->update([
            'status' => ScrapeStatus::Succeeded,
            'results_count' => $resultsCount,
            'actual_cost' => $actualCost,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->update([
            'status' => ScrapeStatus::Failed,
            'error_message' => $error,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function scopeForBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [ScrapeStatus::Pending, ScrapeStatus::Running]);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
