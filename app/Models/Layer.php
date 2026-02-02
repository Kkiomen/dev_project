<?php

namespace App\Models;

use App\Enums\LayerType;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Layer extends Model
{
    use HasFactory, HasPublicId, HasPosition;

    /**
     * Valid semantic roles for AI classification.
     * Maps to SemanticTag enum values where applicable.
     */
    public const SEMANTIC_ROLES = [
        'header',
        'subtitle',
        'body',        // Maps to 'paragraph' SemanticTag
        'cta',
        'decoration',
        'main_image',
        'avatar',      // Maps to 'main_image' SemanticTag
        'logo',
        'background',
        'accent',      // Maps to 'primary_color' SemanticTag
        'social_handle',
        'date',        // Maps to 'paragraph' SemanticTag
        'quote',       // Maps to 'paragraph' SemanticTag
        'url',
    ];

    /**
     * Maps AI classification roles to SemanticTag values.
     */
    public const ROLE_TO_SEMANTIC_TAG = [
        'header' => 'header',
        'subtitle' => 'subtitle',
        'body' => 'paragraph',
        'cta' => 'cta',
        'main_image' => 'main_image',
        'avatar' => 'main_image',
        'logo' => 'logo',
        'accent' => 'primary_color',
        'social_handle' => 'social_handle',
        'date' => 'paragraph',
        'quote' => 'paragraph',
        'url' => 'url',
        // Note: 'decoration' and 'background' don't map to semantic tags (they're visual roles, not data substitution)
    ];

    protected $fillable = [
        'parent_id',
        'layer_key',
        'name',
        'type',
        'position',
        'visible',
        'locked',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'scale_x',
        'scale_y',
        'opacity',
        'properties',
        'semantic_role',
        'ai_confidence',
    ];

    protected $casts = [
        'type' => LayerType::class,
        'position' => 'integer',
        'visible' => 'boolean',
        'locked' => 'boolean',
        'x' => 'float',
        'y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'rotation' => 'float',
        'scale_x' => 'float',
        'scale_y' => 'float',
        'opacity' => 'float',
        'properties' => 'array',
        'ai_confidence' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'template_id';
    }

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Layer::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Layer::class, 'parent_id')->orderBy('position');
    }

    public function allDescendants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->children()->with('allDescendants');
    }

    /**
     * Check if this layer is a group.
     */
    public function isGroup(): bool
    {
        return $this->type === LayerType::GROUP;
    }

    /**
     * Get effective visibility (considering parent groups).
     */
    public function getEffectiveVisibility(): bool
    {
        if (!$this->visible) {
            return false;
        }

        if ($this->parent) {
            return $this->parent->getEffectiveVisibility();
        }

        return true;
    }

    // Accessors
    public function getEffectivePropertiesAttribute(): array
    {
        $defaults = $this->type->defaultProperties();
        return array_merge($defaults, $this->properties ?? []);
    }

    // Helper methods
    public function updateProperties(array $properties): self
    {
        $this->update([
            'properties' => array_merge($this->properties ?? [], $properties),
        ]);

        return $this;
    }

    public function setProperty(string $key, mixed $value): self
    {
        $properties = $this->properties ?? [];
        $properties[$key] = $value;
        $this->update(['properties' => $properties]);

        return $this;
    }

    public function setSemanticRole(string $role, float $confidence = 1.0): self
    {
        // Map the role to a semantic tag value if applicable
        $semanticTag = self::ROLE_TO_SEMANTIC_TAG[$role] ?? null;

        $properties = $this->properties ?? [];

        if ($semanticTag) {
            // Add the mapped semantic tag to the semanticTags array
            $existingTags = $properties['semanticTags'] ?? [];
            if (!in_array($semanticTag, $existingTags, true)) {
                // Determine if this is a content or style tag
                $tag = \App\Enums\SemanticTag::tryFrom($semanticTag);
                if ($tag) {
                    $category = $tag->category();
                    // Remove existing tags of the same category, then add new one
                    $existingTags = array_filter($existingTags, function ($t) use ($category) {
                        $existingTag = \App\Enums\SemanticTag::tryFrom($t);
                        return $existingTag && $existingTag->category() !== $category;
                    });
                    $existingTags[] = $semanticTag;
                    $properties['semanticTags'] = array_values($existingTags);
                }
            }
        }

        $this->update([
            'semantic_role' => $role,
            'ai_confidence' => $confidence,
            'properties' => $properties,
        ]);

        return $this;
    }

    public function hasSemanticRole(): bool
    {
        return $this->semantic_role !== null;
    }

    public function scopeWithSemanticRole($query, string $role)
    {
        return $query->where('semantic_role', $role);
    }

    public function scopeClassified($query)
    {
        return $query->whereNotNull('semantic_role');
    }

    public function scopeUnclassified($query)
    {
        return $query->whereNull('semantic_role');
    }

    public static function isValidSemanticRole(string $role): bool
    {
        return in_array($role, self::SEMANTIC_ROLES, true);
    }

    /**
     * Get the semantic tags array from properties.
     *
     * @return array
     */
    public function getSemanticTags(): array
    {
        return $this->properties['semanticTags'] ?? [];
    }

    /**
     * Set semantic tags (replaces all existing tags).
     *
     * @param array $tags Array of semantic tag values
     * @return self
     */
    public function setSemanticTags(array $tags): self
    {
        $properties = $this->properties ?? [];
        $properties['semanticTags'] = array_values(array_filter($tags, function ($tag) {
            return \App\Enums\SemanticTag::tryFrom($tag) !== null;
        }));
        $this->update(['properties' => $properties]);

        return $this;
    }

    /**
     * Add a semantic tag to the layer.
     * If a tag of the same category exists, it will be replaced.
     *
     * @param string $tagValue
     * @return self
     */
    public function addSemanticTag(string $tagValue): self
    {
        $tag = \App\Enums\SemanticTag::tryFrom($tagValue);
        if (!$tag) {
            return $this;
        }

        $properties = $this->properties ?? [];
        $existingTags = $properties['semanticTags'] ?? [];
        $category = $tag->category();

        // Remove existing tags of the same category
        $existingTags = array_filter($existingTags, function ($t) use ($category) {
            $existingTag = \App\Enums\SemanticTag::tryFrom($t);
            return $existingTag && $existingTag->category() !== $category;
        });

        $existingTags[] = $tagValue;
        $properties['semanticTags'] = array_values($existingTags);

        $this->update(['properties' => $properties]);

        return $this;
    }

    /**
     * Remove a semantic tag from the layer.
     *
     * @param string $tagValue
     * @return self
     */
    public function removeSemanticTag(string $tagValue): self
    {
        $properties = $this->properties ?? [];
        $existingTags = $properties['semanticTags'] ?? [];

        $existingTags = array_filter($existingTags, fn($t) => $t !== $tagValue);
        $properties['semanticTags'] = array_values($existingTags);

        $this->update(['properties' => $properties]);

        return $this;
    }

    /**
     * Check if layer has any semantic tags.
     *
     * @return bool
     */
    public function hasSemanticTags(): bool
    {
        $tags = $this->getSemanticTags();
        return !empty($tags);
    }

    /**
     * Get the content tag for this layer (if any).
     *
     * @return string|null
     */
    public function getContentTag(): ?string
    {
        foreach ($this->getSemanticTags() as $tagValue) {
            $tag = \App\Enums\SemanticTag::tryFrom($tagValue);
            if ($tag && $tag->category() === 'content') {
                return $tagValue;
            }
        }
        return null;
    }

    /**
     * Get the style tag for this layer (if any).
     *
     * @return string|null
     */
    public function getStyleTag(): ?string
    {
        foreach ($this->getSemanticTags() as $tagValue) {
            $tag = \App\Enums\SemanticTag::tryFrom($tagValue);
            if ($tag && $tag->category() === 'style') {
                return $tagValue;
            }
        }
        return null;
    }
}
