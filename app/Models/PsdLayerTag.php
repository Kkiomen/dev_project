<?php

namespace App\Models;

use App\Enums\SemanticTag;
use Illuminate\Database\Eloquent\Model;

class PsdLayerTag extends Model
{
    protected $fillable = [
        'psd_filename',
        'layer_path',
        'semantic_tag',
        'is_variant',
    ];

    protected $casts = [
        'is_variant' => 'boolean',
    ];

    /**
     * Get the semantic tag as enum if set.
     */
    public function getSemanticTagEnum(): ?SemanticTag
    {
        return $this->semantic_tag ? SemanticTag::tryFrom($this->semantic_tag) : null;
    }

    /**
     * Scope to find tags for a specific PSD file.
     */
    public function scopeForFile($query, string $filename)
    {
        return $query->where('psd_filename', $filename);
    }

    /**
     * Scope to find only variant layers.
     */
    public function scopeVariants($query)
    {
        return $query->where('is_variant', true);
    }

    /**
     * Scope to find only tagged layers.
     */
    public function scopeTagged($query)
    {
        return $query->whereNotNull('semantic_tag');
    }

    /**
     * Update or create tag for a specific layer.
     */
    public static function setTag(
        string $psdFilename,
        string $layerPath,
        ?string $semanticTag = null,
        ?bool $isVariant = null
    ): self {
        $attributes = [];

        if ($semanticTag !== null) {
            $attributes['semantic_tag'] = $semanticTag ?: null;
        }

        if ($isVariant !== null) {
            $attributes['is_variant'] = $isVariant;
        }

        return self::updateOrCreate(
            [
                'psd_filename' => $psdFilename,
                'layer_path' => $layerPath,
            ],
            $attributes
        );
    }

    /**
     * Bulk update tags for a PSD file.
     *
     * @param string $psdFilename
     * @param array $tags Array of ['layer_path' => ..., 'semantic_tag' => ..., 'is_variant' => ...]
     */
    public static function bulkUpdate(string $psdFilename, array $tags): void
    {
        foreach ($tags as $tag) {
            self::setTag(
                $psdFilename,
                $tag['layer_path'],
                $tag['semantic_tag'] ?? null,
                $tag['is_variant'] ?? null
            );
        }
    }

    /**
     * Get all tags for a PSD file as an associative array.
     *
     * @param string $psdFilename
     * @return array Keyed by layer_path
     */
    public static function getTagsForFile(string $psdFilename): array
    {
        return self::forFile($psdFilename)
            ->get()
            ->keyBy('layer_path')
            ->map(fn ($tag) => [
                'semantic_tag' => $tag->semantic_tag,
                'is_variant' => $tag->is_variant,
            ])
            ->toArray();
    }

    /**
     * Get variant layer paths for a PSD file.
     *
     * @param string $psdFilename
     * @return array
     */
    public static function getVariantPaths(string $psdFilename): array
    {
        return self::forFile($psdFilename)
            ->variants()
            ->pluck('layer_path')
            ->toArray();
    }
}
