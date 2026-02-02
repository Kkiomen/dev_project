<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TemplateTag extends Model
{
    use HasFactory;

    public const CATEGORY_STYLE = 'style';
    public const CATEGORY_MOOD = 'mood';
    public const CATEGORY_COLOR = 'color';
    public const CATEGORY_LAYOUT = 'layout';

    public const VALID_CATEGORIES = [
        self::CATEGORY_STYLE,
        self::CATEGORY_MOOD,
        self::CATEGORY_COLOR,
        self::CATEGORY_LAYOUT,
    ];

    public const VALID_TAGS = [
        self::CATEGORY_STYLE => [
            'minimalist',
            'bold',
            'elegant',
            'modern',
            'vintage',
            'playful',
            'corporate',
            'creative',
        ],
        self::CATEGORY_MOOD => [
            'professional',
            'casual',
            'energetic',
            'calm',
            'luxurious',
            'friendly',
            'serious',
        ],
        self::CATEGORY_COLOR => [
            'dark',
            'light',
            'vibrant',
            'muted',
            'monochrome',
            'colorful',
            'pastel',
            'neon',
        ],
        self::CATEGORY_LAYOUT => [
            'centered',
            'asymmetric',
            'grid',
            'minimal-text',
            'text-heavy',
            'image-focused',
        ],
    ];

    protected $fillable = [
        'name',
        'name_pl',
        'category',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'template_tag')
            ->withPivot(['confidence', 'is_ai_generated'])
            ->withTimestamps();
    }

    public function incrementUsage(): self
    {
        $this->increment('usage_count');

        return $this;
    }

    public function decrementUsage(): self
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }

        return $this;
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderByDesc('usage_count')->limit($limit);
    }

    public static function findOrCreateByName(string $name, string $category): self
    {
        return self::firstOrCreate(
            ['name' => $name],
            ['category' => $category]
        );
    }

    public static function isValidTag(string $name, string $category): bool
    {
        if (!isset(self::VALID_TAGS[$category])) {
            return false;
        }

        return in_array($name, self::VALID_TAGS[$category], true);
    }
}
