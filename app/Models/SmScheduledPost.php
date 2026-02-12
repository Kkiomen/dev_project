<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmScheduledPost extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_scheduled_posts';

    protected $fillable = [
        'brand_id',
        'social_post_id',
        'platform',
        'scheduled_at',
        'published_at',
        'status',
        'approval_status',
        'approval_notes',
        'approved_by',
        'approved_at',
        'retry_count',
        'max_retries',
        'error_message',
        'external_post_id',
        'platform_response',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'platform_response' => 'array',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publishLogs(): HasMany
    {
        return $this->hasMany(SmPublishLog::class);
    }

    // Scopes
    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'scheduled')
            ->where('approval_status', 'approved')
            ->where('scheduled_at', '<=', now());
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < $this->max_retries;
    }

    public function approve(int $userId, ?string $notes = null): self
    {
        $this->approval_status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;
        $this->status = 'scheduled';
        $this->save();

        return $this;
    }

    public function reject(int $userId, ?string $notes = null): self
    {
        $this->approval_status = 'rejected';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;
        $this->status = 'cancelled';
        $this->save();

        return $this;
    }

    public function markAsPublished(string $externalId = null, array $response = null): self
    {
        $this->status = 'published';
        $this->published_at = now();
        $this->external_post_id = $externalId;
        $this->platform_response = $response;
        $this->save();

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->status = 'failed';
        $this->error_message = $error;
        $this->retry_count++;
        $this->save();

        return $this;
    }
}
