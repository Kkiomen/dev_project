<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmContentTemplate extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_content_templates';

    protected $fillable = [
        'brand_id',
        'name',
        'category',
        'platform',
        'prompt_template',
        'variables',
        'content_type',
        'is_system',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Helpers
    public function incrementUsage(): self
    {
        $this->increment('usage_count');

        return $this;
    }

    public function renderPrompt(array $variables = []): string
    {
        $prompt = $this->prompt_template;

        foreach ($variables as $key => $value) {
            $prompt = str_replace("{{{$key}}}", $value, $prompt);
        }

        return $prompt;
    }
}
