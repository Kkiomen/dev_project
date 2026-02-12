<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmDesignTemplate extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_design_templates';

    protected $fillable = [
        'brand_id',
        'name',
        'type',
        'platform',
        'canvas_json',
        'width',
        'height',
        'thumbnail_path',
        'category',
        'tags',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'canvas_json' => 'array',
        'tags' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function generatedAssets(): HasMany
    {
        return $this->hasMany(SmGeneratedAsset::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeForBrand($query, int $brandId)
    {
        return $query->where(function ($q) use ($brandId) {
            $q->where('brand_id', $brandId)->orWhere('is_system', true);
        });
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where(function ($q) use ($platform) {
            $q->where('platform', $platform)->orWhereNull('platform');
        });
    }

    // Helpers
    public function getDimensions(): string
    {
        return "{$this->width}x{$this->height}";
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null;
    }
}
