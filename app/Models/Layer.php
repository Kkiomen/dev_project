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
        'properties',
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
        'properties' => 'array',
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
}
