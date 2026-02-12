<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmCrisisAlert extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_crisis_alerts';

    protected $fillable = [
        'brand_id',
        'severity',
        'trigger_type',
        'description',
        'related_items',
        'is_resolved',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'related_items' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    public function resolve(string $notes = null): self
    {
        $this->is_resolved = true;
        $this->resolved_at = now();
        $this->resolution_notes = $notes;
        $this->save();

        return $this;
    }
}
