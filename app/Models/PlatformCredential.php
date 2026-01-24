<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'platform',
        'platform_user_id',
        'platform_user_name',
        'access_token',
        'token_expires_at',
        'refresh_token',
        'metadata',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Encrypt access token before saving
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    // Decrypt access token when reading
    public function getAccessTokenAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    // Encrypt refresh token before saving
    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }

    // Decrypt refresh token when reading
    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    // Helpers
    public function isExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false; // Page tokens don't expire
        }

        return $this->token_expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->subDays($days)->isPast();
    }

    public function getPageId(): ?string
    {
        return $this->metadata['page_id'] ?? null;
    }

    public function getInstagramBusinessId(): ?string
    {
        return $this->metadata['instagram_business_id'] ?? null;
    }

    public function getFacebookPageId(): ?string
    {
        return $this->metadata['facebook_page_id'] ?? null;
    }
}
