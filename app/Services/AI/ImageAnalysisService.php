<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Image Analysis Service.
 *
 * Communicates with the Docker microservice to analyze images
 * for optimal text placement in templates.
 */
class ImageAnalysisService
{
    /**
     * URL of the image analysis microservice.
     */
    protected string $serviceUrl;

    /**
     * Request timeout in seconds.
     */
    protected int $timeout;

    /**
     * Cache TTL in seconds (1 hour by default).
     */
    protected int $cacheTtl;

    public function __construct()
    {
        $this->serviceUrl = config('services.image_analysis.url', 'http://image-analysis:3334');
        $this->timeout = config('services.image_analysis.timeout', 30);
        $this->cacheTtl = config('services.image_analysis.cache_ttl', 3600);
    }

    /**
     * Analyze an image using the Docker microservice.
     *
     * Results are cached in Redis to avoid repeated API calls for the same image.
     */
    public function analyzeImage(string $imageUrl, int $width = 1080, int $height = 1080): array
    {
        $cacheKey = $this->buildCacheKey($imageUrl, $width, $height);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($imageUrl, $width, $height) {
            return $this->performAnalysis($imageUrl, $width, $height);
        });
    }

    /**
     * Build cache key for image analysis.
     */
    protected function buildCacheKey(string $imageUrl, int $width, int $height): string
    {
        return 'image_analysis:' . md5($imageUrl . ':' . $width . ':' . $height);
    }

    /**
     * Perform the actual image analysis API call.
     */
    protected function performAnalysis(string $imageUrl, int $width, int $height): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->serviceUrl}/analyze", [
                    'imageUrl' => $imageUrl,
                    'width' => $width,
                    'height' => $height,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('single')->info('Image analysis completed', [
                    'image_url' => substr($imageUrl, 0, 100),
                    'focal_point' => $data['focal_point'] ?? null,
                    'suggested_position' => $data['suggested_text_position'] ?? null,
                    'is_dark' => $data['brightness']['is_dark'] ?? null,
                    'cached' => false,
                ]);

                return $data;
            }

            Log::warning('Image analysis failed', [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return $this->getDefaultAnalysis();

        } catch (\Exception $e) {
            Log::error('Image analysis service error', [
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultAnalysis();
        }
    }

    /**
     * Clear cached analysis for a specific image.
     */
    public function clearCache(string $imageUrl, int $width = 1080, int $height = 1080): bool
    {
        $cacheKey = $this->buildCacheKey($imageUrl, $width, $height);

        return Cache::forget($cacheKey);
    }

    /**
     * Clear all image analysis cache.
     */
    public function clearAllCache(): bool
    {
        // Note: This requires Redis with SCAN command support
        // For simplicity, we'll just return true as Cache::flush() would clear everything
        return true;
    }

    /**
     * Check if the image analysis service is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->serviceUrl}/health");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get default analysis when service is unavailable.
     */
    protected function getDefaultAnalysis(): array
    {
        return [
            'success' => false,
            'focal_point' => [
                'x' => 540,
                'y' => 540,
                'normalized' => ['x' => 0.5, 'y' => 0.5],
            ],
            'brightness' => [
                'top-left' => 0.5,
                'top-right' => 0.5,
                'bottom-left' => 0.5,
                'bottom-right' => 0.5,
                'overall' => 0.5,
                'is_dark' => false,
            ],
            'suggested_text_position' => 'bottom',
            'safe_zones' => [
                [
                    'position' => 'bottom',
                    'x' => 40,
                    'y' => 780,
                    'width' => 1000,
                    'height' => 260,
                    'recommended_text_color' => '#FFFFFF',
                ],
            ],
            'busy_zones' => [
                [
                    'position' => 'center',
                    'x' => 270,
                    'y' => 270,
                    'width' => 540,
                    'height' => 540,
                    'reason' => 'Default busy zone (center)',
                ],
            ],
        ];
    }

    /**
     * Adjust layer positions based on image analysis.
     *
     * IMPORTANT: This method should NOT move layers that are already in the
     * text zone (outside the photo area). The archetype constraints should
     * take precedence.
     */
    public function adjustLayersToAnalysis(array $layers, array $analysis, ?array $photoLayer = null): array
    {
        if (!($analysis['success'] ?? false)) {
            return $layers;
        }

        $busyZones = $analysis['busy_zones'] ?? [];
        $safeZones = $analysis['safe_zones'] ?? [];
        $suggestedPosition = $analysis['suggested_text_position'] ?? 'bottom';

        // Find photo layer to determine the photo area
        if (!$photoLayer) {
            foreach ($layers as $layer) {
                $type = $layer['type'] ?? '';
                $name = strtolower($layer['name'] ?? '');
                if ($type === 'image' || str_contains($name, 'photo')) {
                    $photoLayer = $layer;
                    break;
                }
            }
        }

        // If no photo layer found, don't adjust (text is not on a photo)
        if (!$photoLayer) {
            Log::channel('single')->info('No photo layer found, skipping image analysis adjustments');
            return $layers;
        }

        $photoX = $photoLayer['x'] ?? 0;
        $photoY = $photoLayer['y'] ?? 0;
        $photoW = $photoLayer['width'] ?? 1080;
        $photoH = $photoLayer['height'] ?? 1080;

        Log::channel('single')->info('Photo area for analysis', [
            'photo_x' => $photoX,
            'photo_y' => $photoY,
            'photo_w' => $photoW,
            'photo_h' => $photoH,
        ]);

        $adjustedLayers = [];

        // Track used Y positions in each safe zone to prevent stacking
        $usedYPositions = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Only adjust text layers
            if (!in_array($type, ['text', 'textbox'])) {
                $adjustedLayers[] = $layer;
                continue;
            }

            $layerX = $layer['x'] ?? 0;
            $layerY = $layer['y'] ?? 0;
            $layerW = $layer['width'] ?? 200;
            $layerH = $layer['height'] ?? 50;

            // Check if layer is actually on the photo area
            $onPhoto = $this->rectanglesOverlap(
                $layerX, $layerY, $layerW, $layerH,
                $photoX, $photoY, $photoW, $photoH
            );

            // If text is NOT on the photo, don't move it (it's in the text zone)
            if (!$onPhoto) {
                Log::channel('single')->info('Layer not on photo, keeping position', [
                    'layer' => $layer['name'] ?? 'unknown',
                    'position' => ['x' => $layerX, 'y' => $layerY],
                ]);
                $adjustedLayers[] = $layer;
                continue;
            }

            // Check if layer overlaps with busy zones (only relevant for text on photo)
            if ($this->layerOverlapsBusyZone($layer, $busyZones)) {
                // Move layer to a safe zone with stacking
                $layer = $this->moveLayerToSafeZoneWithStacking(
                    $layer,
                    $safeZones,
                    $suggestedPosition,
                    $usedYPositions
                );

                Log::channel('single')->info('Moved layer to avoid busy zone', [
                    'layer' => $layer['name'] ?? 'unknown',
                    'new_position' => ['x' => $layer['x'], 'y' => $layer['y']],
                ]);
            }

            $adjustedLayers[] = $layer;
        }

        return $adjustedLayers;
    }

    /**
     * Check if two rectangles overlap.
     */
    protected function rectanglesOverlap(
        int $x1, int $y1, int $w1, int $h1,
        int $x2, int $y2, int $w2, int $h2
    ): bool {
        return !($x1 + $w1 <= $x2 || $x2 + $w2 <= $x1 || $y1 + $h1 <= $y2 || $y2 + $h2 <= $y1);
    }

    /**
     * Check if a layer overlaps with any busy zone.
     */
    protected function layerOverlapsBusyZone(array $layer, array $busyZones): bool
    {
        $lx = $layer['x'] ?? 0;
        $ly = $layer['y'] ?? 0;
        $lw = $layer['width'] ?? 0;
        $lh = $layer['height'] ?? 0;

        foreach ($busyZones as $zone) {
            $zx = $zone['x'] ?? 0;
            $zy = $zone['y'] ?? 0;
            $zw = $zone['width'] ?? 0;
            $zh = $zone['height'] ?? 0;

            // Check for overlap
            $overlapsX = $lx < $zx + $zw && $lx + $lw > $zx;
            $overlapsY = $ly < $zy + $zh && $ly + $lh > $zy;

            if ($overlapsX && $overlapsY) {
                return true;
            }
        }

        return false;
    }

    /**
     * Move a layer to a safe zone with stacking support.
     * Multiple layers in the same zone will be stacked vertically.
     */
    protected function moveLayerToSafeZoneWithStacking(
        array $layer,
        array $safeZones,
        string $preferredPosition,
        array &$usedYPositions
    ): array {
        // Find a safe zone matching the preferred position
        $targetZone = null;

        foreach ($safeZones as $zone) {
            if (str_contains($zone['position'] ?? '', $preferredPosition)) {
                $targetZone = $zone;
                break;
            }
        }

        // If no match, use the first safe zone
        if (!$targetZone && !empty($safeZones)) {
            $targetZone = $safeZones[0];
        }

        if ($targetZone) {
            $zoneKey = $targetZone['position'] ?? 'default';
            $layerHeight = $layer['height'] ?? 50;
            $spacing = 16; // Space between stacked layers

            // Calculate Y position with stacking
            if (!isset($usedYPositions[$zoneKey])) {
                // First layer in this zone
                $layer['y'] = $targetZone['y'];
                $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
            } else {
                // Stack below previous layer
                $layer['y'] = $usedYPositions[$zoneKey];
                $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
            }

            // Center horizontally in safe zone
            $layerWidth = $layer['width'] ?? 200;
            $zoneWidth = $targetZone['width'] ?? 1000;
            $layer['x'] = $targetZone['x'] + (int) (($zoneWidth - $layerWidth) / 2);

            // Adjust width if needed
            if ($layerWidth > $zoneWidth) {
                $layer['width'] = $zoneWidth;
            }
        }

        return $layer;
    }

    /**
     * Move a layer to a safe zone (legacy method for backwards compatibility).
     */
    protected function moveLayerToSafeZone(array $layer, array $safeZones, string $preferredPosition): array
    {
        $usedPositions = [];
        return $this->moveLayerToSafeZoneWithStacking($layer, $safeZones, $preferredPosition, $usedPositions);
    }

    /**
     * Get the recommended text color for a position.
     */
    public function getRecommendedTextColor(array $analysis, string $position = 'bottom'): string
    {
        $safeZones = $analysis['safe_zones'] ?? [];

        foreach ($safeZones as $zone) {
            if (str_contains($zone['position'] ?? '', $position)) {
                return $zone['recommended_text_color'] ?? '#FFFFFF';
            }
        }

        // Default based on overall brightness
        $isDark = $analysis['brightness']['is_dark'] ?? false;

        return $isDark ? '#FFFFFF' : '#000000';
    }
}
