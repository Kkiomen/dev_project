<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Text Overlay Service.
 *
 * Detects when text is placed on images/photos and adds
 * semi-transparent overlay backgrounds for better readability.
 */
class TextOverlayService
{
    /**
     * Minimum contrast improvement threshold.
     */
    protected float $minOverlayOpacity = 0.6;

    /**
     * Gradient presets for premium Instagram-style overlays.
     * Modern designs use gradient fades instead of solid overlays.
     * IMPORTANT: Coverage is reduced to ~20% for cleaner look that doesn't wash out photos.
     */
    public const GRADIENT_PRESETS = [
        'bottom_fade' => [
            'angle' => 0,               // Gradient goes from bottom to top
            'start_opacity' => 0.85,    // Strong at bottom where text sits
            'end_opacity' => 0,         // Fade to transparent at top
            'coverage' => 0.35,         // Cover bottom 35% - sharp falloff
        ],
        'top_fade' => [
            'angle' => 180,             // Gradient goes from top to bottom
            'start_opacity' => 0.8,
            'end_opacity' => 0,
            'coverage' => 0.3,          // Cover top 30% - sharp falloff
        ],
        'side_fade_left' => [
            'angle' => 90,              // Gradient goes from left to right
            'start_opacity' => 0.8,
            'end_opacity' => 0,
            'coverage' => 0.2,          // Cover left 20% (216px on 1080) - sharp falloff
        ],
        'side_fade_right' => [
            'angle' => 270,             // Gradient goes from right to left
            'start_opacity' => 0.8,
            'end_opacity' => 0,
            'coverage' => 0.2,          // Cover right 20% - sharp falloff
        ],
        'vignette' => [
            'type' => 'radial',
            'center_opacity' => 0,
            'edge_opacity' => 0.6,
        ],
    ];

    /**
     * Analyze layers and add overlays where text is on photos.
     */
    public function addTextOverlays(array $layers, int $templateWidth, int $templateHeight): array
    {
        $photoLayers = $this->findPhotoLayers($layers);
        $textLayers = $this->findTextLayers($layers);

        if (empty($photoLayers) || empty($textLayers)) {
            return $layers;
        }

        $overlaysToAdd = [];

        foreach ($textLayers as $textIndex => $textLayer) {
            $textName = strtolower($textLayer['name'] ?? '');

            // Skip if text already has its own background (like textbox with fill)
            if ($this->hasOwnBackground($textLayer)) {
                continue;
            }

            // Check if text overlaps with any photo
            foreach ($photoLayers as $photoLayer) {
                if ($this->layersOverlap($textLayer, $photoLayer)) {
                    // Check if there's already an overlay for this text
                    if (!$this->hasExistingOverlay($textLayer, $layers)) {
                        $overlay = $this->createOverlayForText($textLayer, $templateWidth);
                        if ($overlay) {
                            $overlaysToAdd[] = [
                                'overlay' => $overlay,
                                'forText' => $textLayer['name'] ?? 'unknown',
                            ];
                        }
                    }
                    break;
                }
            }
        }

        // Insert overlays before their corresponding text layers
        if (!empty($overlaysToAdd)) {
            $layers = $this->insertOverlays($layers, $overlaysToAdd);

            Log::channel('single')->info('Text overlays added', [
                'count' => count($overlaysToAdd),
                'for_texts' => array_column($overlaysToAdd, 'forText'),
            ]);
        }

        return $layers;
    }

    /**
     * Find all photo/image layers.
     */
    protected function findPhotoLayers(array $layers): array
    {
        return array_filter($layers, function ($layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            return $type === 'image' ||
                   str_contains($name, 'photo') ||
                   str_contains($name, 'image');
        });
    }

    /**
     * Find all text layers.
     */
    protected function findTextLayers(array $layers): array
    {
        return array_filter($layers, function ($layer) {
            $type = $layer['type'] ?? '';
            return in_array($type, ['text', 'textbox']);
        });
    }

    /**
     * Check if a text layer has its own background (e.g., textbox with fill).
     */
    protected function hasOwnBackground(array $layer): bool
    {
        $type = $layer['type'] ?? '';

        // Textbox with fill color has its own background
        if ($type === 'textbox') {
            $fill = $layer['properties']['fill'] ?? null;
            if ($fill && $fill !== 'transparent' && $fill !== 'none') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two layers overlap.
     */
    protected function layersOverlap(array $layer1, array $layer2): bool
    {
        $x1 = $layer1['x'] ?? 0;
        $y1 = $layer1['y'] ?? 0;
        $w1 = $layer1['width'] ?? 0;
        $h1 = $layer1['height'] ?? 0;

        $x2 = $layer2['x'] ?? 0;
        $y2 = $layer2['y'] ?? 0;
        $w2 = $layer2['width'] ?? 0;
        $h2 = $layer2['height'] ?? 0;

        // Check for overlap
        return $x1 < $x2 + $w2 &&
               $x1 + $w1 > $x2 &&
               $y1 < $y2 + $h2 &&
               $y1 + $h1 > $y2;
    }

    /**
     * Check if there's already an overlay for this text.
     */
    protected function hasExistingOverlay(array $textLayer, array $layers): bool
    {
        $textY = $textLayer['y'] ?? 0;
        $textName = strtolower($textLayer['name'] ?? '');

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');
            $layerY = $layer['y'] ?? 0;

            // Check if there's a rectangle near this text that could be an overlay
            if ($type === 'rectangle') {
                $opacity = $layer['properties']['opacity'] ?? 1;

                // Semi-transparent rectangle near the text
                if ($opacity < 1 && abs($layerY - $textY) < 100) {
                    return true;
                }

                // Named as overlay
                if (str_contains($name, 'overlay') || str_contains($name, 'gradient')) {
                    if (abs($layerY - $textY) < 200) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Create a semi-transparent overlay for text.
     */
    protected function createOverlayForText(array $textLayer, int $templateWidth): ?array
    {
        $textX = $textLayer['x'] ?? 0;
        $textY = $textLayer['y'] ?? 0;
        $textWidth = $textLayer['width'] ?? 200;
        $textHeight = $textLayer['height'] ?? 50;
        $textName = $textLayer['name'] ?? 'text';

        // Add padding around text
        $padding = 24;

        // Determine overlay style based on text position
        $isAtTop = $textY < 200;
        $isAtBottom = $textY > 700;

        // Create overlay
        $overlay = [
            'name' => "overlay_for_{$textName}",
            'type' => 'rectangle',
            'x' => 0,
            'y' => max(0, $textY - $padding),
            'width' => $templateWidth,
            'height' => $textHeight + $padding * 2,
            'properties' => [
                'fill' => '#000000',
                'opacity' => $this->minOverlayOpacity,
                'cornerRadius' => 0,
            ],
        ];

        // For bottom text, extend overlay to bottom edge
        if ($isAtBottom) {
            $overlay['height'] = 1080 - $overlay['y'];
        }

        // For top text, extend from top
        if ($isAtTop) {
            $overlay['y'] = 0;
            $overlay['height'] = $textY + $textHeight + $padding;
        }

        return $overlay;
    }

    /**
     * Insert overlays into layer array (before their text layers).
     */
    protected function insertOverlays(array $layers, array $overlaysToAdd): array
    {
        $result = [];
        $overlayMap = [];

        // Create map of text name -> overlay
        foreach ($overlaysToAdd as $item) {
            $overlayMap[$item['forText']] = $item['overlay'];
        }

        foreach ($layers as $layer) {
            $name = $layer['name'] ?? '';

            // If this text has an overlay, insert it first
            if (isset($overlayMap[$name])) {
                $result[] = $overlayMap[$name];
            }

            $result[] = $layer;
        }

        return $result;
    }

    /**
     * Create a gradient overlay for text readability.
     */
    public function createGradientOverlay(string $position, int $templateWidth, int $templateHeight): array
    {
        $height = (int) ($templateHeight * 0.4);

        if ($position === 'top') {
            return [
                'name' => 'gradient_overlay_top',
                'type' => 'rectangle',
                'x' => 0,
                'y' => 0,
                'width' => $templateWidth,
                'height' => $height,
                'properties' => [
                    'fillType' => 'gradient',
                    'gradientStartColor' => 'rgba(0,0,0,0.7)',
                    'gradientEndColor' => 'rgba(0,0,0,0)',
                    'gradientAngle' => 180,
                ],
            ];
        }

        // Bottom gradient
        return [
            'name' => 'gradient_overlay_bottom',
            'type' => 'rectangle',
            'x' => 0,
            'y' => $templateHeight - $height,
            'width' => $templateWidth,
            'height' => $height,
            'properties' => [
                'fillType' => 'gradient',
                'gradientStartColor' => 'rgba(0,0,0,0)',
                'gradientEndColor' => 'rgba(0,0,0,0.8)',
                'gradientAngle' => 180,
            ],
        ];
    }

    /**
     * Add automatic gradient overlay based on text position.
     * Detects where text is positioned and creates appropriate gradient.
     */
    public function addAutomaticGradientOverlay(array $layers, int $templateWidth, int $templateHeight): array
    {
        $photoLayers = $this->findPhotoLayers($layers);
        $textLayers = $this->findTextLayers($layers);

        Log::channel('single')->info('TextOverlay: Adding automatic gradient overlay', [
            'photo_layers_count' => count($photoLayers),
            'text_layers_count' => count($textLayers),
            'canvas' => ['width' => $templateWidth, 'height' => $templateHeight],
        ]);

        if (empty($photoLayers) || empty($textLayers)) {
            Log::channel('single')->debug('TextOverlay: Skipping gradient - no photo or text layers');
            return $layers;
        }

        // Determine text position (average Y position of all text)
        $textPositions = [];
        foreach ($textLayers as $textLayer) {
            $textPositions[] = [
                'y' => $textLayer['y'] ?? 0,
                'x' => $textLayer['x'] ?? 0,
                'height' => $textLayer['height'] ?? 50,
                'width' => $textLayer['width'] ?? 200,
            ];
        }

        $avgY = array_sum(array_column($textPositions, 'y')) / count($textPositions);
        $avgX = array_sum(array_column($textPositions, 'x')) / count($textPositions);

        Log::channel('single')->debug('TextOverlay: Text position analysis', [
            'average_x' => round($avgX, 2),
            'average_y' => round($avgY, 2),
        ]);

        // Determine which gradient preset to use based on text position
        $presetName = $this->detectBestGradientPreset($avgX, $avgY, $templateWidth, $templateHeight);
        $preset = self::GRADIENT_PRESETS[$presetName] ?? self::GRADIENT_PRESETS['bottom_fade'];

        // Find the topmost text layer position
        $topmostTextY = min(array_column($textPositions, 'y'));

        Log::channel('single')->info('TextOverlay: Selected gradient preset', [
            'preset_name' => $presetName,
            'angle' => $preset['angle'] ?? 'radial',
            'start_opacity' => $preset['start_opacity'] ?? $preset['center_opacity'] ?? 0,
            'topmost_text_y' => $topmostTextY,
        ]);

        // Create gradient overlay - dynamically positioned based on text
        $gradientOverlay = $this->createDynamicGradientOverlay(
            $presetName,
            $preset,
            $templateWidth,
            $templateHeight,
            $topmostTextY
        );

        Log::channel('single')->debug('TextOverlay: Created gradient overlay layer', [
            'layer_name' => $gradientOverlay['name'],
            'position' => ['x' => $gradientOverlay['x'], 'y' => $gradientOverlay['y']],
            'size' => ['width' => $gradientOverlay['width'], 'height' => $gradientOverlay['height']],
        ]);

        // Insert gradient after photo layer but before text layers
        return $this->insertGradientOverlay($layers, $gradientOverlay);
    }

    /**
     * Detect best gradient preset based on text position.
     */
    protected function detectBestGradientPreset(float $avgX, float $avgY, int $width, int $height): string
    {
        $centerX = $width / 2;
        $centerY = $height / 2;

        // Text in bottom third
        if ($avgY > $height * 0.6) {
            return 'bottom_fade';
        }

        // Text in top third
        if ($avgY < $height * 0.3) {
            return 'top_fade';
        }

        // Text on left side
        if ($avgX < $width * 0.35) {
            return 'side_fade_left';
        }

        // Text on right side
        if ($avgX > $width * 0.65) {
            return 'side_fade_right';
        }

        // Default to bottom fade for centered text
        return 'bottom_fade';
    }

    /**
     * Create dynamic gradient overlay positioned relative to text.
     * The gradient starts 20px above the topmost text layer for tight, aggressive fade.
     */
    protected function createDynamicGradientOverlay(
        string $presetName,
        array $preset,
        int $templateWidth,
        int $templateHeight,
        int $topmostTextY
    ): array {
        $startOpacity = $preset['start_opacity'] ?? 0.8;
        $endOpacity = $preset['end_opacity'] ?? 0;
        $angle = $preset['angle'] ?? 0;

        // Gradient buffer - start 20px above text for tight, aggressive fade
        $gradientBuffer = 20;

        // Calculate dimensions based on angle
        if ($angle === 0 || $angle === 180) {
            // Vertical gradient (bottom_fade or top_fade)
            if ($angle === 0) {
                // Bottom fade: gradient starts 50px above topmost text, goes to bottom
                $gradientStartY = max(0, $topmostTextY - $gradientBuffer);
                $gradientHeight = $templateHeight - $gradientStartY;
            } else {
                // Top fade: gradient from top to topmost text + buffer
                $gradientStartY = 0;
                $gradientHeight = min($templateHeight, $topmostTextY + $gradientBuffer);
            }

            Log::channel('single')->debug('TextOverlay: Dynamic gradient calculation (vertical)', [
                'topmost_text_y' => $topmostTextY,
                'gradient_start_y' => $gradientStartY,
                'gradient_height' => $gradientHeight,
                'buffer' => $gradientBuffer,
            ]);

            return [
                'name' => "gradient_overlay_{$presetName}",
                'type' => 'rectangle',
                'x' => 0,
                'y' => $gradientStartY,
                'width' => $templateWidth,
                'height' => $gradientHeight,
                'properties' => [
                    'fillType' => 'gradient',
                    'gradientStartColor' => "rgba(0,0,0,{$endOpacity})",
                    'gradientEndColor' => "rgba(0,0,0,{$startOpacity})",
                    'gradientAngle' => $angle,
                ],
            ];
        } else {
            // Horizontal gradient (side_fade)
            // Use text position to determine coverage
            $coverage = $preset['coverage'] ?? 0.5;
            $gradientWidth = (int)($templateWidth * $coverage);
            $x = $angle === 90 ? 0 : $templateWidth - $gradientWidth;

            return [
                'name' => "gradient_overlay_{$presetName}",
                'type' => 'rectangle',
                'x' => $x,
                'y' => 0,
                'width' => $gradientWidth,
                'height' => $templateHeight,
                'properties' => [
                    'fillType' => 'gradient',
                    'gradientStartColor' => "rgba(0,0,0,{$startOpacity})",
                    'gradientEndColor' => "rgba(0,0,0,{$endOpacity})",
                    'gradientAngle' => $angle,
                ],
            ];
        }
    }

    /**
     * Create gradient overlay layer from preset (legacy method).
     */
    protected function createGradientOverlayFromPreset(
        string $presetName,
        array $preset,
        int $templateWidth,
        int $templateHeight
    ): array {
        $coverage = $preset['coverage'] ?? 0.5;
        $startOpacity = $preset['start_opacity'] ?? 0.8;
        $endOpacity = $preset['end_opacity'] ?? 0;
        $angle = $preset['angle'] ?? 0;

        // Calculate dimensions based on angle
        if ($angle === 0 || $angle === 180) {
            // Vertical gradient
            $gradientHeight = (int)($templateHeight * $coverage);
            $y = $angle === 0 ? $templateHeight - $gradientHeight : 0;

            return [
                'name' => "gradient_overlay_{$presetName}",
                'type' => 'rectangle',
                'x' => 0,
                'y' => $y,
                'width' => $templateWidth,
                'height' => $gradientHeight,
                'properties' => [
                    'fillType' => 'gradient',
                    'gradientStartColor' => "rgba(0,0,0,{$startOpacity})",
                    'gradientEndColor' => "rgba(0,0,0,{$endOpacity})",
                    'gradientAngle' => $angle,
                ],
            ];
        } else {
            // Horizontal gradient
            $gradientWidth = (int)($templateWidth * $coverage);
            $x = $angle === 90 ? 0 : $templateWidth - $gradientWidth;

            return [
                'name' => "gradient_overlay_{$presetName}",
                'type' => 'rectangle',
                'x' => $x,
                'y' => 0,
                'width' => $gradientWidth,
                'height' => $templateHeight,
                'properties' => [
                    'fillType' => 'gradient',
                    'gradientStartColor' => "rgba(0,0,0,{$startOpacity})",
                    'gradientEndColor' => "rgba(0,0,0,{$endOpacity})",
                    'gradientAngle' => $angle,
                ],
            ];
        }
    }

    /**
     * Insert gradient overlay after photo layer but before text layers.
     */
    protected function insertGradientOverlay(array $layers, array $gradientOverlay): array
    {
        $result = [];
        $inserted = false;

        foreach ($layers as $layer) {
            $result[] = $layer;

            // Insert gradient after photo/image layer
            if (!$inserted) {
                $type = $layer['type'] ?? '';
                $name = strtolower($layer['name'] ?? '');

                if ($type === 'image' || str_contains($name, 'photo')) {
                    $result[] = $gradientOverlay;
                    $inserted = true;

                    Log::channel('single')->info('Gradient overlay inserted', [
                        'preset' => $gradientOverlay['name'],
                        'after_layer' => $layer['name'] ?? 'unknown',
                    ]);
                }
            }
        }

        // If no photo was found, add gradient at the beginning (after background)
        if (!$inserted && count($result) > 0) {
            array_splice($result, 1, 0, [$gradientOverlay]);
        }

        return $result;
    }
}
