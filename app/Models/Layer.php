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
