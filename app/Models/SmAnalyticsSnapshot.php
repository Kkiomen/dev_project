<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmAnalyticsSnapshot extends Model
{
    protected $table = 'sm_analytics_snapshots';

    protected $fillable = [
        'brand_id',
        'platform',
        'snapshot_date',
        'followers',
        'following',
        'reach',
        'impressions',
        'profile_views',
        'website_clicks',
        'engagement_rate',
        'posts_count',
        'extra_metrics',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'extra_metrics' => 'array',
        'engagement_rate' => 'decimal:4',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeInPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('snapshot_date', [$start, $end]);
    }
}
