<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmAutoReplyRule extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_auto_reply_rules';

    protected $fillable = [
        'brand_id',
        'trigger_type',
        'trigger_value',
        'response_template',
        'requires_approval',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incrementUsage(): self
    {
        $this->increment('usage_count');

        return $this;
    }
}
