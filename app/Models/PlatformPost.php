<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPost extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'social_post_id',
        'platform',
        'enabled',
        'platform_caption',
        'video_title',
        'video_description',
        'hashtags',
        'link_preview',
        'publish_status',
        'published_at',
        'external_id',
        'external_url',
        'error_message',
        'platform_data',
    ];

    protected $casts = [
        'platform' => Platform::class,
        'publish_status' => PublishStatus::class,
        'enabled' => 'boolean',
        'hashtags' => 'array',
        'link_preview' => 'array',
        'platform_data' => 'array',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'publish_status' => 'not_started',
    ];

    // Relationships
    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    // Helper methods
    public function getEffectiveCaption(): string
    {
        return $this->platform_caption ?? $this->socialPost->main_caption;
    }

    public function hasOverride(): bool
    {
        return !is_null($this->platform_caption);
    }

    public function syncFromMain(): self
    {
        $this->platform_caption = null;
        $this->hashtags = null;
        $this->link_preview = null;
        $this->video_title = null;
        $this->video_description = null;
        $this->save();

        return $this;
    }

    public function setOverride(string $caption): self
    {
        $this->platform_caption = $caption;
        $this->save();

        return $this;
    }

    public function enable(): self
    {
        $this->enabled = true;
        $this->save();

        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        $this->save();

        return $this;
    }

    public function markAsPublished(?string $externalId = null, ?string $externalUrl = null): self
    {
        $this->publish_status = PublishStatus::Published;
        $this->published_at = now();
        if ($externalId) {
            $this->external_id = $externalId;
        }
        if ($externalUrl) {
            $this->external_url = $externalUrl;
        }
        $this->save();

        return $this;
    }

    public function markAsFailed(?string $error = null): self
    {
        $this->publish_status = PublishStatus::Failed;
        if ($error) {
            $this->error_message = $error;
        }
        $this->save();

        return $this;
    }

    public function markAsPending(): self
    {
        $this->publish_status = PublishStatus::Pending;
        $this->save();

        return $this;
    }

    /**
     * Get the caption to use for publishing.
     */
    public function getCaption(): string
    {
        return $this->platform_caption ?? $this->socialPost->main_caption ?? '';
    }

    public function isFacebook(): bool
    {
        return $this->platform === Platform::Facebook;
    }

    public function isInstagram(): bool
    {
        return $this->platform === Platform::Instagram;
    }

    public function isYouTube(): bool
    {
        return $this->platform === Platform::YouTube;
    }

    public function getFormattedHashtags(): string
    {
        if (empty($this->hashtags)) {
            return '';
        }

        return implode(' ', array_map(fn($tag) => '#' . ltrim($tag, '#'), $this->hashtags));
    }
}
