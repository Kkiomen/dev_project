<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Elevation Service for Multi-layer Shadow Physics.
 *
 * Implements Material Design-like elevation levels with multi-layer shadows
 * for premium depth and visual hierarchy.
 */
class ElevationService
{
    /**
     * Baseline shadow unit for calculations.
     */
    public const SHADOW_UNIT = 1;

    /**
     * Elevation levels with shadow definitions.
     * Each level has multiple shadow layers for realism.
     * Based on Material Design principles.
     */
    public const ELEVATION_LEVELS = [
        0 => [], // No shadow (flat)
        1 => [
            ['blur' => 2, 'offsetY' => 1, 'opacity' => 0.08],
            ['blur' => 3, 'offsetY' => 1, 'opacity' => 0.05],
        ],
        2 => [
            ['blur' => 4, 'offsetY' => 2, 'opacity' => 0.10],
            ['blur' => 5, 'offsetY' => 2, 'opacity' => 0.06],
        ],
        3 => [
            ['blur' => 8, 'offsetY' => 4, 'opacity' => 0.12],
            ['blur' => 10, 'offsetY' => 4, 'opacity' => 0.08],
        ],
        4 => [
            ['blur' => 16, 'offsetY' => 8, 'opacity' => 0.14],
            ['blur' => 20, 'offsetY' => 8, 'opacity' => 0.10],
        ],
        5 => [
            ['blur' => 24, 'offsetY' => 12, 'opacity' => 0.16],
            ['blur' => 32, 'offsetY' => 12, 'opacity' => 0.12],
        ],
    ];

    /**
     * Premium shadow styles for modern Instagram-quality design.
     * Soft glow: diffuse shadows with no vertical offset for floating effect.
     */
    public const SHADOW_STYLES = [
        'soft_glow' => [
            'blur' => [30, 45, 60],       // Low, medium, high intensity
            'opacity' => [0.10, 0.12, 0.15],
            'offsetX' => 0,
            'offsetY' => 0,               // No offset = diffuse glow
        ],
        'ambient' => [
            'blur' => [20, 30, 40],
            'opacity' => [0.08, 0.10, 0.12],
            'offsetX' => 0,
            'offsetY' => [2, 4, 6],
        ],
    ];

    /**
     * Recommended elevation by layer type.
     */
    public const LAYER_ELEVATIONS = [
        'background' => 0,
        'image' => 0,
        'photo' => 0,
        'overlay' => 0,
        'accent' => 1,
        'line' => 0,
        'text' => 0,
        'headline' => 0,
        'subtext' => 0,
        'cta' => 3,      // CTA buttons should float
        'button' => 3,   // Buttons float
        'textbox' => 3,  // Textbox buttons float
        'card' => 2,
        'panel' => 2,
    ];

    /**
     * Get shadow properties for elevation level.
     * Returns primary shadow (for systems supporting single shadow).
     */
    public function getShadowForElevation(int $level): array
    {
        $level = max(0, min(5, $level)); // Clamp to 0-5
        $shadows = self::ELEVATION_LEVELS[$level] ?? [];

        if (empty($shadows)) {
            return [
                'shadowEnabled' => false,
            ];
        }

        // Return primary shadow (first layer - most prominent)
        $primary = $shadows[0];

        return [
            'shadowEnabled' => true,
            'shadowColor' => '#000000',
            'shadowBlur' => $primary['blur'],
            'shadowOffsetX' => 0,
            'shadowOffsetY' => $primary['offsetY'],
            'shadowOpacity' => $primary['opacity'],
        ];
    }

    /**
     * Get all shadow layers for elevation (for advanced rendering).
     */
    public function getAllShadowLayers(int $level): array
    {
        $level = max(0, min(5, $level));
        return self::ELEVATION_LEVELS[$level] ?? [];
    }

    /**
     * Get recommended elevation for layer type.
     */
    public function getElevationForLayerType(string $type, string $name = ''): int
    {
        $nameLower = strtolower($name);

        // CTA/button detection by name
        if (str_contains($nameLower, 'cta') || str_contains($nameLower, 'button')) {
            return 3;
        }

        // Card/panel detection by name
        if (str_contains($nameLower, 'card') || str_contains($nameLower, 'panel')) {
            return 2;
        }

        // Accent detection
        if (str_contains($nameLower, 'accent') || str_contains($nameLower, 'highlight')) {
            return 1;
        }

        // Default by type
        return self::LAYER_ELEVATIONS[$type] ?? 0;
    }

    /**
     * Apply elevation shadows to a single layer.
     */
    public function applyElevationToLayer(array $layer): array
    {
        $type = $layer['type'] ?? 'rectangle';
        $name = $layer['name'] ?? '';

        $elevation = $this->getElevationForLayerType($type, $name);

        // Only apply shadow if elevation > 0
        if ($elevation > 0) {
            $shadowProps = $this->getShadowForElevation($elevation);

            $layer['properties'] = array_merge(
                $layer['properties'] ?? [],
                $shadowProps
            );
        }

        return $layer;
    }

    /**
     * Apply elevation shadows to all layers.
     */
    public function applyElevationToLayers(array $layers): array
    {
        return array_map([$this, 'applyElevationToLayer'], $layers);
    }

    /**
     * Get CSS box-shadow value for elevation level.
     * Useful for frontend rendering or debugging.
     */
    public function getCssBoxShadow(int $level): string
    {
        $shadows = $this->getAllShadowLayers($level);

        if (empty($shadows)) {
            return 'none';
        }

        $cssShadows = array_map(function ($shadow) {
            $opacity = $shadow['opacity'];
            $blur = $shadow['blur'];
            $offsetY = $shadow['offsetY'];

            return "0 {$offsetY}px {$blur}px rgba(0, 0, 0, {$opacity})";
        }, $shadows);

        return implode(', ', $cssShadows);
    }

    /**
     * Create a floating effect for interactive elements.
     */
    public function getFloatingEffect(int $baseElevation = 3): array
    {
        return [
            'normal' => $this->getShadowForElevation($baseElevation),
            'hover' => $this->getShadowForElevation(min(5, $baseElevation + 1)),
            'pressed' => $this->getShadowForElevation(max(0, $baseElevation - 1)),
        ];
    }

    /**
     * Get soft glow shadow for modern Instagram-style design.
     * Soft glow creates a diffuse, centered shadow without directional offset.
     *
     * @param int $intensity 1 = subtle, 2 = medium, 3 = strong
     */
    public function getSoftGlowShadow(int $intensity = 2): array
    {
        $index = max(0, min(2, $intensity - 1));
        $style = self::SHADOW_STYLES['soft_glow'];

        $shadow = [
            'shadowEnabled' => true,
            'shadowColor' => '#000000',
            'shadowBlur' => $style['blur'][$index],
            'shadowOffsetX' => $style['offsetX'],
            'shadowOffsetY' => $style['offsetY'],
            'shadowOpacity' => $style['opacity'][$index],
        ];

        Log::channel('single')->debug('ElevationService: Generated soft glow shadow', [
            'intensity' => $intensity,
            'blur' => $shadow['shadowBlur'],
            'opacity' => $shadow['shadowOpacity'],
        ]);

        return $shadow;
    }

    /**
     * Apply soft glow shadow to CTA/button layers.
     * Modern Instagram posts use soft, diffuse shadows instead of directional ones.
     */
    public function applySoftGlowToCtaLayers(array $layers, int $intensity = 2): array
    {
        $softGlow = $this->getSoftGlowShadow($intensity);
        $appliedCount = 0;

        $result = array_map(function ($layer) use ($softGlow, &$appliedCount) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Apply soft glow to CTA buttons
            if ($type === 'textbox' ||
                str_contains($name, 'cta') ||
                str_contains($name, 'button')) {
                $layer['properties'] = array_merge(
                    $layer['properties'] ?? [],
                    $softGlow
                );
                $appliedCount++;

                Log::channel('single')->debug('ElevationService: Applied soft glow to layer', [
                    'layer_name' => $layer['name'] ?? 'unknown',
                    'layer_type' => $type,
                ]);
            }

            return $layer;
        }, $layers);

        Log::channel('single')->info('ElevationService: Soft glow applied to CTA layers', [
            'total_layers' => count($layers),
            'cta_layers_updated' => $appliedCount,
            'intensity' => $intensity,
        ]);

        return $result;
    }

    /**
     * Get ambient shadow style (subtle elevation with slight offset).
     *
     * @param int $intensity 1 = subtle, 2 = medium, 3 = strong
     */
    public function getAmbientShadow(int $intensity = 2): array
    {
        $index = max(0, min(2, $intensity - 1));
        $style = self::SHADOW_STYLES['ambient'];

        return [
            'shadowEnabled' => true,
            'shadowColor' => '#000000',
            'shadowBlur' => $style['blur'][$index],
            'shadowOffsetX' => $style['offsetX'],
            'shadowOffsetY' => is_array($style['offsetY']) ? $style['offsetY'][$index] : $style['offsetY'],
            'shadowOpacity' => $style['opacity'][$index],
        ];
    }
}
