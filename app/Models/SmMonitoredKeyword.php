<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmMonitoredKeyword extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_monitored_keywords';

    protected $fillable = [
        'brand_id',
        'keyword',
        'platform',
        'category',
        'is_active',
        'mention_count',
        'last_mention_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mention_count' => 'integer',
        'last_mention_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(SmMention::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
