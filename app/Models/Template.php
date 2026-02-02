<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'user_id',
        'template_group_id',
        'variant_order',
        'psd_import_id',
        'brand_id',
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
        'is_library',
        'library_category',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'variant_order' => 'integer',
        'settings' => 'array',
        'is_library' => 'boolean',
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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TemplateGroup::class, 'template_group_id');
    }

    public function psdImport(): BelongsTo
    {
        return $this->belongsTo(PsdImport::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TemplateTag::class, 'template_tag')
            ->withPivot(['confidence', 'is_ai_generated'])
            ->withTimestamps();
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
        return $query->with('layers.parent');
    }

    public function scopeWithFonts($query)
    {
        return $query->with('fonts');
    }

    public function scopeLibrary($query)
    {
        return $query->where('is_library', true);
    }

    public function scopeUserTemplates($query)
    {
        return $query->where('is_library', false);
    }

    public function scopePrimaryOnly($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('template_group_id')
              ->orWhere('variant_order', 1);
        });
    }

    public function scopeWithStyleTags($query, array $tags)
    {
        return $query->whereHas('tags', function ($q) use ($tags) {
            $q->whereIn('name', $tags);
        });
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

        // Copy layers with parent_id remapping
        $this->copyLayersTo($newTemplate);

        foreach ($this->fonts as $font) {
            $newFont = $font->replicate();
            $newFont->template_id = $newTemplate->id;
            $newFont->save();
        }

        return $newTemplate->load('layers.parent', 'fonts');
    }

    /**
     * Copy this template to a user's collection.
     */
    public function copyToUser(int $userId): Template
    {
        $newTemplate = $this->replicate(['public_id', 'is_library', 'library_category']);
        $newTemplate->user_id = $userId;
        $newTemplate->is_library = false;
        $newTemplate->library_category = null;
        $newTemplate->thumbnail_path = null;
        $newTemplate->save();

        // Copy layers with parent_id remapping
        $this->copyLayersTo($newTemplate);

        foreach ($this->fonts as $font) {
            $newFont = $font->replicate();
            $newFont->template_id = $newTemplate->id;
            $newFont->save();
        }

        return $newTemplate->load('layers.parent', 'fonts');
    }

    /**
     * Copy current template to library as a new template.
     * Original template remains unchanged - user can continue working on it.
     */
    public function copyToLibrary(?string $category = null, ?string $thumbnailPath = null): Template
    {
        // Create a copy of the template for library
        $libraryTemplate = $this->replicate(['public_id', 'is_library', 'library_category', 'thumbnail_path']);
        $libraryTemplate->is_library = true;
        $libraryTemplate->library_category = $category;
        $libraryTemplate->thumbnail_path = $thumbnailPath;
        $libraryTemplate->save();

        // Copy layers with parent_id remapping
        $this->copyLayersTo($libraryTemplate);

        // Copy all fonts
        foreach ($this->fonts as $font) {
            $newFont = $font->replicate();
            $newFont->template_id = $libraryTemplate->id;
            $newFont->save();
        }

        return $libraryTemplate->load('layers.parent', 'fonts');
    }

    /**
     * Copy all layers to another template with proper parent_id remapping.
     */
    protected function copyLayersTo(Template $targetTemplate): void
    {
        // Build mapping from old layer ID to new layer ID
        $idMapping = [];

        // First pass: create all layers without parent_id
        foreach ($this->layers as $layer) {
            $newLayer = $layer->replicate(['public_id', 'parent_id']);
            $newLayer->template_id = $targetTemplate->id;
            $newLayer->parent_id = null;
            $newLayer->save();

            $idMapping[$layer->id] = $newLayer->id;
        }

        // Second pass: update parent_id using the mapping
        foreach ($this->layers as $layer) {
            if ($layer->parent_id && isset($idMapping[$layer->parent_id])) {
                $newLayerId = $idMapping[$layer->id];
                Layer::where('id', $newLayerId)->update([
                    'parent_id' => $idMapping[$layer->parent_id],
                ]);
            }
        }
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        return asset('storage/' . $this->thumbnail_path);
    }

    /**
     * Remove from library.
     */
    public function removeFromLibrary(): self
    {
        $this->is_library = false;
        $this->library_category = null;
        $this->save();

        return $this;
    }
}
