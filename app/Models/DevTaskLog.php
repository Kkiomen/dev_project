<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevTaskLog extends Model
{
    use HasPublicId;

    protected $fillable = [
        'dev_task_id',
        'type',
        'content',
        'metadata',
        'user_id',
        'success',
    ];

    protected $casts = [
        'metadata' => 'array',
        'success' => 'boolean',
    ];

    public const TYPE_BOT_TRIGGER = 'bot_trigger';
    public const TYPE_BOT_RESPONSE = 'bot_response';
    public const TYPE_PLAN_GENERATION = 'plan_generation';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_COMMENT = 'comment';

    public const TYPES = [
        self::TYPE_BOT_TRIGGER,
        self::TYPE_BOT_RESPONSE,
        self::TYPE_PLAN_GENERATION,
        self::TYPE_STATUS_CHANGE,
        self::TYPE_COMMENT,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DevTask::class, 'dev_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }
}
