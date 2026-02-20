<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CiCompetitorAccount extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'ci_competitor_accounts';

    protected $fillable = [
        'ci_competitor_id',
        'platform',
        'handle',
        'external_id',
        'profile_data',
        'last_scraped_at',
    ];

    protected $casts = [
        'profile_data' => 'array',
        'last_scraped_at' => 'datetime',
    ];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(CiCompetitor::class, 'ci_competitor_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CiCompetitorPost::class, 'ci_competitor_account_id');
    }

    public function needsRefresh(int $ttlDays = 7): bool
    {
        if (!$this->last_scraped_at) {
            return true;
        }

        return $this->last_scraped_at->diffInDays(now()) >= $ttlDays;
    }

    public function getFollowersCount(): ?int
    {
        return $this->profile_data['followers_count'] ?? null;
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeNeedsRefresh(Builder $query, int $ttlDays = 7): Builder
    {
        return $query->where(function ($q) use ($ttlDays) {
            $q->whereNull('last_scraped_at')
              ->orWhere('last_scraped_at', '<', now()->subDays($ttlDays));
        });
    }
}
