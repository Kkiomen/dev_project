<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'user_id',
        'base_id',
        'name',
        'description',
        'width',
        'height',
        'background_color',
        'background_image',
        'thumbnail_path',
        'settings',
        'position',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'user_id';
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(Layer::class)->ordered();
    }

    public function fonts(): HasMany
    {
        return $this->hasMany(TemplateFont::class);
    }

    public function generatedImages(): HasMany
    {
        return $this->hasMany(GeneratedImage::class);
    }

    // Scopes
    public function scopeWithLayers($query)
    {
        return $query->with('layers');
    }

    public function scopeWithFonts($query)
    {
        return $query->with('fonts');
    }

    // Helper methods
    public function addLayer(string $name, string $type, array $attributes = []): Layer
    {
        return $this->layers()->create(array_merge([
            'name' => $name,
            'type' => $type,
        ], $attributes));
    }

    public function duplicate(): Template
    {
        $newTemplate = $this->replicate(['public_id']);
        $newTemplate->name = $this->name . ' (copy)';
        $newTemplate->thumbnail_path = null;
        $newTemplate->save();

        foreach ($this->layers as $layer) {
            $newLayer = $layer->replicate(['public_id']);
            $newLayer->template_id = $newTemplate->id;
            $newLayer->save();
        }

        foreach ($this->fonts as $font) {
            $newFont = $font->replicate();
            $newFont->template_id = $newTemplate->id;
            $newFont->save();
        }

        return $newTemplate->load('layers', 'fonts');
    }
}
