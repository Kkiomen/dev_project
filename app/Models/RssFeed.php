<?php

namespace App\Models;

use App\Enums\RssFeedStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RssFeed extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'name',
        'url',
        'url_hash',
        'site_url',
        'status',
        'last_error',
        'last_fetched_at',
        'articles_count',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $feed) {
            if ($feed->isDirty('url')) {
                $feed->url_hash = hash('sha256', $feed->url);
            }
        });
    }

    protected $casts = [
        'status' => RssFeedStatus::class,
        'last_fetched_at' => 'datetime',
        'articles_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(RssArticle::class);
    }

    // Scopes

    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RssFeedStatus::Active);
    }

    public function scopeDueForFetch(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereNull('last_fetched_at')
                    ->orWhere('last_fetched_at', '<', now()->subMinutes(30));
            });
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === RssFeedStatus::Active;
    }

    public function markError(string $error): void
    {
        $this->update([
            'status' => RssFeedStatus::Error,
            'last_error' => $error,
        ]);
    }

    public function markFetched(): void
    {
        $this->update([
            'last_fetched_at' => now(),
            'last_error' => null,
            'articles_count' => $this->articles()->count(),
        ]);
    }
}
