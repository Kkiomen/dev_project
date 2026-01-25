<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Contrast Validator - WCAG AA Compliance.
 *
 * WCAG AA requires:
 * - Minimum 4.5:1 contrast ratio for normal text
 * - Minimum 3:1 contrast ratio for large text (18pt+ or 14pt bold)
 *
 * WCAG AAA requires:
 * - Minimum 7:1 contrast ratio for normal text
 * - Minimum 4.5:1 contrast ratio for large text
 */
class ContrastValidator
{
    /**
     * WCAG AA minimum contrast for normal text.
     */
    public const WCAG_AA_NORMAL = 4.5;

    /**
     * WCAG AA minimum contrast for large text.
     */
    public const WCAG_AA_LARGE = 3.0;

    /**
     * WCAG AAA minimum contrast for normal text.
     */
    public const WCAG_AAA_NORMAL = 7.0;

    /**
     * WCAG AAA minimum contrast for large text.
     */
    public const WCAG_AAA_LARGE = 4.5;

    /**
     * Threshold for "large text" (in pixels, approximate).
     */
    public const LARGE_TEXT_SIZE = 24;

    /**
     * Validate contrast between text and background colors.
     */
    public function validateContrast(string $textColor, string $backgroundColor): array
    {
        $ratio = $this->calculateContrastRatio($textColor, $backgroundColor);

        return [
            'ratio' => round($ratio, 2),
            'passes_aa_normal' => $ratio >= self::WCAG_AA_NORMAL,
            'passes_aa_large' => $ratio >= self::WCAG_AA_LARGE,
            'passes_aaa_normal' => $ratio >= self::WCAG_AAA_NORMAL,
            'passes_aaa_large' => $ratio >= self::WCAG_AAA_LARGE,
        ];
    }

    /**
     * Calculate contrast ratio between two colors.
     * Formula: (L1 + 0.05) / (L2 + 0.05) where L1 is lighter
     */
    public function calculateContrastRatio(string $color1, string $color2): float
    {
        $l1 = $this->relativeLuminance($color1);
        $l2 = $this->relativeLuminance($color2);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Calculate relative luminance of a color.
     * Based on WCAG 2.0 formula.
     */
    protected function relativeLuminance(string $hex): float
    {
        $rgb = $this->hexToRgb($hex);

        $rgb = array_map(function ($channel) {
            $channel = $channel / 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : pow(($channel + 0.055) / 1.055, 2.4);
        }, $rgb);

        // Apply luminance weights
        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }

    /**
     * Convert hex color to RGB array.
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        // Handle 3-character hex
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Suggest a better text color for given background.
     */
    public function suggestTextColor(string $backgroundColor, string $preferredColor = '#FFFFFF'): string
    {
        $contrast = $this->calculateContrastRatio($preferredColor, $backgroundColor);

        if ($contrast >= self::WCAG_AA_NORMAL) {
            return $preferredColor;
        }

        // Try pure white
        $whiteContrast = $this->calculateContrastRatio('#FFFFFF', $backgroundColor);
        if ($whiteContrast >= self::WCAG_AA_NORMAL) {
            return '#FFFFFF';
        }

        // Try pure black
        $blackContrast = $this->calculateContrastRatio('#000000', $backgroundColor);
        if ($blackContrast >= self::WCAG_AA_NORMAL) {
            return '#000000';
        }

        // Return whichever has better contrast
        return $whiteContrast > $blackContrast ? '#FFFFFF' : '#000000';
    }

    /**
     * Validate all text layers in a template.
     */
    public function validateLayers(array $layers, string $defaultBackground = '#FFFFFF'): array
    {
        $issues = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';

            if (!in_array($type, ['text', 'textbox'])) {
                continue;
            }

            $textColor = $layer['properties']['fill'] ?? $layer['properties']['textColor'] ?? '#000000';
            $backgroundColor = $this->findBackgroundForLayer($layer, $layers, $defaultBackground);
            $fontSize = $layer['properties']['fontSize'] ?? 16;

            $result = $this->validateContrast($textColor, $backgroundColor);
            $isLargeText = $fontSize >= self::LARGE_TEXT_SIZE;
            $minRequired = $isLargeText ? self::WCAG_AA_LARGE : self::WCAG_AA_NORMAL;

            if ($result['ratio'] < $minRequired) {
                $issues[] = [
                    'layer' => $layer['name'] ?? 'unknown',
                    'type' => 'contrast_violation',
                    'message' => "Contrast ratio {$result['ratio']}:1 is below WCAG AA minimum ({$minRequired}:1)",
                    'current_ratio' => $result['ratio'],
                    'required_ratio' => $minRequired,
                    'text_color' => $textColor,
                    'background_color' => $backgroundColor,
                    'suggested_text_color' => $this->suggestTextColor($backgroundColor, $textColor),
                ];
            }
        }

        return $issues;
    }

    /**
     * Fix contrast issues in layers.
     */
    public function fixContrastIssues(array $layers, string $defaultBackground = '#FFFFFF'): array
    {
        $fixes = [];

        foreach ($layers as &$layer) {
            $type = $layer['type'] ?? '';

            if (!in_array($type, ['text', 'textbox'])) {
                continue;
            }

            $textColor = $layer['properties']['fill'] ?? $layer['properties']['textColor'] ?? '#000000';
            $backgroundColor = $this->findBackgroundForLayer($layer, $layers, $defaultBackground);
            $fontSize = $layer['properties']['fontSize'] ?? 16;

            $result = $this->validateContrast($textColor, $backgroundColor);
            $isLargeText = $fontSize >= self::LARGE_TEXT_SIZE;
            $minRequired = $isLargeText ? self::WCAG_AA_LARGE : self::WCAG_AA_NORMAL;

            if ($result['ratio'] < $minRequired) {
                $newColor = $this->suggestTextColor($backgroundColor, $textColor);

                if ($type === 'textbox') {
                    $layer['properties']['textColor'] = $newColor;
                } else {
                    $layer['properties']['fill'] = $newColor;
                }

                $fixes[] = [
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_color' => $textColor,
                    'new_color' => $newColor,
                ];
            }
        }

        if (!empty($fixes)) {
            Log::channel('single')->info('Contrast fixes applied', [
                'fixes' => $fixes,
            ]);
        }

        return $layers;
    }

    /**
     * Find the background color behind a layer.
     * This is a simplified approach - finds the largest rectangle or uses default.
     */
    protected function findBackgroundForLayer(array $layer, array $allLayers, string $default): string
    {
        $layerY = $layer['y'] ?? 0;

        // Look for rectangles that could be behind this layer
        foreach ($allLayers as $other) {
            $otherType = $other['type'] ?? '';
            $otherName = strtolower($other['name'] ?? '');

            if ($otherType !== 'rectangle') {
                continue;
            }

            // Check if it's a background layer
            if (str_contains($otherName, 'background') || str_contains($otherName, 'bg')) {
                return $other['properties']['fill'] ?? $default;
            }

            // Check if it's a full-width overlay
            $width = $other['width'] ?? 0;
            $height = $other['height'] ?? 0;
            $otherY = $other['y'] ?? 0;

            if ($width >= 1000 && $height >= 100) {
                // Check if layer is within this rectangle
                if ($layerY >= $otherY && $layerY <= $otherY + $height) {
                    return $other['properties']['fill'] ?? $default;
                }
            }
        }

        return $default;
    }

    /**
     * Check if two colors have sufficient contrast.
     */
    public function hasEnoughContrast(string $color1, string $color2, bool $isLargeText = false): bool
    {
        $ratio = $this->calculateContrastRatio($color1, $color2);
        $minRequired = $isLargeText ? self::WCAG_AA_LARGE : self::WCAG_AA_NORMAL;

        return $ratio >= $minRequired;
    }
}
