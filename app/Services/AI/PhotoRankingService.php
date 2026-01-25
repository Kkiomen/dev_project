<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Photo Ranking Service.
 *
 * Scores and ranks photos based on composition quality
 * for optimal template design.
 */
class PhotoRankingService
{
    /**
     * Scoring weights for different criteria.
     */
    public const SCORING_WEIGHTS = [
        'focal_point' => 0.40,
        'aspect_ratio' => 0.20,
        'brightness_balance' => 0.20,
        'color_vibrancy' => 0.20,
    ];

    /**
     * Ideal focal point zones for each archetype.
     */
    public const ARCHETYPE_FOCAL_ZONES = [
        'centered_minimal' => ['x' => [0.35, 0.65], 'y' => [0.35, 0.65]],
        'thirds_left' => ['x' => [0.15, 0.45], 'y' => [0.25, 0.75]],
        'thirds_right' => ['x' => [0.55, 0.85], 'y' => [0.25, 0.75]],
        'bottom_heavy' => ['x' => [0.20, 0.80], 'y' => [0.40, 0.70]],
        'top_anchor' => ['x' => [0.20, 0.80], 'y' => [0.15, 0.50]],
    ];

    public function __construct(
        protected ImageAnalysisService $imageAnalysisService
    ) {}

    /**
     * Rank photos for a given archetype and target dimensions.
     *
     * @param array $photos Array of photo objects from stock service
     * @param string $archetype Composition archetype name
     * @param int $targetWidth Target template width
     * @param int $targetHeight Target template height
     * @return array Sorted photos with scores
     */
    public function rankPhotos(array $photos, string $archetype, int $targetWidth, int $targetHeight): array
    {
        if (empty($photos)) {
            return [];
        }

        $rankedPhotos = [];

        foreach ($photos as $photo) {
            $scores = $this->calculatePhotoScores($photo, $archetype, $targetWidth, $targetHeight);
            $totalScore = $this->calculateTotalScore($scores);

            $rankedPhotos[] = [
                'photo' => $photo,
                'scores' => $scores,
                'total_score' => $totalScore,
            ];
        }

        // Sort by total score descending
        usort($rankedPhotos, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

        Log::channel('single')->info('Photos ranked', [
            'count' => count($rankedPhotos),
            'archetype' => $archetype,
            'top_score' => $rankedPhotos[0]['total_score'] ?? 0,
            'bottom_score' => end($rankedPhotos)['total_score'] ?? 0,
        ]);

        return $rankedPhotos;
    }

    /**
     * Get the best photo from a list based on ranking.
     */
    public function getBestPhoto(array $photos, string $archetype, int $targetWidth, int $targetHeight): ?array
    {
        $ranked = $this->rankPhotos($photos, $archetype, $targetWidth, $targetHeight);

        if (empty($ranked)) {
            return null;
        }

        return $ranked[0]['photo'];
    }

    /**
     * Calculate all scores for a photo.
     */
    protected function calculatePhotoScores(array $photo, string $archetype, int $targetWidth, int $targetHeight): array
    {
        $photoWidth = $photo['width'] ?? 1920;
        $photoHeight = $photo['height'] ?? 1080;

        // Get image URL for analysis
        $imageUrl = $this->getPhotoUrl($photo, $targetWidth, $targetHeight);

        // Try to get image analysis (may be cached)
        $analysis = null;
        if ($imageUrl && $this->imageAnalysisService->isAvailable()) {
            $analysis = $this->imageAnalysisService->analyzeImage($imageUrl, $targetWidth, $targetHeight);
        }

        return [
            'focal_point' => $this->calculateFocalPointScore($analysis, $archetype),
            'aspect_ratio' => $this->calculateAspectScore($photoWidth, $photoHeight, $targetWidth, $targetHeight),
            'brightness_balance' => $this->calculateBrightnessScore($analysis),
            'color_vibrancy' => $this->calculateVibrancyScore($analysis),
        ];
    }

    /**
     * Calculate focal point compatibility score.
     */
    public function calculateFocalPointScore(?array $analysis, string $archetype): int
    {
        if (!$analysis || !($analysis['success'] ?? false)) {
            return 50; // Neutral score when no analysis available
        }

        $focalPoint = $analysis['focal_point']['normalized'] ?? null;
        if (!$focalPoint) {
            return 50;
        }

        $focalX = $focalPoint['x'] ?? 0.5;
        $focalY = $focalPoint['y'] ?? 0.5;

        $idealZone = self::ARCHETYPE_FOCAL_ZONES[$archetype] ?? self::ARCHETYPE_FOCAL_ZONES['centered_minimal'];

        // Check if focal point is within ideal zone
        $inZoneX = $focalX >= $idealZone['x'][0] && $focalX <= $idealZone['x'][1];
        $inZoneY = $focalY >= $idealZone['y'][0] && $focalY <= $idealZone['y'][1];

        if ($inZoneX && $inZoneY) {
            // Perfect match - calculate how centered within the zone
            $zoneCenterX = ($idealZone['x'][0] + $idealZone['x'][1]) / 2;
            $zoneCenterY = ($idealZone['y'][0] + $idealZone['y'][1]) / 2;

            $distanceFromCenter = sqrt(
                pow($focalX - $zoneCenterX, 2) +
                pow($focalY - $zoneCenterY, 2)
            );

            // Max distance is about 0.21 for half the zone width
            $normalizedDistance = min(1, $distanceFromCenter / 0.21);

            return (int) (100 - ($normalizedDistance * 20)); // 80-100 for in-zone
        }

        // Calculate distance from ideal zone
        $distanceX = max(0, $idealZone['x'][0] - $focalX, $focalX - $idealZone['x'][1]);
        $distanceY = max(0, $idealZone['y'][0] - $focalY, $focalY - $idealZone['y'][1]);
        $totalDistance = sqrt(pow($distanceX, 2) + pow($distanceY, 2));

        // Max distance is about 0.7 (corner to corner)
        $normalizedDistance = min(1, $totalDistance / 0.5);

        return (int) max(0, 80 - ($normalizedDistance * 80)); // 0-80 for out of zone
    }

    /**
     * Calculate aspect ratio fit score.
     */
    public function calculateAspectScore(int $photoWidth, int $photoHeight, int $targetWidth, int $targetHeight): int
    {
        if ($photoWidth === 0 || $photoHeight === 0) {
            return 50;
        }

        $photoRatio = $photoWidth / $photoHeight;
        $targetRatio = $targetWidth / $targetHeight;

        // Calculate how much cropping is needed
        if ($photoRatio >= $targetRatio) {
            // Photo is wider - crop sides
            $usedWidth = $photoHeight * $targetRatio;
            $cropRatio = $usedWidth / $photoWidth;
        } else {
            // Photo is taller - crop top/bottom
            $usedHeight = $photoWidth / $targetRatio;
            $cropRatio = $usedHeight / $photoHeight;
        }

        // Score based on how much of the image is used
        // 100% usage = 100 points, 50% usage = 50 points
        return (int) ($cropRatio * 100);
    }

    /**
     * Calculate brightness balance score.
     */
    protected function calculateBrightnessScore(?array $analysis): int
    {
        if (!$analysis || !isset($analysis['brightness'])) {
            return 50;
        }

        $brightness = $analysis['brightness'];

        $quadrants = [
            $brightness['top-left'] ?? 0.5,
            $brightness['top-right'] ?? 0.5,
            $brightness['bottom-left'] ?? 0.5,
            $brightness['bottom-right'] ?? 0.5,
        ];

        // Calculate variance - lower variance means more even lighting
        $mean = array_sum($quadrants) / count($quadrants);
        $variance = 0;
        foreach ($quadrants as $q) {
            $variance += pow($q - $mean, 2);
        }
        $variance /= count($quadrants);

        // Ideal variance is low (even lighting) - max variance is about 0.25
        $normalizedVariance = min(1, $variance / 0.1);

        // Also check that overall brightness is in good range (0.3-0.7)
        $overall = $brightness['overall'] ?? 0.5;
        $brightnessQuality = 1 - (2 * abs($overall - 0.5));

        $score = (1 - $normalizedVariance) * 0.6 + $brightnessQuality * 0.4;

        return (int) ($score * 100);
    }

    /**
     * Calculate color vibrancy score.
     */
    protected function calculateVibrancyScore(?array $analysis): int
    {
        if (!$analysis || !isset($analysis['colors'])) {
            return 50;
        }

        $colors = $analysis['colors'];

        // Check for accent candidates
        $accentCandidates = $colors['accent_candidates'] ?? [];
        if (empty($accentCandidates)) {
            return 30; // No strong colors
        }

        // More accent candidates is better (up to 3)
        $candidateScore = min(1, count($accentCandidates) / 3);

        // Check for good saturation in dominant colors
        $dominantColors = $colors['dominant'] ?? [];
        $saturationSum = 0;
        $count = 0;

        foreach ($dominantColors as $color) {
            if (isset($color['saturation'])) {
                $saturationSum += $color['saturation'];
                $count++;
            }
        }

        $avgSaturation = $count > 0 ? $saturationSum / $count : 0.3;

        // Ideal saturation is around 0.4-0.6
        $saturationScore = 1 - abs($avgSaturation - 0.5) * 2;
        $saturationScore = max(0, $saturationScore);

        $score = $candidateScore * 0.6 + $saturationScore * 0.4;

        return (int) ($score * 100);
    }

    /**
     * Calculate weighted total score.
     */
    protected function calculateTotalScore(array $scores): int
    {
        $total = 0;

        foreach (self::SCORING_WEIGHTS as $criterion => $weight) {
            $total += ($scores[$criterion] ?? 0) * $weight;
        }

        return (int) $total;
    }

    /**
     * Get the best URL for a photo at target dimensions.
     */
    protected function getPhotoUrl(array $photo, int $targetWidth, int $targetHeight): ?string
    {
        // Try different URL keys in order of preference
        $urlKeys = ['src', 'url', 'large', 'regular', 'medium', 'small'];

        // Check for nested urls object (Unsplash format)
        if (isset($photo['urls'])) {
            foreach (['regular', 'small', 'thumb'] as $size) {
                if (isset($photo['urls'][$size])) {
                    return $photo['urls'][$size];
                }
            }
        }

        // Check for Pexels format
        if (isset($photo['src'])) {
            foreach (['large', 'medium', 'small'] as $size) {
                if (isset($photo['src'][$size])) {
                    return $photo['src'][$size];
                }
            }
        }

        // Direct URL
        foreach ($urlKeys as $key) {
            if (isset($photo[$key]) && is_string($photo[$key])) {
                return $photo[$key];
            }
        }

        return null;
    }
}
