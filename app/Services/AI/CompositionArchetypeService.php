<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Composition Archetype Service.
 *
 * Provides 5 professional composition archetypes for template layouts.
 * AI must choose one of these proven compositions instead of generating from scratch.
 */
class CompositionArchetypeService
{
    /**
     * 6 professional composition archetypes.
     * All coordinates are for 1080x1080 canvas.
     */
    public const ARCHETYPES = [
        'hero_left' => [
            'name' => 'Hero Left',
            'description' => 'Text in left column (40%), photo on right (60%)',
            'text_zone' => ['x' => 80, 'y' => 200, 'width' => 400, 'height' => 680],
            'photo_zone' => ['x' => 480, 'y' => 0, 'width' => 600, 'height' => 1080],
            'cta_position' => 'bottom-left',
            'headline_align' => 'left',
            'ideal_focal_x' => [0.6, 1.0], // Focal point should be on right side
        ],
        'hero_right' => [
            'name' => 'Hero Right',
            'description' => 'Photo on left (60%), text in right column (40%)',
            'text_zone' => ['x' => 600, 'y' => 200, 'width' => 400, 'height' => 680],
            'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 600, 'height' => 1080],
            'cta_position' => 'bottom-right',
            'headline_align' => 'left',
            'ideal_focal_x' => [0.0, 0.4], // Focal point should be on left side
        ],
        'split_content' => [
            'name' => 'Split Content',
            'description' => 'Clean 50/50 split - photo left, solid color right (NO text on photo)',
            'text_zone' => ['x' => 580, 'y' => 200, 'width' => 420, 'height' => 680],
            'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 540, 'height' => 1080],
            'background_zone' => ['x' => 540, 'y' => 0, 'width' => 540, 'height' => 1080], // Solid color
            'cta_position' => 'bottom-right',
            'headline_align' => 'left',
            'ideal_focal_x' => [0.0, 0.5], // Focal point on left half
            'requires_overlay' => false, // NO overlay needed - text is on solid color
            'no_text_on_photo' => true, // Critical: text NEVER goes on photo
        ],
        'split_diagonal' => [
            'name' => 'Split Diagonal',
            'description' => 'Diagonal split - photo top-left, color bottom-right',
            'text_zone' => ['x' => 400, 'y' => 500, 'width' => 600, 'height' => 500],
            'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 700],
            'cta_position' => 'bottom-center',
            'headline_align' => 'left',
            'ideal_focal_x' => [0.2, 0.6],
            'requires_overlay' => true,
        ],
        'bottom_focus' => [
            'name' => 'Bottom Focus',
            'description' => 'Photo full width top (60%), colored block with text at bottom (40%)',
            'text_zone' => ['x' => 80, 'y' => 700, 'width' => 920, 'height' => 300],
            'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 1080, 'height' => 650],
            'cta_position' => 'bottom-center',
            'headline_align' => 'center',
            'ideal_focal_y' => [0.0, 0.5], // Focal point should be in upper half
        ],
        'centered_minimal' => [
            'name' => 'Centered Minimal',
            'description' => 'Full-bleed photo with overlay, centered text',
            'text_zone' => ['x' => 140, 'y' => 300, 'width' => 800, 'height' => 480],
            'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 1080, 'height' => 1080],
            'cta_position' => 'bottom-center',
            'headline_align' => 'center',
            'requires_overlay' => true,
            'overlay_opacity' => 0.6,
        ],
    ];

    /**
     * Select the best archetype based on image analysis.
     */
    public function selectArchetype(array $imageAnalysis, array $recentlyUsed = []): string
    {
        $focalPoint = $imageAnalysis['focal_point']['normalized'] ?? ['x' => 0.5, 'y' => 0.5];
        $focalX = $focalPoint['x'];
        $focalY = $focalPoint['y'];

        Log::channel('single')->debug('Selecting composition archetype', [
            'focal_x' => $focalX,
            'focal_y' => $focalY,
            'recently_used' => $recentlyUsed,
        ]);

        // Score each archetype based on focal point compatibility
        $scores = [];

        foreach (self::ARCHETYPES as $name => $archetype) {
            $score = 100;

            // Penalize recently used archetypes
            if (in_array($name, $recentlyUsed)) {
                $score -= 50;
            }

            // Check X focal point compatibility
            if (isset($archetype['ideal_focal_x'])) {
                [$minX, $maxX] = $archetype['ideal_focal_x'];
                if ($focalX >= $minX && $focalX <= $maxX) {
                    $score += 30;
                } else {
                    // Distance penalty
                    $distance = min(abs($focalX - $minX), abs($focalX - $maxX));
                    $score -= $distance * 40;
                }
            }

            // Check Y focal point compatibility
            if (isset($archetype['ideal_focal_y'])) {
                [$minY, $maxY] = $archetype['ideal_focal_y'];
                if ($focalY >= $minY && $focalY <= $maxY) {
                    $score += 30;
                } else {
                    $distance = min(abs($focalY - $minY), abs($focalY - $maxY));
                    $score -= $distance * 40;
                }
            }

            $scores[$name] = $score;
        }

        // Sort by score descending
        arsort($scores);

        $selected = array_key_first($scores);

        Log::channel('single')->info('Archetype selected', [
            'selected' => $selected,
            'scores' => $scores,
        ]);

        return $selected;
    }

    /**
     * Get archetype definition.
     */
    public function getArchetype(string $name): array
    {
        return self::ARCHETYPES[$name] ?? self::ARCHETYPES['centered_minimal'];
    }

    /**
     * Get all archetype names.
     */
    public function getArchetypeNames(): array
    {
        return array_keys(self::ARCHETYPES);
    }

    /**
     * Get archetype constraints formatted for AI prompt.
     */
    public function getArchetypeForPrompt(string $name): string
    {
        $archetype = $this->getArchetype($name);

        $textZone = $archetype['text_zone'];
        $photoZone = $archetype['photo_zone'];

        $prompt = <<<ARCHETYPE
COMPOSITION ARCHETYPE: {$archetype['name']}
{$archetype['description']}

TEXT ZONE CONSTRAINTS:
- Position: x={$textZone['x']}, y={$textZone['y']}
- Size: {$textZone['width']}x{$textZone['height']}
- All text elements (headline, subtext) MUST be within this zone
- Headline alignment: {$archetype['headline_align']}

PHOTO ZONE:
- Position: x={$photoZone['x']}, y={$photoZone['y']}
- Size: {$photoZone['width']}x{$photoZone['height']}

CTA BUTTON: {$archetype['cta_position']}
ARCHETYPE;

        if ($archetype['requires_overlay'] ?? false) {
            $opacity = $archetype['overlay_opacity'] ?? 0.5;
            $prompt .= "\n\nREQUIRED: Add semi-transparent overlay (opacity: {$opacity}) between photo and text for readability.";
        }

        return $prompt;
    }

    /**
     * Get text zone for an archetype.
     */
    public function getTextZone(string $name): array
    {
        $archetype = $this->getArchetype($name);
        return $archetype['text_zone'];
    }

    /**
     * Get photo zone for an archetype.
     */
    public function getPhotoZone(string $name): array
    {
        $archetype = $this->getArchetype($name);
        return $archetype['photo_zone'];
    }

    /**
     * Check if archetype requires overlay.
     */
    public function requiresOverlay(string $name): bool
    {
        $archetype = $this->getArchetype($name);
        return $archetype['requires_overlay'] ?? false;
    }

    /**
     * Get CTA position for archetype.
     */
    public function getCtaPosition(string $name, int $templateWidth = 1080, int $templateHeight = 1080): array
    {
        $archetype = $this->getArchetype($name);
        $position = $archetype['cta_position'] ?? 'bottom-center';

        $ctaWidth = 220;
        $ctaHeight = 50;
        $margin = 80;

        return match ($position) {
            'bottom-left' => [
                'x' => $margin,
                'y' => $templateHeight - $ctaHeight - $margin,
            ],
            'bottom-right' => [
                'x' => $templateWidth - $ctaWidth - $margin,
                'y' => $templateHeight - $ctaHeight - $margin,
            ],
            'bottom-center' => [
                'x' => ($templateWidth - $ctaWidth) / 2,
                'y' => $templateHeight - $ctaHeight - $margin,
            ],
            default => [
                'x' => ($templateWidth - $ctaWidth) / 2,
                'y' => $templateHeight - $ctaHeight - $margin,
            ],
        };
    }
}
