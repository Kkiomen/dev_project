<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CiInsight extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'ci_insights';

    protected $fillable = [
        'brand_id',
        'insight_type',
        'platform',
        'title',
        'description',
        'data',
        'priority',
        'is_actioned',
        'action_taken',
        'valid_until',
    ];

    protected $casts = [
        'data' => 'array',
        'priority' => 'integer',
        'is_actioned' => 'boolean',
        'valid_until' => 'date',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function markAsActioned(string $actionTaken): self
    {
        $this->update([
            'is_actioned' => true,
            'action_taken' => $actionTaken,
        ]);

        return $this;
    }

    public function scopeForBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
              ->orWhere('valid_until', '>=', now()->toDateString());
        });
    }

    public function scopeUnactioned(Builder $query): Builder
    {
        return $query->where('is_actioned', false);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('insight_type', $type);
    }

    public function scopeHighPriority(Builder $query, int $minPriority = 7): Builder
    {
        return $query->where('priority', '>=', $minPriority);
    }
}
