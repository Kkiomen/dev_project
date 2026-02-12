<?php

namespace App\Models;

use App\Enums\Platform;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmAccount extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_accounts';

    protected $fillable = [
        'brand_id',
        'platform',
        'platform_user_id',
        'handle',
        'display_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'metadata',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Encrypt access token
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    // Encrypt refresh token
    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function isConnected(): bool
    {
        return $this->isActive() && !$this->isExpired() && $this->access_token !== null;
    }

    public function getPlatformEnum(): Platform
    {
        return Platform::from($this->platform);
    }

    public function getFollowersCount(): ?int
    {
        return $this->metadata['followers_count'] ?? null;
    }

    public function markAsExpired(): self
    {
        $this->status = 'expired';
        $this->save();

        return $this;
    }

    public function markAsRevoked(): self
    {
        $this->status = 'revoked';
        $this->access_token = null;
        $this->refresh_token = null;
        $this->save();

        return $this;
    }
}
