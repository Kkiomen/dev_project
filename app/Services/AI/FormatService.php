<?php

namespace App\Services\AI;

/**
 * Format Service for Multi-format Support.
 *
 * Provides support for multiple social media formats (1:1, 4:5, 3:4, 9:16, 16:9)
 * with automatic composition scaling.
 */
class FormatService
{
    /**
     * Supported formats with dimensions.
     */
    public const FORMATS = [
        'square' => [
            'width' => 1080,
            'height' => 1080,
            'ratio' => '1:1',
            'platforms' => ['instagram_feed', 'facebook'],
            'description' => 'Standard square format',
        ],
        'portrait' => [
            'width' => 1080,
            'height' => 1350,
            'ratio' => '4:5',
            'platforms' => ['instagram_feed_optimal'],
            'description' => 'Instagram optimal portrait',
        ],
        'tall' => [
            'width' => 1080,
            'height' => 1440,
            'ratio' => '3:4',
            'platforms' => ['instagram_grid_friendly'],
            'description' => 'Tall portrait format',
        ],
        'story' => [
            'width' => 1080,
            'height' => 1920,
            'ratio' => '9:16',
            'platforms' => ['instagram_stories', 'reels', 'tiktok', 'snapchat'],
            'safe_zone' => ['top' => 250, 'bottom' => 250],
            'description' => 'Full vertical for Stories/Reels',
        ],
        'landscape' => [
            'width' => 1920,
            'height' => 1080,
            'ratio' => '16:9',
            'platforms' => ['youtube_thumbnail', 'linkedin', 'twitter'],
            'description' => 'Widescreen landscape',
        ],
        'linkedin' => [
            'width' => 1200,
            'height' => 627,
            'ratio' => '1.91:1',
            'platforms' => ['linkedin_post', 'linkedin_article'],
            'description' => 'LinkedIn optimal format',
        ],
        'pinterest' => [
            'width' => 1000,
            'height' => 1500,
            'ratio' => '2:3',
            'platforms' => ['pinterest'],
            'description' => 'Pinterest pin format',
        ],
    ];

    /**
     * Base format for calculations (all archetypes are designed for this).
     */
    public const BASE_FORMAT = [
        'width' => 1080,
        'height' => 1080,
    ];

    /**
     * Get format definition by name.
     */
    public function getFormat(string $name): array
    {
        return self::FORMATS[$name] ?? self::FORMATS['square'];
    }

    /**
     * Get all available format names.
     */
    public function getFormatNames(): array
    {
        return array_keys(self::FORMATS);
    }

    /**
     * Get formats suitable for a platform.
     */
    public function getFormatsForPlatform(string $platform): array
    {
        $suitable = [];

        foreach (self::FORMATS as $name => $format) {
            if (in_array($platform, $format['platforms'] ?? [])) {
                $suitable[$name] = $format;
            }
        }

        return $suitable;
    }

    /**
     * Get the best format for a platform.
     */
    public function getBestFormatForPlatform(string $platform): string
    {
        $formats = $this->getFormatsForPlatform($platform);

        if (empty($formats)) {
            return 'square'; // Default fallback
        }

        // Return first matching format
        return array_key_first($formats);
    }

    /**
     * Scale archetype zones for a different format.
     */
    public function scaleArchetypeForFormat(array $archetype, string $targetFormat): array
    {
        $format = $this->getFormat($targetFormat);
        $baseWidth = self::BASE_FORMAT['width'];
        $baseHeight = self::BASE_FORMAT['height'];

        $scaleX = $format['width'] / $baseWidth;
        $scaleY = $format['height'] / $baseHeight;

        // For taller formats (like Stories), use a different scaling strategy
        $isTaller = $format['height'] > $format['width'] * 1.3;

        // Scale text zone
        if (isset($archetype['text_zone'])) {
            $archetype['text_zone'] = $this->scaleZone(
                $archetype['text_zone'],
                $scaleX,
                $scaleY,
                $isTaller,
                $format
            );
        }

        // Scale photo zone
        if (isset($archetype['photo_zone'])) {
            $archetype['photo_zone'] = $this->scaleZone(
                $archetype['photo_zone'],
                $scaleX,
                $scaleY,
                $isTaller,
                $format
            );
        }

        return $archetype;
    }

    /**
     * Scale a single zone for new format.
     */
    protected function scaleZone(array $zone, float $scaleX, float $scaleY, bool $isTaller, array $format): array
    {
        // For taller formats, expand height more than width
        if ($isTaller) {
            // Expand vertically, maintain horizontal proportions
            return [
                'x' => (int) ($zone['x'] * $scaleX),
                'y' => (int) ($zone['y'] * $scaleY),
                'width' => (int) ($zone['width'] * $scaleX),
                'height' => (int) ($zone['height'] * $scaleY * 1.2), // Expand height
            ];
        }

        // Standard proportional scaling
        return [
            'x' => (int) ($zone['x'] * $scaleX),
            'y' => (int) ($zone['y'] * $scaleY),
            'width' => (int) ($zone['width'] * $scaleX),
            'height' => (int) ($zone['height'] * $scaleY),
        ];
    }

    /**
     * Get safe zone for format (for Stories/Reels with UI overlays).
     */
    public function getSafeZone(string $format): array
    {
        $formatDef = $this->getFormat($format);

        if (isset($formatDef['safe_zone'])) {
            return [
                'top' => $formatDef['safe_zone']['top'] ?? 0,
                'bottom' => $formatDef['safe_zone']['bottom'] ?? 0,
                'left' => $formatDef['safe_zone']['left'] ?? 0,
                'right' => $formatDef['safe_zone']['right'] ?? 0,
            ];
        }

        return ['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0];
    }

    /**
     * Adjust layer positions for format safe zone.
     */
    public function adjustLayersForSafeZone(array $layers, string $format): array
    {
        $safeZone = $this->getSafeZone($format);
        $formatDef = $this->getFormat($format);

        if (array_sum($safeZone) === 0) {
            return $layers; // No safe zone adjustment needed
        }

        $adjustedLayers = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Skip full-bleed elements (backgrounds, photos)
            if (str_contains($name, 'background') || str_contains($name, 'photo')) {
                $adjustedLayers[] = $layer;
                continue;
            }

            // Adjust text/button layers to stay in safe zone
            if (in_array($type, ['text', 'textbox'])) {
                $y = $layer['y'] ?? 0;

                // Ensure not in top safe zone
                if ($y < $safeZone['top']) {
                    $layer['y'] = $safeZone['top'] + 20;
                }

                // Ensure not in bottom safe zone
                $layerHeight = $layer['height'] ?? 50;
                $maxY = $formatDef['height'] - $safeZone['bottom'] - $layerHeight;
                if ($y > $maxY) {
                    $layer['y'] = $maxY - 20;
                }
            }

            $adjustedLayers[] = $layer;
        }

        return $adjustedLayers;
    }

    /**
     * Scale all layers for a target format.
     */
    public function scaleLayersForFormat(array $layers, string $targetFormat): array
    {
        $format = $this->getFormat($targetFormat);
        $baseWidth = self::BASE_FORMAT['width'];
        $baseHeight = self::BASE_FORMAT['height'];

        $scaleX = $format['width'] / $baseWidth;
        $scaleY = $format['height'] / $baseHeight;

        $scaledLayers = [];

        foreach ($layers as $layer) {
            $scaledLayer = $layer;

            // Scale position
            $scaledLayer['x'] = (int) (($layer['x'] ?? 0) * $scaleX);
            $scaledLayer['y'] = (int) (($layer['y'] ?? 0) * $scaleY);

            // Scale size
            $scaledLayer['width'] = (int) (($layer['width'] ?? 100) * $scaleX);
            $scaledLayer['height'] = (int) (($layer['height'] ?? 100) * $scaleY);

            // Scale font size if text layer
            if (in_array($layer['type'] ?? '', ['text', 'textbox'])) {
                $fontSize = $layer['properties']['fontSize'] ?? 16;
                // Scale font proportionally but with a minimum
                $scaledLayer['properties']['fontSize'] = max(12, (int) ($fontSize * min($scaleX, $scaleY)));
            }

            $scaledLayers[] = $scaledLayer;
        }

        return $scaledLayers;
    }

    /**
     * Get recommended format for industry.
     */
    public function getRecommendedFormat(string $platform, ?string $industry = null): string
    {
        // Instagram feed for most industries prefers 4:5
        if ($platform === 'instagram' && in_array($industry, ['beauty', 'fashion', 'fitness'])) {
            return 'portrait';
        }

        return $this->getBestFormatForPlatform($platform);
    }
}
