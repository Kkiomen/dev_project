<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CiTrendingTopic extends Model
{
    use HasFactory;

    protected $table = 'ci_trending_topics';

    protected $fillable = [
        'brand_id',
        'platform',
        'source',
        'topic',
        'category',
        'volume',
        'growth_rate',
        'trend_direction',
        'related_hashtags',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'related_hashtags' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'volume' => 'integer',
        'growth_rate' => 'float',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    public function scopeForBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('valid_until', '>=', now()->toDateString());
    }

    public function scopeRising(Builder $query): Builder
    {
        return $query->whereIn('trend_direction', ['rising', 'breakout']);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeByDirection(Builder $query, string $direction): Builder
    {
        return $query->where('trend_direction', $direction);
    }
}
