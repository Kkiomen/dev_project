<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Text Positioning Service.
 *
 * Ensures text layers are properly positioned without overlapping
 * and maintains proper visual hierarchy and spacing.
 */
class TextPositioningService
{
    /**
     * Minimum vertical spacing between text layers.
     */
    protected int $minVerticalSpacing = 16;

    /**
     * Standard margin from edges (80px = 10 × 8pt grid units for professional spacing).
     */
    protected int $standardMargin = 80;

    /**
     * Fix text positioning issues.
     */
    public function fixTextPositioning(array $layers, int $templateWidth, int $templateHeight): array
    {
        $textLayers = [];
        $otherLayers = [];

        // Separate text and non-text layers
        foreach ($layers as $index => $layer) {
            $type = $layer['type'] ?? '';
            if (in_array($type, ['text', 'textbox'])) {
                $textLayers[] = ['index' => $index, 'layer' => $layer];
            } else {
                $otherLayers[] = $layer;
            }
        }

        if (count($textLayers) < 2) {
            return $layers;
        }

        // Sort text layers by their intended hierarchy
        $textLayers = $this->sortByHierarchy($textLayers);

        // Check for overlapping text
        $hasOverlap = $this->detectTextOverlaps($textLayers);

        if ($hasOverlap) {
            Log::channel('single')->warning('Text overlap detected, repositioning');
            $textLayers = $this->repositionTextLayers($textLayers, $templateWidth, $templateHeight);
        }

        // Ensure proper margins
        $textLayers = $this->ensureProperMargins($textLayers, $templateWidth);

        // Rebuild layers array
        return $this->rebuildLayers($otherLayers, $textLayers, $layers);
    }

    /**
     * Sort text layers by hierarchy (headline first, then subtext, then CTA).
     */
    protected function sortByHierarchy(array $textLayers): array
    {
        usort($textLayers, function ($a, $b) {
            $orderA = $this->getHierarchyOrder($a['layer']);
            $orderB = $this->getHierarchyOrder($b['layer']);
            return $orderA <=> $orderB;
        });

        return $textLayers;
    }

    /**
     * Get hierarchy order for a layer (lower = higher in hierarchy).
     */
    protected function getHierarchyOrder(array $layer): int
    {
        $name = strtolower($layer['name'] ?? '');
        $fontSize = $layer['properties']['fontSize'] ?? 16;
        $type = $layer['type'] ?? '';

        // CTA buttons are last
        if ($type === 'textbox' || str_contains($name, 'cta') || str_contains($name, 'button')) {
            return 100;
        }

        // Headlines first (large text or named headline)
        if (str_contains($name, 'headline') || str_contains($name, 'title') || str_contains($name, 'header')) {
            return 1;
        }
        if ($fontSize >= 36) {
            return 2;
        }

        // Subtext middle
        if (str_contains($name, 'subtext') || str_contains($name, 'subtitle') || str_contains($name, 'desc')) {
            return 50;
        }

        // Default based on font size (larger = higher priority)
        return 100 - $fontSize;
    }

    /**
     * Detect if any text layers overlap.
     */
    protected function detectTextOverlaps(array $textLayers): bool
    {
        for ($i = 0; $i < count($textLayers); $i++) {
            for ($j = $i + 1; $j < count($textLayers); $j++) {
                if ($this->layersOverlap($textLayers[$i]['layer'], $textLayers[$j]['layer'])) {
                    return true;
                }
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

        return $x1 < $x2 + $w2 &&
               $x1 + $w1 > $x2 &&
               $y1 < $y2 + $h2 &&
               $y1 + $h1 > $y2;
    }

    /**
     * Reposition text layers to avoid overlaps.
     * Preserves the original layout intent (top vs bottom focused).
     *
     * IMPORTANT: Only adjusts Y positions to fix overlaps.
     * X positions are PRESERVED to respect archetype text zones (e.g., hero_right at x=600).
     */
    protected function repositionTextLayers(array $textLayers, int $templateWidth, int $templateHeight): array
    {
        if (empty($textLayers)) {
            return $textLayers;
        }

        // CRITICAL: Remember original X positions - don't override archetype text zones!
        // Archetypes like hero_right position text at x=600, we must not change that.
        $originalXPositions = [];
        foreach ($textLayers as $index => $item) {
            $originalXPositions[$index] = $item['layer']['x'] ?? $this->standardMargin;
        }

        // Find CTA layer
        $ctaIndex = null;
        foreach ($textLayers as $index => $item) {
            $layer = $item['layer'];
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            if ($type === 'textbox' || str_contains($name, 'cta') || str_contains($name, 'button')) {
                $ctaIndex = $index;
                break;
            }
        }

        // Determine if original layout was bottom-focused
        // Check where the headline was originally positioned
        $isBottomFocused = false;
        foreach ($textLayers as $item) {
            $layer = $item['layer'];
            $name = strtolower($layer['name'] ?? '');
            $y = $layer['y'] ?? 0;

            // If headline/title was in bottom half, this is a bottom-focused layout
            if ((str_contains($name, 'headline') || str_contains($name, 'title')) && $y > $templateHeight * 0.4) {
                $isBottomFocused = true;
                break;
            }
        }

        // Determine the text zone X position from original positions (use most common/leftmost)
        // This respects archetype text zones
        $textZoneX = min($originalXPositions) > 400 ? min($originalXPositions) : $this->standardMargin;

        Log::channel('single')->debug('TextPositioning: Layout analysis', [
            'is_bottom_focused' => $isBottomFocused,
            'template_height' => $templateHeight,
            'preserved_text_zone_x' => $textZoneX,
            'original_x_positions' => $originalXPositions,
        ]);

        // Position CTA at bottom first (CTA can be centered)
        if ($ctaIndex !== null) {
            $textLayers[$ctaIndex]['layer']['y'] = $templateHeight - 120;
            $textLayers[$ctaIndex]['layer']['x'] = ($templateWidth - ($textLayers[$ctaIndex]['layer']['width'] ?? 220)) / 2;
        }

        // Get non-CTA text layers and their total height
        $contentLayers = [];
        $totalContentHeight = 0;
        foreach ($textLayers as $index => $item) {
            if ($index === $ctaIndex) continue;
            $contentLayers[] = ['index' => $index, 'height' => $item['layer']['height'] ?? 50];
            $totalContentHeight += ($item['layer']['height'] ?? 50) + $this->minVerticalSpacing * 2;
        }

        // Calculate the max width available in the text zone
        $maxWidth = $templateWidth - $textZoneX - $this->standardMargin;

        if ($isBottomFocused) {
            // BOTTOM-FOCUSED: Stack text layers above CTA, from bottom up
            $ctaTop = $ctaIndex !== null ? ($templateHeight - 120) : $templateHeight;
            $bottomLimit = $ctaTop - 40; // Space above CTA
            $currentY = $bottomLimit;

            // Process in reverse order (so headline ends up at top of the text block)
            $contentLayers = array_reverse($contentLayers);

            foreach ($contentLayers as $item) {
                $index = $item['index'];
                $layer = &$textLayers[$index]['layer'];
                $height = $layer['height'] ?? 50;

                // Position from bottom up (ONLY adjust Y, preserve X)
                $currentY -= $height;
                $layer['y'] = max($this->standardMargin, (int) $currentY);

                // PRESERVE original X position from archetype (don't reset to margin!)
                $layer['x'] = $originalXPositions[$index];

                // Ensure width fits within text zone
                if (($layer['width'] ?? 0) > $maxWidth) {
                    $layer['width'] = $maxWidth;
                }

                $currentY -= $this->minVerticalSpacing * 2;
            }
        } else {
            // TOP-FOCUSED: Stack from top down (original behavior)
            $currentY = $this->standardMargin;
            $bottomLimit = $ctaIndex !== null ? $templateHeight - 200 : $templateHeight - 100;

            foreach ($textLayers as $index => &$item) {
                if ($index === $ctaIndex) continue;

                $layer = &$item['layer'];
                $height = $layer['height'] ?? 50;

                // Make sure we don't exceed bottom limit
                if ($currentY + $height > $bottomLimit) {
                    $currentY = $bottomLimit - $height - $this->minVerticalSpacing;
                }

                $layer['y'] = (int) $currentY;

                // PRESERVE original X position from archetype (don't reset to margin!)
                $layer['x'] = $originalXPositions[$index];

                // Ensure width fits within text zone
                if (($layer['width'] ?? 0) > $maxWidth) {
                    $layer['width'] = $maxWidth;
                }

                $currentY += $height + $this->minVerticalSpacing * 2;
            }
        }

        return $textLayers;
    }

    /**
     * Ensure all text layers have proper margins.
     *
     * IMPORTANT: Respects archetype text zones. For split layouts (hero_left, hero_right, split_content),
     * text positioned at x > 400 is in a "right text zone" and should not be moved to left margin.
     */
    protected function ensureProperMargins(array $textLayers, int $templateWidth): array
    {
        foreach ($textLayers as &$item) {
            $layer = &$item['layer'];

            $x = $layer['x'] ?? 0;
            $width = $layer['width'] ?? 0;

            // Determine if this is a right-side text zone (split layout archetypes)
            // Text at x > 400 is intentionally on the right side - don't move to left margin
            $isRightZone = $x >= 400;

            // Fix left margin - but ONLY for left-zone layouts
            // Don't mess with right-zone text (hero_right, split_content, etc.)
            if (!$isRightZone && $x < $this->standardMargin && $x > 0) {
                $layer['x'] = $this->standardMargin;
            }

            // Fix right margin - ensure text doesn't overflow canvas
            $rightEdge = $x + $width;
            $rightMargin = $templateWidth - $rightEdge;

            if ($rightMargin < $this->standardMargin && $rightMargin > 0) {
                // For right-zone layouts, reduce width instead of moving
                if ($isRightZone) {
                    $layer['width'] = $templateWidth - $x - $this->standardMargin;
                } else {
                    // Left-zone: either move left or reduce width
                    if ($width > $templateWidth - ($this->standardMargin * 2)) {
                        $layer['width'] = $templateWidth - ($this->standardMargin * 2);
                        $layer['x'] = $this->standardMargin;
                    } else {
                        $layer['x'] = $templateWidth - $width - $this->standardMargin;
                    }
                }
            }
        }

        return $textLayers;
    }

    /**
     * Rebuild layers array with fixed text layers.
     */
    protected function rebuildLayers(array $otherLayers, array $textLayers, array $originalLayers): array
    {
        $result = [];

        // First, add non-text layers in their original order
        foreach ($originalLayers as $original) {
            $type = $original['type'] ?? '';

            if (!in_array($type, ['text', 'textbox'])) {
                $result[] = $original;
            }
        }

        // Then add text layers in hierarchy order
        foreach ($textLayers as $item) {
            $result[] = $item['layer'];
        }

        return $result;
    }

    /**
     * Center a text layer horizontally.
     */
    public function centerHorizontally(array $layer, int $templateWidth): array
    {
        $width = $layer['width'] ?? 200;
        $layer['x'] = (int) (($templateWidth - $width) / 2);

        return $layer;
    }

    /**
     * Align text layers to a common left edge.
     */
    public function alignLeft(array $layers, int $leftX = 40): array
    {
        foreach ($layers as &$layer) {
            $type = $layer['type'] ?? '';
            if (in_array($type, ['text', 'textbox'])) {
                $layer['x'] = $leftX;
            }
        }

        return $layers;
    }

    /**
     * Calculate proper Y position for a layer based on others.
     */
    public function calculateNextYPosition(array $existingLayers, int $spacing = 24): int
    {
        $maxBottom = 0;

        foreach ($existingLayers as $layer) {
            $type = $layer['type'] ?? '';
            if (!in_array($type, ['text', 'textbox'])) {
                continue;
            }

            $bottom = ($layer['y'] ?? 0) + ($layer['height'] ?? 0);
            $maxBottom = max($maxBottom, $bottom);
        }

        return $maxBottom + $spacing;
    }
}
