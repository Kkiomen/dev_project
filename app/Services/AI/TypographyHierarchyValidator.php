<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Typography Hierarchy Validator.
 *
 * Ensures visual hierarchy is maintained:
 * - Headline MUST be larger than subtext
 * - Subtext MUST be larger than CTA text
 */
class TypographyHierarchyValidator
{
    /**
     * Minimum ratio between headline and subtext font sizes.
     * Modern Instagram-quality design requires 3.5x for visual dominance.
     * Visual weight distribution: Headline 70%, Subtext 20%, CTA 10%
     */
    protected float $headlineToSubtextRatio = 3.5;

    /**
     * Minimum ratio between subtext and CTA font sizes.
     */
    protected float $subtextToCtaRatio = 1.0;

    /**
     * Keywords to identify layer types.
     */
    protected array $headlineKeywords = ['headline', 'title', 'header', 'heading', 'h1', 'h2'];
    protected array $subtextKeywords = ['subtext', 'subtitle', 'description', 'body', 'tagline', 'sub'];
    protected array $ctaKeywords = ['cta', 'button', 'action', 'call'];

    /**
     * Validate typography hierarchy.
     * Returns array of issues found.
     */
    public function validateHierarchy(array $layers): array
    {
        $issues = [];

        $headline = $this->findLayerByType($layers, 'headline');
        $subtext = $this->findLayerByType($layers, 'subtext');
        $cta = $this->findLayerByType($layers, 'cta');

        Log::channel('single')->debug('TypographyHierarchy: Validating hierarchy', [
            'headline_size' => $headline['properties']['fontSize'] ?? 'not found',
            'subtext_size' => $subtext['properties']['fontSize'] ?? 'not found',
            'cta_size' => $cta['properties']['fontSize'] ?? 'not found',
            'required_ratio' => $this->headlineToSubtextRatio,
        ]);

        // Check headline vs subtext hierarchy
        if ($headline && $subtext) {
            $headlineSize = $headline['properties']['fontSize'] ?? 0;
            $subtextSize = $subtext['properties']['fontSize'] ?? 0;

            if ($headlineSize <= $subtextSize) {
                $issues[] = [
                    'type' => 'hierarchy_violation',
                    'message' => "Headline ({$headlineSize}px) must be larger than subtext ({$subtextSize}px)",
                    'fix' => [
                        'layer' => $headline['name'] ?? 'headline',
                        'property' => 'fontSize',
                        'suggested' => (int) ($subtextSize * $this->headlineToSubtextRatio),
                    ],
                ];
            }
        }

        // Check subtext vs CTA hierarchy
        if ($subtext && $cta) {
            $subtextSize = $subtext['properties']['fontSize'] ?? 0;
            $ctaSize = $cta['properties']['fontSize'] ?? 0;

            if ($subtextSize < $ctaSize) {
                $issues[] = [
                    'type' => 'hierarchy_violation',
                    'message' => "Subtext ({$subtextSize}px) should be at least equal to CTA ({$ctaSize}px)",
                    'fix' => [
                        'layer' => $subtext['name'] ?? 'subtext',
                        'property' => 'fontSize',
                        'suggested' => $ctaSize,
                    ],
                ];
            }
        }

        return $issues;
    }

    /**
     * Maximum headline size (in pixels) to prevent visual weight dominance.
     * Even with 3.5x ratio, headline shouldn't exceed this.
     */
    protected int $maxHeadlineSize = 61; // Reduced from 76 to prevent 94%+ dominance

    /**
     * Maximum headline height as percentage of canvas.
     * Headline block should not exceed 25% of canvas height.
     */
    protected float $maxHeadlineHeightRatio = 0.25;

    /**
     * Modular scale (Major Third 1.25) for snapping font sizes.
     */
    protected array $modularScale = [13, 16, 20, 25, 31, 39, 49, 61, 76, 95];

    /**
     * Fix hierarchy issues automatically.
     */
    public function fixHierarchy(array $layers): array
    {
        $headline = $this->findLayerByType($layers, 'headline');
        $subtext = $this->findLayerByType($layers, 'subtext');
        $cta = $this->findLayerByType($layers, 'cta');

        // Get current sizes
        $ctaSize = $cta['properties']['fontSize'] ?? 16;
        $subtextSize = $subtext['properties']['fontSize'] ?? 20;
        $headlineSize = $headline['properties']['fontSize'] ?? 48;

        Log::channel('single')->info('TypographyHierarchy: Fixing hierarchy', [
            'current' => [
                'headline' => $headlineSize,
                'subtext' => $subtextSize,
                'cta' => $ctaSize,
            ],
            'ratio_requirement' => $this->headlineToSubtextRatio,
        ]);

        // Calculate proper hierarchy: headline = 3.5x subtext, subtext = 1.25x CTA
        $idealSubtextSize = max($subtextSize, (int) ($ctaSize * 1.25));
        $idealHeadlineSize = max($headlineSize, (int) ($idealSubtextSize * $this->headlineToSubtextRatio));

        // VISUAL WEIGHT CAP: Don't let headline dominate everything
        // Cap at max size to preserve visual balance (60% headline, 20% subtext, 20% CTA)
        if ($idealHeadlineSize > $this->maxHeadlineSize) {
            Log::channel('single')->warning('TypographyHierarchy: Capping headline to prevent dominance', [
                'calculated' => $idealHeadlineSize,
                'capped_to' => $this->maxHeadlineSize,
            ]);
            $idealHeadlineSize = $this->maxHeadlineSize;

            // Recalculate subtext based on capped headline
            $idealSubtextSize = max($subtextSize, (int) ($idealHeadlineSize / 3.0));
        }

        // Snap to modular scale for professional typography
        $idealHeadlineSize = $this->snapToModularScale($idealHeadlineSize);
        $idealSubtextSize = $this->snapToModularScale($idealSubtextSize);

        Log::channel('single')->info('TypographyHierarchy: Calculated ideal sizes', [
            'ideal_headline' => $idealHeadlineSize,
            'ideal_subtext' => $idealSubtextSize,
            'headline_needs_fix' => $headlineSize < $idealHeadlineSize,
            'subtext_needs_fix' => $subtextSize < $idealSubtextSize,
        ]);

        // Apply fixes
        $fixedLayers = [];
        $fixes = [];

        foreach ($layers as $layer) {
            $name = strtolower($layer['name'] ?? '');

            if ($this->matchesKeywords($name, $this->headlineKeywords)) {
                $currentSize = $layer['properties']['fontSize'] ?? 0;

                // Increase if too small
                if ($currentSize < $idealHeadlineSize) {
                    $layer['properties']['fontSize'] = $idealHeadlineSize;
                    $fixes[] = "Fixed headline size to {$idealHeadlineSize}px (was too small)";
                }
                // Cap if too large (visual weight dominance prevention)
                elseif ($currentSize > $this->maxHeadlineSize) {
                    $cappedSize = $this->snapToModularScale($this->maxHeadlineSize);
                    $layer['properties']['fontSize'] = $cappedSize;
                    $fixes[] = "Capped headline size to {$cappedSize}px (was {$currentSize}px - too dominant)";
                }
            }

            if ($this->matchesKeywords($name, $this->subtextKeywords)) {
                if (($layer['properties']['fontSize'] ?? 0) < $idealSubtextSize) {
                    $layer['properties']['fontSize'] = $idealSubtextSize;
                    $fixes[] = "Fixed subtext size to {$idealSubtextSize}px";
                }
            }

            $fixedLayers[] = $layer;
        }

        if (!empty($fixes)) {
            Log::channel('single')->info('Typography hierarchy fixes applied', [
                'fixes' => $fixes,
            ]);
        }

        return $fixedLayers;
    }

    /**
     * Find a layer by its semantic type (headline, subtext, cta).
     */
    protected function findLayerByType(array $layers, string $type): ?array
    {
        $keywords = match ($type) {
            'headline' => $this->headlineKeywords,
            'subtext' => $this->subtextKeywords,
            'cta' => $this->ctaKeywords,
            default => [],
        };

        foreach ($layers as $layer) {
            $name = strtolower($layer['name'] ?? '');
            $layerType = strtolower($layer['type'] ?? '');

            // Check by name
            if ($this->matchesKeywords($name, $keywords)) {
                return $layer;
            }

            // For CTA, also check if it's a textbox (button)
            if ($type === 'cta' && $layerType === 'textbox') {
                return $layer;
            }
        }

        // Secondary check: for headline, find largest text
        if ($type === 'headline') {
            return $this->findLargestText($layers);
        }

        return null;
    }

    /**
     * Check if name matches any of the keywords.
     */
    protected function matchesKeywords(string $name, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the layer with the largest font size (likely the headline).
     */
    protected function findLargestText(array $layers): ?array
    {
        $largest = null;
        $maxSize = 0;

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            if (!in_array($type, ['text', 'textbox'])) {
                continue;
            }

            $size = $layer['properties']['fontSize'] ?? 0;
            if ($size > $maxSize) {
                $maxSize = $size;
                $largest = $layer;
            }
        }

        return $largest;
    }

    /**
     * Get recommended font sizes based on CTA size.
     * Implements 70-20-10 visual weight rule.
     */
    public function getRecommendedSizes(int $ctaSize = 16): array
    {
        return [
            'cta' => $ctaSize,
            'subtext' => (int) ($ctaSize * 1.25),
            'headline' => (int) ($ctaSize * 1.25 * $this->headlineToSubtextRatio),
        ];
    }

    /**
     * Calculate visual weight distribution score.
     * Target: Headline 70%, Subtext 20%, CTA 10%
     *
     * @return array{score: float, distribution: array, valid: bool}
     */
    public function calculateVisualWeight(array $layers): array
    {
        $headline = $this->findLayerByType($layers, 'headline');
        $subtext = $this->findLayerByType($layers, 'subtext');
        $cta = $this->findLayerByType($layers, 'cta');

        $headlineSize = $headline['properties']['fontSize'] ?? 0;
        $subtextSize = $subtext['properties']['fontSize'] ?? 0;
        $ctaSize = $cta['properties']['fontSize'] ?? 0;

        $total = $headlineSize + $subtextSize + $ctaSize;

        if ($total === 0) {
            return [
                'score' => 0,
                'distribution' => ['headline' => 0, 'subtext' => 0, 'cta' => 0],
                'valid' => false,
            ];
        }

        $distribution = [
            'headline' => round(($headlineSize / $total) * 100, 1),
            'subtext' => round(($subtextSize / $total) * 100, 1),
            'cta' => round(($ctaSize / $total) * 100, 1),
        ];

        // Target: 70-20-10 (allow 10% deviation)
        $headlineValid = abs($distribution['headline'] - 70) <= 15;
        $subtextValid = abs($distribution['subtext'] - 20) <= 10;
        $ctaValid = abs($distribution['cta'] - 10) <= 5;

        $score = 0;
        if ($headlineValid) $score += 50;
        if ($subtextValid) $score += 30;
        if ($ctaValid) $score += 20;

        return [
            'score' => $score,
            'distribution' => $distribution,
            'valid' => $headlineValid && $subtextValid && $ctaValid,
            'target' => ['headline' => 70, 'subtext' => 20, 'cta' => 10],
        ];
    }

    /**
     * Snap a font size to the nearest value on the modular scale.
     * This ensures professional typography with consistent ratios.
     */
    protected function snapToModularScale(int $size): int
    {
        $closest = $this->modularScale[0];
        $minDiff = abs($size - $closest);

        foreach ($this->modularScale as $scaleValue) {
            $diff = abs($size - $scaleValue);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $scaleValue;
            }
        }

        Log::channel('single')->debug('TypographyHierarchy: Snapped to modular scale', [
            'original' => $size,
            'snapped' => $closest,
        ]);

        return $closest;
    }
}
