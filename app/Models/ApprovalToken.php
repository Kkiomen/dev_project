<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ApprovalToken extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'user_id',
        'token',
        'client_name',
        'client_email',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApprovalToken $token) {
            if (empty($token->token)) {
                $token->token = Str::random(64);
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PostApproval::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    // Helper methods
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function revoke(): self
    {
        $this->is_active = false;
        $this->save();

        return $this;
    }

    public function regenerate(): self
    {
        $this->token = Str::random(64);
        $this->save();

        return $this;
    }

    public function extendExpiration(\DateTimeInterface $expiresAt): self
    {
        $this->expires_at = $expiresAt;
        $this->save();

        return $this;
    }

    public function getPendingPostsCount(): int
    {
        return $this->approvals()
            ->whereNull('is_approved')
            ->count();
    }

    public function getApprovalUrl(): string
    {
        return url("/approve/{$this->token}");
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)->first();
    }

    public static function findValidByToken(string $token): ?self
    {
        return static::where('token', $token)->active()->first();
    }
}
