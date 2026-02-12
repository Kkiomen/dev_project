<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmListeningReport extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_listening_reports';

    protected $fillable = [
        'brand_id',
        'period_start',
        'period_end',
        'share_of_voice',
        'sentiment_breakdown',
        'top_mentions',
        'trending_keywords',
        'ai_summary',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'share_of_voice' => 'array',
        'sentiment_breakdown' => 'array',
        'top_mentions' => 'array',
        'trending_keywords' => 'array',
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
