<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Color Harmony Validator.
 *
 * Validates color palette coherence beyond WCAG contrast.
 * Uses color theory to ensure harmonious relationships.
 */
class ColorHarmonyValidator
{
    /**
     * Harmony types with their hue angle differences.
     */
    public const HARMONY_TYPES = [
        'complementary' => 180,
        'analogous' => 30,
        'triadic' => 120,
        'split_complementary' => 150,
        'square' => 90,
    ];

    /**
     * Tolerance in degrees for harmony matching.
     */
    public const HARMONY_TOLERANCE = 15;

    /**
     * Minimum saturation for a color to be considered vibrant.
     */
    public const VIBRANT_SATURATION_THRESHOLD = 0.3;

    /**
     * Ideal vibrancy balance ratio (vibrant / total colors).
     */
    public const IDEAL_VIBRANCY_RATIO = [0.2, 0.5];

    /**
     * Validate a color palette for harmony.
     *
     * @param array $colors Array of hex colors
     * @return array Validation result with harmony type, score, and issues
     */
    public function validatePalette(array $colors): array
    {
        if (count($colors) < 2) {
            return [
                'valid' => true,
                'score' => 100,
                'harmony_type' => 'single',
                'issues' => [],
                'suggestions' => [],
            ];
        }

        $hslColors = array_map([$this, 'hexToHsl'], $colors);
        $issues = [];
        $score = 100;

        // Detect harmony type
        $harmonyResult = $this->detectHarmonyType($hslColors);

        // Check vibrancy balance
        $vibrancyResult = $this->checkVibrancyBalance($hslColors);
        if (!$vibrancyResult['balanced']) {
            $issues[] = "color:vibrancy - " . $vibrancyResult['message'];
            $score -= 15;
        }

        // Check for clashing colors (no harmony)
        if ($harmonyResult['type'] === 'unknown' && count($colors) > 2) {
            $issues[] = "color:harmony - Colors don't follow any recognized harmony pattern";
            $score -= 20;
        }

        // Check for too many saturated colors
        $saturatedCount = count(array_filter($hslColors, fn($hsl) => $hsl['s'] > 0.6));
        if ($saturatedCount > 2) {
            $issues[] = "color:saturation - Too many highly saturated colors ({$saturatedCount}). Limit to 1-2 for balance";
            $score -= 10;
        }

        // Check lightness variety
        $lightnessValues = array_column($hslColors, 'l');
        $lightnessRange = max($lightnessValues) - min($lightnessValues);
        if ($lightnessRange < 0.3 && count($colors) > 2) {
            $issues[] = "color:contrast - Insufficient lightness variety. Add light/dark contrast";
            $score -= 10;
        }

        $suggestions = $this->generateSuggestions($issues, $colors);

        $result = [
            'valid' => $score >= 70,
            'score' => max(0, $score),
            'harmony_type' => $harmonyResult['type'],
            'harmony_confidence' => $harmonyResult['confidence'],
            'vibrancy_ratio' => $vibrancyResult['ratio'],
            'issues' => $issues,
            'suggestions' => $suggestions,
        ];

        Log::channel('single')->info('Color harmony validation', [
            'colors_count' => count($colors),
            'harmony_type' => $result['harmony_type'],
            'score' => $result['score'],
            'issues_count' => count($issues),
        ]);

        return $result;
    }

    /**
     * Suggest an accent color based on harmony type.
     *
     * @param string $primary Primary color in hex
     * @param string $type Harmony type (complementary, analogous, triadic)
     * @return string Suggested accent color in hex
     */
    public function suggestAccentColor(string $primary, string $type = 'complementary'): string
    {
        $hsl = $this->hexToHsl($primary);
        $angle = self::HARMONY_TYPES[$type] ?? 180;

        $newHue = fmod($hsl['h'] + $angle, 360);

        // Keep saturation high for accent visibility
        $newSaturation = max($hsl['s'], 0.5);

        // Ensure good contrast with original
        $newLightness = $hsl['l'] > 0.5 ? max(0.3, $hsl['l'] - 0.2) : min(0.7, $hsl['l'] + 0.2);

        return $this->hslToHex([
            'h' => $newHue,
            's' => $newSaturation,
            'l' => $newLightness,
        ]);
    }

    /**
     * Generate a harmonious palette from a base color.
     *
     * @param string $baseColor Base color in hex
     * @param string $harmonyType Harmony type
     * @param int $count Number of colors to generate (2-5)
     * @return array Array of hex colors
     */
    public function generateHarmoniousPalette(string $baseColor, string $harmonyType = 'complementary', int $count = 3): array
    {
        $hsl = $this->hexToHsl($baseColor);
        $palette = [$baseColor];
        $angle = self::HARMONY_TYPES[$harmonyType] ?? 180;

        for ($i = 1; $i < $count; $i++) {
            $newHue = fmod($hsl['h'] + ($angle * $i / ($count - 1)), 360);

            // Vary saturation and lightness slightly
            $satVariation = 0.1 * (($i % 2) ? 1 : -1);
            $lightVariation = 0.15 * (($i % 2) ? -1 : 1);

            $newHsl = [
                'h' => $newHue,
                's' => max(0.1, min(1, $hsl['s'] + $satVariation)),
                'l' => max(0.15, min(0.85, $hsl['l'] + $lightVariation)),
            ];

            $palette[] = $this->hslToHex($newHsl);
        }

        return $palette;
    }

    /**
     * Check if two colors are harmonious.
     */
    public function areColorsHarmonious(string $color1, string $color2): array
    {
        $hsl1 = $this->hexToHsl($color1);
        $hsl2 = $this->hexToHsl($color2);

        $hueDiff = abs($hsl1['h'] - $hsl2['h']);
        if ($hueDiff > 180) {
            $hueDiff = 360 - $hueDiff;
        }

        foreach (self::HARMONY_TYPES as $type => $angle) {
            $tolerance = self::HARMONY_TOLERANCE;

            // Analogous has a range rather than exact angle
            if ($type === 'analogous') {
                if ($hueDiff <= 30) {
                    return ['harmonious' => true, 'type' => $type, 'confidence' => 1 - ($hueDiff / 30)];
                }
                continue;
            }

            if (abs($hueDiff - $angle) <= $tolerance) {
                $confidence = 1 - (abs($hueDiff - $angle) / $tolerance);
                return ['harmonious' => true, 'type' => $type, 'confidence' => $confidence];
            }
        }

        return ['harmonious' => false, 'type' => 'none', 'confidence' => 0];
    }

    /**
     * Detect the harmony type of a palette.
     */
    protected function detectHarmonyType(array $hslColors): array
    {
        if (count($hslColors) < 2) {
            return ['type' => 'single', 'confidence' => 1];
        }

        // Get hue values, filtering out low-saturation colors (grays)
        $hues = [];
        foreach ($hslColors as $hsl) {
            if ($hsl['s'] >= 0.1) {
                $hues[] = $hsl['h'];
            }
        }

        if (count($hues) < 2) {
            return ['type' => 'monochromatic', 'confidence' => 1];
        }

        // Check each harmony type
        $bestMatch = ['type' => 'unknown', 'confidence' => 0];

        foreach (self::HARMONY_TYPES as $type => $expectedAngle) {
            $matchScore = $this->calculateHarmonyMatch($hues, $expectedAngle);
            if ($matchScore > $bestMatch['confidence']) {
                $bestMatch = ['type' => $type, 'confidence' => $matchScore];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate how well hues match a harmony angle.
     */
    protected function calculateHarmonyMatch(array $hues, float $expectedAngle): float
    {
        if (count($hues) < 2) {
            return 0;
        }

        $matches = 0;
        $comparisons = 0;

        for ($i = 0; $i < count($hues); $i++) {
            for ($j = $i + 1; $j < count($hues); $j++) {
                $diff = abs($hues[$i] - $hues[$j]);
                if ($diff > 180) {
                    $diff = 360 - $diff;
                }

                $comparisons++;

                // For analogous, check if within range
                if ($expectedAngle === 30) {
                    if ($diff <= 30) {
                        $matches += 1 - ($diff / 30);
                    }
                } else {
                    $deviation = abs($diff - $expectedAngle);
                    if ($deviation <= self::HARMONY_TOLERANCE) {
                        $matches += 1 - ($deviation / self::HARMONY_TOLERANCE);
                    }
                }
            }
        }

        return $comparisons > 0 ? $matches / $comparisons : 0;
    }

    /**
     * Check vibrancy balance in palette.
     */
    protected function checkVibrancyBalance(array $hslColors): array
    {
        $vibrantCount = 0;
        foreach ($hslColors as $hsl) {
            if ($hsl['s'] >= self::VIBRANT_SATURATION_THRESHOLD && $hsl['l'] > 0.2 && $hsl['l'] < 0.8) {
                $vibrantCount++;
            }
        }

        $total = count($hslColors);
        $ratio = $total > 0 ? $vibrantCount / $total : 0;

        $balanced = $ratio >= self::IDEAL_VIBRANCY_RATIO[0] && $ratio <= self::IDEAL_VIBRANCY_RATIO[1];

        $message = '';
        if ($ratio < self::IDEAL_VIBRANCY_RATIO[0]) {
            $message = "Palette lacks vibrant colors. Add a saturated accent";
        } elseif ($ratio > self::IDEAL_VIBRANCY_RATIO[1]) {
            $message = "Too many vibrant colors. Add neutral tones for balance";
        }

        return [
            'balanced' => $balanced,
            'ratio' => round($ratio, 2),
            'vibrant_count' => $vibrantCount,
            'message' => $message,
        ];
    }

    /**
     * Generate suggestions based on issues.
     */
    protected function generateSuggestions(array $issues, array $colors): array
    {
        $suggestions = [];

        foreach ($issues as $issue) {
            if (str_contains($issue, 'harmony')) {
                if (!empty($colors)) {
                    $accent = $this->suggestAccentColor($colors[0], 'complementary');
                    $suggestions[] = "Consider using {$accent} as an accent color for harmony";
                }
            }
            if (str_contains($issue, 'vibrancy')) {
                if (!empty($colors)) {
                    $vibrant = $this->makeVibrant($colors[0]);
                    $suggestions[] = "Add a vibrant accent like {$vibrant}";
                }
            }
            if (str_contains($issue, 'saturation')) {
                $suggestions[] = "Replace some saturated colors with neutrals (#F5F5F5, #333333)";
            }
            if (str_contains($issue, 'lightness')) {
                $suggestions[] = "Add contrast with a very light (#F8F8F8) or very dark (#1A1A1A) color";
            }
        }

        return array_unique($suggestions);
    }

    /**
     * Make a color more vibrant.
     */
    protected function makeVibrant(string $hex): string
    {
        $hsl = $this->hexToHsl($hex);
        $hsl['s'] = min(1, $hsl['s'] + 0.3);
        $hsl['l'] = 0.5; // Optimal lightness for vibrancy

        return $this->hslToHex($hsl);
    }

    /**
     * Convert hex color to HSL.
     */
    public function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');

        // Handle 3-character hex
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        // Lightness
        $l = ($max + $min) / 2;

        // Saturation and Hue
        // Use a small epsilon for floating point comparison
        $epsilon = 0.0001;

        if ($delta < $epsilon) {
            // Grayscale - no saturation, no hue
            $s = 0;
            $h = 0;
        } else {
            // Calculate saturation
            $denominator = $l > 0.5 ? (2 - $max - $min) : ($max + $min);
            $s = $denominator > $epsilon ? $delta / $denominator : 0;

            // Calculate hue
            if (abs($max - $r) < $epsilon) {
                $h = 60 * fmod((($g - $b) / $delta), 6);
                if ($g < $b) {
                    $h += 360;
                }
            } elseif (abs($max - $g) < $epsilon) {
                $h = 60 * ((($b - $r) / $delta) + 2);
            } else {
                $h = 60 * ((($r - $g) / $delta) + 4);
            }
        }

        return [
            'h' => round($h, 1),
            's' => round($s, 3),
            'l' => round($l, 3),
        ];
    }

    /**
     * Convert HSL to hex color.
     */
    public function hslToHex(array $hsl): string
    {
        $h = $hsl['h'];
        $s = $hsl['s'];
        $l = $hsl['l'];

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $c = (1 - abs(2 * $l - 1)) * $s;
            $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
            $m = $l - $c / 2;

            if ($h < 60) {
                [$r, $g, $b] = [$c, $x, 0];
            } elseif ($h < 120) {
                [$r, $g, $b] = [$x, $c, 0];
            } elseif ($h < 180) {
                [$r, $g, $b] = [0, $c, $x];
            } elseif ($h < 240) {
                [$r, $g, $b] = [0, $x, $c];
            } elseif ($h < 300) {
                [$r, $g, $b] = [$x, 0, $c];
            } else {
                [$r, $g, $b] = [$c, 0, $x];
            }

            $r += $m;
            $g += $m;
            $b += $m;
        }

        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
