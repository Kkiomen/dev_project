<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentQueue extends Model
{
    use HasFactory;

    protected $table = 'content_queue';

    protected $fillable = [
        'brand_id',
        'pillar_name',
        'platform',
        'target_date',
        'target_time',
        'topic',
        'content_type',
        'status',
        'social_post_id',
        'generation_error',
        'generation_attempts',
    ];

    protected $casts = [
        'target_date' => 'date',
        'generation_attempts' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeGenerating(Builder $query): Builder
    {
        return $query->where('status', 'generating');
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeForDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('target_date', [$startDate, $endDate]);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('target_date')->orderBy('target_time');
    }

    // Status methods
    public function markAsGenerating(): self
    {
        $this->status = 'generating';
        $this->generation_attempts++;
        $this->generation_error = null;
        $this->save();

        return $this;
    }

    public function markAsReady(SocialPost $post): self
    {
        $this->status = 'ready';
        $this->social_post_id = $post->id;
        $this->generation_error = null;
        $this->save();

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->status = 'failed';
        $this->generation_error = $error;
        $this->save();

        return $this;
    }

    public function markAsPublished(): self
    {
        $this->status = 'published';
        $this->save();

        return $this;
    }

    public function resetToPending(): self
    {
        $this->status = 'pending';
        $this->generation_error = null;
        $this->save();

        return $this;
    }

    // Helper methods
    public function attachPost(SocialPost $post): self
    {
        $this->social_post_id = $post->id;
        $this->save();

        return $this;
    }

    public function getScheduledDateTime(): ?\DateTimeInterface
    {
        if (!$this->target_date || !$this->target_time) {
            return null;
        }

        return $this->target_date->setTimeFromTimeString($this->target_time);
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->generation_attempts < 3;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
