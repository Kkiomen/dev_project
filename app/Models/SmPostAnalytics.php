<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmPostAnalytics extends Model
{
    use HasFactory;
    protected $table = 'sm_post_analytics';

    protected $fillable = [
        'social_post_id',
        'platform',
        'likes',
        'comments',
        'shares',
        'saves',
        'reach',
        'impressions',
        'clicks',
        'video_views',
        'engagement_rate',
        'extra_metrics',
        'collected_at',
    ];

    protected $casts = [
        'extra_metrics' => 'array',
        'collected_at' => 'datetime',
        'engagement_rate' => 'decimal:4',
    ];

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function getTotalEngagement(): int
    {
        return $this->likes + $this->comments + $this->shares + $this->saves;
    }
}
