<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmAlertRule extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_alert_rules';

    protected $fillable = [
        'brand_id',
        'alert_type',
        'threshold',
        'timeframe',
        'notify_via',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'notify_via' => 'array',
        'is_active' => 'boolean',
        'threshold' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
