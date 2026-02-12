<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmWeeklyReport extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_weekly_reports';

    protected $fillable = [
        'brand_id',
        'period_start',
        'period_end',
        'summary',
        'top_posts',
        'recommendations',
        'growth_metrics',
        'platform_breakdown',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'summary' => 'array',
        'top_posts' => 'array',
        'growth_metrics' => 'array',
        'platform_breakdown' => 'array',
        'generated_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function getPeriodLabel(): string
    {
        return $this->period_start->format('M d') . ' - ' . $this->period_end->format('M d, Y');
    }
}
