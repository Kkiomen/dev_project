<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmBrandKit extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_brand_kits';

    protected $fillable = [
        'brand_id',
        'colors',
        'fonts',
        'logo_path',
        'logo_dark_path',
        'style_preset',
        'tone_of_voice',
        'voice_attributes',
        'content_pillars',
        'hashtag_groups',
        'brand_guidelines_notes',
    ];

    protected $casts = [
        'colors' => 'array',
        'fonts' => 'array',
        'voice_attributes' => 'array',
        'content_pillars' => 'array',
        'hashtag_groups' => 'array',
    ];

    protected $attributes = [
        'colors' => '{}',
        'fonts' => '{}',
        'voice_attributes' => '[]',
        'content_pillars' => '[]',
        'hashtag_groups' => '{}',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Color helpers
    public function getPrimaryColor(): ?string
    {
        return $this->colors['primary'] ?? null;
    }

    public function getSecondaryColor(): ?string
    {
        return $this->colors['secondary'] ?? null;
    }

    public function getAccentColor(): ?string
    {
        return $this->colors['accent'] ?? null;
    }

    // Font helpers
    public function getHeadingFont(): ?array
    {
        return $this->fonts['heading'] ?? null;
    }

    public function getBodyFont(): ?array
    {
        return $this->fonts['body'] ?? null;
    }

    // Content pillar helpers
    public function getContentPillars(): array
    {
        return $this->content_pillars ?? [];
    }

    public function getHashtagGroup(string $group): array
    {
        return $this->hashtag_groups[$group] ?? [];
    }

    public function getBrandedHashtags(): array
    {
        return $this->getHashtagGroup('branded');
    }

    public function getIndustryHashtags(): array
    {
        return $this->getHashtagGroup('industry');
    }

    // Build context for AI
    public function buildAiContext(): array
    {
        return [
            'style_preset' => $this->style_preset,
            'tone_of_voice' => $this->tone_of_voice,
            'voice_attributes' => $this->voice_attributes ?? [],
            'content_pillars' => $this->content_pillars ?? [],
            'colors' => $this->colors ?? [],
            'hashtag_groups' => $this->hashtag_groups ?? [],
        ];
    }
}
