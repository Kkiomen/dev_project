<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CiCompetitorPost extends Model
{
    use HasFactory;

    protected $table = 'ci_competitor_posts';

    protected $fillable = [
        'brand_id',
        'ci_competitor_id',
        'ci_competitor_account_id',
        'platform',
        'external_post_id',
        'post_type',
        'caption',
        'hashtags',
        'post_url',
        'posted_at',
        'likes',
        'comments',
        'shares',
        'saves',
        'views',
        'engagement_rate',
        'ai_analysis',
        'analyzed_at',
        'raw_data',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'ai_analysis' => 'array',
        'raw_data' => 'array',
        'posted_at' => 'datetime',
        'analyzed_at' => 'datetime',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'saves' => 'integer',
        'views' => 'integer',
        'engagement_rate' => 'float',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(CiCompetitor::class, 'ci_competitor_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CiCompetitorAccount::class, 'ci_competitor_account_id');
    }

    public function getTotalEngagement(): int
    {
        return $this->likes + $this->comments + $this->shares + $this->saves;
    }

    public function needsAnalysis(): bool
    {
        return is_null($this->analyzed_at);
    }

    public function scopeForBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeTopPerforming(Builder $query, int $limit = 20): Builder
    {
        return $query->orderByDesc('engagement_rate')->limit($limit);
    }

    public function scopeByPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('posted_at', '>=', now()->subDays($days));
    }

    public function scopeUnanalyzed(Builder $query): Builder
    {
        return $query->whereNull('analyzed_at');
    }
}
