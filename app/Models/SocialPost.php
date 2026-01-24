<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class SocialPost extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'user_id',
        'brand_id',
        'title',
        'main_caption',
        'status',
        'scheduled_at',
        'published_at',
        'settings',
        'position',
    ];

    protected $casts = [
        'status' => PostStatus::class,
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'user_id';
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platformPosts(): HasMany
    {
        return $this->hasMany(PlatformPost::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->ordered();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PostApproval::class);
    }

    // Scopes
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeWithStatus(Builder $query, PostStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeScheduledBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PostStatus::PendingApproval);
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Draft);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereIn('status', [PostStatus::Approved, PostStatus::Scheduled])
            ->whereNotNull('scheduled_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published);
    }

    // Helper methods
    public function getPlatformPost(Platform $platform): ?PlatformPost
    {
        return $this->platformPosts->firstWhere('platform', $platform->value);
    }

    public function getEnabledPlatforms(): array
    {
        return $this->platformPosts
            ->where('enabled', true)
            ->pluck('platform')
            ->toArray();
    }

    public function createPlatformPosts(): void
    {
        foreach (Platform::cases() as $platform) {
            $this->platformPosts()->firstOrCreate(
                ['platform' => $platform->value],
                ['enabled' => true]
            );
        }
    }

    public function syncPlatformContent(): void
    {
        foreach ($this->platformPosts as $platformPost) {
            if ($platformPost->enabled && is_null($platformPost->platform_caption)) {
                $platformPost->syncFromMain();
            }
        }
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canDelete(): bool
    {
        return $this->status->canDelete();
    }

    public function canSchedule(): bool
    {
        return $this->status->canSchedule();
    }

    public function requestApproval(): self
    {
        $this->status = PostStatus::PendingApproval;
        $this->save();

        return $this;
    }

    public function approve(): self
    {
        $this->status = PostStatus::Approved;
        $this->save();

        return $this;
    }

    public function schedule(\DateTimeInterface $scheduledAt): self
    {
        $this->scheduled_at = $scheduledAt;
        $this->status = PostStatus::Scheduled;
        $this->save();

        return $this;
    }

    public function markAsPublished(): self
    {
        $this->status = PostStatus::Published;
        $this->published_at = now();
        $this->save();

        return $this;
    }

    public function markAsFailed(): self
    {
        $this->status = PostStatus::Failed;
        $this->save();

        return $this;
    }

    public function duplicate(): SocialPost
    {
        $newPost = $this->replicate(['public_id', 'status', 'scheduled_at', 'published_at']);
        $newPost->title = $this->title . ' (copy)';
        $newPost->status = PostStatus::Draft;
        $newPost->save();

        foreach ($this->platformPosts as $platformPost) {
            $newPlatformPost = $platformPost->replicate(['public_id', 'publish_status', 'published_at']);
            $newPlatformPost->social_post_id = $newPost->id;
            $newPlatformPost->publish_status = 'pending';
            $newPlatformPost->save();
        }

        foreach ($this->media as $mediaItem) {
            $newMedia = $mediaItem->replicate(['public_id']);
            $newMedia->social_post_id = $newPost->id;
            $newMedia->save();
        }

        return $newPost->load('platformPosts', 'media');
    }

    public function getFirstMediaUrl(): ?string
    {
        $firstMedia = $this->media->first();

        if (!$firstMedia) {
            return null;
        }

        return $firstMedia->url;
    }
}
