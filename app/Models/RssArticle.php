<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssArticle extends Model
{
    use HasPublicId;

    protected $fillable = [
        'rss_feed_id',
        'brand_id',
        'guid',
        'guid_hash',
        'title',
        'description',
        'url',
        'author',
        'image_url',
        'categories',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $article) {
            if ($article->isDirty('guid')) {
                $article->guid_hash = hash('sha256', $article->guid);
            }
        });
    }

    protected $casts = [
        'categories' => 'array',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function feed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class, 'rss_feed_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Scopes

    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeForFeed(Builder $query, RssFeed $feed): Builder
    {
        return $query->where('rss_feed_id', $feed->id);
    }

    public function scopePublishedAfter(Builder $query, $date): Builder
    {
        return $query->where('published_at', '>=', $date);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
