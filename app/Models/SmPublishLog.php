<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmPublishLog extends Model
{
    protected $table = 'sm_publish_logs';

    protected $fillable = [
        'sm_scheduled_post_id',
        'action',
        'http_status',
        'request_payload',
        'response_payload',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(SmScheduledPost::class, 'sm_scheduled_post_id');
    }

    public function isSuccess(): bool
    {
        return $this->http_status >= 200 && $this->http_status < 300;
    }
}
