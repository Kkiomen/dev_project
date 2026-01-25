<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Template Variation Service.
 *
 * Generates multiple layout variations of the same design concept.
 * Uses different archetypes, color schemes, and font pairings.
 */
class TemplateVariationService
{
    /**
     * Color scheme types for variations.
     */
    public const COLOR_SCHEMES = [
        'complementary',
        'analogous',
        'triadic',
        'monochromatic',
    ];

    /**
     * Elevation levels for variation.
     */
    public const ELEVATION_LEVELS = [1, 2, 3, 4];

    public function __construct(
        protected CompositionArchetypeService $archetypeService,
        protected DesignTokensService $designTokensService,
        protected ColorHarmonyValidator $colorHarmonyValidator,
        protected ElevationService $elevationService
    ) {}

    /**
     * Generate multiple variations of a base design.
     *
     * @param array $baseLayers The original layers
     * @param array $designPlan The design plan with archetype, colors, etc.
     * @param int $count Number of variations to generate (2-5)
     * @return array Array of variation designs
     */
    public function generateVariations(array $baseLayers, array $designPlan, int $count = 3): array
    {
        $count = max(2, min(5, $count));
        $variations = [];
        $usedArchetypes = [$designPlan['composition_archetype'] ?? 'centered_minimal'];
        $usedColorSchemes = [];

        // Original design is variation #1
        $variations[] = [
            'id' => 1,
            'name' => 'Original',
            'layers' => $baseLayers,
            'archetype' => $usedArchetypes[0],
            'changes' => [],
        ];

        // Generate additional variations
        for ($i = 2; $i <= $count; $i++) {
            $variation = $this->createVariation(
                $baseLayers,
                $designPlan,
                $usedArchetypes,
                $usedColorSchemes,
                $i
            );

            $variations[] = $variation;
            $usedArchetypes[] = $variation['archetype'];

            if (isset($variation['color_scheme'])) {
                $usedColorSchemes[] = $variation['color_scheme'];
            }
        }

        Log::channel('single')->info('Template variations generated', [
            'count' => count($variations),
            'archetypes' => array_column($variations, 'archetype'),
        ]);

        return $variations;
    }

    /**
     * Create a single variation.
     */
    public function createVariation(
        array $baseLayers,
        array $designPlan,
        array $excludeArchetypes,
        array $excludeColorSchemes,
        int $variationNumber
    ): array {
        $changes = [];
        $layers = $this->deepCopyLayers($baseLayers);

        // Get template dimensions
        $width = $designPlan['width'] ?? 1080;
        $height = $designPlan['height'] ?? 1080;

        // 1. Select a different archetype
        $newArchetype = $this->selectAlternativeArchetype($excludeArchetypes);
        $changes[] = "archetype:{$newArchetype}";

        // 2. Apply new archetype constraints to layers
        $layers = $this->applyArchetypeToLayers($layers, $newArchetype, $width, $height);

        // 3. Vary color scheme (every other variation)
        $colorScheme = null;
        if ($variationNumber % 2 === 0) {
            $colorScheme = $this->selectAlternativeColorScheme($excludeColorSchemes);
            $layers = $this->applyColorSchemeVariation($layers, $designPlan, $colorScheme);
            $changes[] = "color_scheme:{$colorScheme}";
        }

        // 4. Vary font pairing (for variation 3+)
        if ($variationNumber >= 3) {
            $industry = $designPlan['industry'] ?? 'default';
            $layers = $this->applyAlternativeFonts($layers, $industry);
            $changes[] = 'fonts:alternative';
        }

        // 5. Vary elevation level
        $elevationLevel = self::ELEVATION_LEVELS[($variationNumber - 1) % count(self::ELEVATION_LEVELS)];
        $layers = $this->applyElevationLevel($layers, $elevationLevel);
        $changes[] = "elevation:{$elevationLevel}";

        return [
            'id' => $variationNumber,
            'name' => "Variation {$variationNumber}",
            'layers' => $layers,
            'archetype' => $newArchetype,
            'color_scheme' => $colorScheme,
            'changes' => $changes,
        ];
    }

    /**
     * Select an archetype not in the excluded list.
     */
    protected function selectAlternativeArchetype(array $excludeArchetypes): string
    {
        $availableArchetypes = array_keys(CompositionArchetypeService::ARCHETYPES);

        $available = array_diff($availableArchetypes, $excludeArchetypes);

        if (empty($available)) {
            // If all used, reset and pick first
            return $availableArchetypes[0];
        }

        // Randomly pick from available
        return $available[array_rand($available)];
    }

    /**
     * Select a color scheme not in the excluded list.
     */
    protected function selectAlternativeColorScheme(array $excludeSchemes): string
    {
        $available = array_diff(self::COLOR_SCHEMES, $excludeSchemes);

        if (empty($available)) {
            return self::COLOR_SCHEMES[0];
        }

        return $available[array_rand($available)];
    }

    /**
     * Apply archetype constraints to layers.
     */
    protected function applyArchetypeToLayers(array $layers, string $archetype, int $width, int $height): array
    {
        $archetypeConfig = CompositionArchetypeService::ARCHETYPES[$archetype] ?? null;

        if (!$archetypeConfig) {
            return $layers;
        }

        $textZone = $archetypeConfig['text_zone'];
        $photoZone = $archetypeConfig['photo_zone'];
        $headlineAlign = $archetypeConfig['headline_align'] ?? 'left';

        // Scale zones if not 1080x1080
        $scaleX = $width / 1080;
        $scaleY = $height / 1080;

        $textZone = [
            'x' => (int) ($textZone['x'] * $scaleX),
            'y' => (int) ($textZone['y'] * $scaleY),
            'width' => (int) ($textZone['width'] * $scaleX),
            'height' => (int) ($textZone['height'] * $scaleY),
        ];

        $photoZone = [
            'x' => (int) ($photoZone['x'] * $scaleX),
            'y' => (int) ($photoZone['y'] * $scaleY),
            'width' => (int) ($photoZone['width'] * $scaleX),
            'height' => (int) ($photoZone['height'] * $scaleY),
        ];

        // Reposition layers
        $textLayerIndex = 0;
        $adjustedLayers = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Handle photo/image layers
            if ($type === 'image' || str_contains($name, 'photo')) {
                $layer['x'] = $photoZone['x'];
                $layer['y'] = $photoZone['y'];
                $layer['width'] = $photoZone['width'];
                $layer['height'] = $photoZone['height'];
            }
            // Handle text layers
            elseif (in_array($type, ['text', 'textbox'])) {
                $verticalOffset = $textLayerIndex * 80; // Stack text layers

                $layer['x'] = $textZone['x'];
                $layer['y'] = min($textZone['y'] + $verticalOffset, $textZone['y'] + $textZone['height'] - 50);
                $layer['width'] = min($layer['width'] ?? 400, $textZone['width']);

                // Update text alignment
                if (isset($layer['properties'])) {
                    $layer['properties']['align'] = $headlineAlign;
                }

                $textLayerIndex++;
            }
            // Handle overlays
            elseif (str_contains($name, 'overlay')) {
                $layer['x'] = $photoZone['x'];
                $layer['y'] = $photoZone['y'];
                $layer['width'] = $photoZone['width'];
                $layer['height'] = $photoZone['height'];

                // Update overlay opacity if archetype requires it
                if (isset($archetypeConfig['overlay_opacity']) && isset($layer['properties'])) {
                    $layer['properties']['opacity'] = $archetypeConfig['overlay_opacity'];
                }
            }

            $adjustedLayers[] = $layer;
        }

        return $adjustedLayers;
    }

    /**
     * Apply color scheme variation.
     */
    protected function applyColorSchemeVariation(array $layers, array $designPlan, string $scheme): array
    {
        $primaryColor = $designPlan['primary_color'] ?? '#D4AF37';

        // Generate new accent color based on scheme
        $newAccent = $this->colorHarmonyValidator->suggestAccentColor($primaryColor, $scheme);

        foreach ($layers as &$layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Update CTA/button colors
            if ($type === 'textbox' || str_contains($name, 'cta') || str_contains($name, 'button')) {
                if (isset($layer['properties']['fill'])) {
                    $layer['properties']['fill'] = $newAccent;
                }
            }

            // Update accent elements
            if (str_contains($name, 'accent') || str_contains($name, 'decoration')) {
                if (isset($layer['properties']['fill'])) {
                    $layer['properties']['fill'] = $newAccent;
                }
            }
        }

        return $layers;
    }

    /**
     * Apply alternative font pairing.
     */
    protected function applyAlternativeFonts(array $layers, string $industry): array
    {
        // Get alternative font pairs based on industry
        $altFonts = $this->getAlternativeFonts($industry);

        foreach ($layers as &$layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            if (!in_array($type, ['text', 'textbox'])) {
                continue;
            }

            if (!isset($layer['properties'])) {
                continue;
            }

            // Determine if headline or body
            $isHeadline = str_contains($name, 'headline') ||
                          str_contains($name, 'title') ||
                          ($layer['properties']['fontSize'] ?? 16) >= 31;

            if ($isHeadline) {
                $layer['properties']['fontFamily'] = $altFonts['heading'];
                $layer['properties']['fontWeight'] = $altFonts['heading_weight'];
            } else {
                $layer['properties']['fontFamily'] = $altFonts['body'];
                $layer['properties']['fontWeight'] = $altFonts['body_weight'];
            }
        }

        return $layers;
    }

    /**
     * Get alternative font pairs for an industry.
     */
    protected function getAlternativeFonts(string $industry): array
    {
        // Define alternative pairs for each industry
        $alternatives = [
            'medical' => [
                'heading' => 'Roboto',
                'heading_weight' => '700',
                'body' => 'Lato',
                'body_weight' => '400',
            ],
            'beauty' => [
                'heading' => 'Cormorant Garamond',
                'heading_weight' => '500',
                'body' => 'Raleway',
                'body_weight' => '300',
            ],
            'gastro' => [
                'heading' => 'Merriweather',
                'heading_weight' => '700',
                'body' => 'Source Sans Pro',
                'body_weight' => '400',
            ],
            'fitness' => [
                'heading' => 'Oswald',
                'heading_weight' => '600',
                'body' => 'Roboto',
                'body_weight' => '400',
            ],
            'technology' => [
                'heading' => 'Space Grotesk',
                'heading_weight' => '600',
                'body' => 'IBM Plex Sans',
                'body_weight' => '400',
            ],
            'default' => [
                'heading' => 'Poppins',
                'heading_weight' => '600',
                'body' => 'Inter',
                'body_weight' => '400',
            ],
        ];

        return $alternatives[$industry] ?? $alternatives['default'];
    }

    /**
     * Apply specific elevation level to layers.
     */
    protected function applyElevationLevel(array $layers, int $level): array
    {
        $shadowConfig = $this->elevationService->getShadowProperties($level);

        foreach ($layers as &$layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Apply elevation to CTAs and cards
            if ($type === 'textbox' ||
                str_contains($name, 'cta') ||
                str_contains($name, 'button') ||
                str_contains($name, 'card')) {

                if (!isset($layer['properties'])) {
                    $layer['properties'] = [];
                }

                foreach ($shadowConfig as $key => $value) {
                    $layer['properties'][$key] = $value;
                }
            }
        }

        return $layers;
    }

    /**
     * Deep copy layers array to avoid reference issues.
     */
    protected function deepCopyLayers(array $layers): array
    {
        return json_decode(json_encode($layers), true);
    }

    /**
     * Get a summary of differences between two layer sets.
     */
    public function compareLayers(array $original, array $variation): array
    {
        $differences = [];

        $originalByName = [];
        foreach ($original as $layer) {
            $name = $layer['name'] ?? 'unnamed';
            $originalByName[$name] = $layer;
        }

        foreach ($variation as $layer) {
            $name = $layer['name'] ?? 'unnamed';

            if (!isset($originalByName[$name])) {
                $differences[] = ['type' => 'added', 'layer' => $name];
                continue;
            }

            $origLayer = $originalByName[$name];

            // Check position changes
            if (($layer['x'] ?? 0) !== ($origLayer['x'] ?? 0) ||
                ($layer['y'] ?? 0) !== ($origLayer['y'] ?? 0)) {
                $differences[] = [
                    'type' => 'position',
                    'layer' => $name,
                    'from' => ['x' => $origLayer['x'] ?? 0, 'y' => $origLayer['y'] ?? 0],
                    'to' => ['x' => $layer['x'] ?? 0, 'y' => $layer['y'] ?? 0],
                ];
            }

            // Check dimension changes
            if (($layer['width'] ?? 0) !== ($origLayer['width'] ?? 0) ||
                ($layer['height'] ?? 0) !== ($origLayer['height'] ?? 0)) {
                $differences[] = [
                    'type' => 'dimensions',
                    'layer' => $name,
                    'from' => ['w' => $origLayer['width'] ?? 0, 'h' => $origLayer['height'] ?? 0],
                    'to' => ['w' => $layer['width'] ?? 0, 'h' => $layer['height'] ?? 0],
                ];
            }

            // Check property changes
            $origProps = $origLayer['properties'] ?? [];
            $newProps = $layer['properties'] ?? [];

            foreach (['fontFamily', 'fill', 'align'] as $prop) {
                if (($newProps[$prop] ?? null) !== ($origProps[$prop] ?? null)) {
                    $differences[] = [
                        'type' => 'property',
                        'layer' => $name,
                        'property' => $prop,
                        'from' => $origProps[$prop] ?? null,
                        'to' => $newProps[$prop] ?? null,
                    ];
                }
            }
        }

        return $differences;
    }
}
