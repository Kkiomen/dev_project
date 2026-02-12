<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmMessage extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_messages';

    protected $fillable = [
        'brand_id',
        'platform',
        'external_thread_id',
        'from_handle',
        'from_name',
        'from_avatar',
        'text',
        'direction',
        'is_read',
        'auto_replied',
        'sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'auto_replied' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function markAsRead(): self
    {
        $this->is_read = true;
        $this->save();

        return $this;
    }
}
