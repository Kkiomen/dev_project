<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmMention extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_mentions';

    protected $fillable = [
        'brand_id',
        'sm_monitored_keyword_id',
        'platform',
        'source_url',
        'author_handle',
        'author_name',
        'text',
        'sentiment',
        'reach',
        'engagement',
        'mentioned_at',
    ];

    protected $casts = [
        'mentioned_at' => 'datetime',
        'reach' => 'integer',
        'engagement' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SmMonitoredKeyword::class, 'sm_monitored_keyword_id');
    }

    public function scopePositive($query)
    {
        return $query->where('sentiment', 'positive');
    }

    public function scopeNegative($query)
    {
        return $query->where('sentiment', 'negative');
    }
}
