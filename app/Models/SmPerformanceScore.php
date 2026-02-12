<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmPerformanceScore extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_performance_scores';

    protected $fillable = [
        'social_post_id',
        'score',
        'analysis',
        'recommendations',
        'ai_model',
    ];

    protected $casts = [
        'analysis' => 'array',
        'score' => 'integer',
    ];

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function getScoreLabel(): string
    {
        return match (true) {
            $this->score >= 80 => 'excellent',
            $this->score >= 60 => 'good',
            $this->score >= 40 => 'average',
            $this->score >= 20 => 'below_average',
            default => 'poor',
        };
    }
}
