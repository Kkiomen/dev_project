<?php

namespace App\Services\AI;

use App\Models\Brand;
use Illuminate\Support\Facades\Log;

/**
 * Design Tokens Service.
 *
 * Provides a constrained "palette of building blocks" for AI to use.
 * Uses modular scale for typography and 8pt grid for spacing.
 *
 * Typography scale: S_n = S_0 × (1.25)^n, where S_0 = 16
 */
class DesignTokensService
{
    /**
     * Modular typography scale (ratio 1.25).
     * Base size: 16px.
     */
    public const FONT_SCALE = [
        'xs' => 13,   // 16 × 1.25^-1
        'sm' => 16,   // base
        'md' => 20,   // 16 × 1.25^1
        'lg' => 25,   // 16 × 1.25^2
        'xl' => 31,   // 16 × 1.25^3
        '2xl' => 39,  // 16 × 1.25^4
        '3xl' => 49,  // 16 × 1.25^5
        '4xl' => 61,  // 16 × 1.25^6
        '5xl' => 76,  // 16 × 1.25^7 - Premium headline size
        '6xl' => 95,  // 16 × 1.25^8 - Impact headline size
    ];

    /**
     * Spacing scale (8pt grid).
     */
    public const SPACING_SCALE = [8, 16, 24, 32, 48, 64, 80, 96, 120];

    /**
     * Baseline grid unit (all line-heights must be multiples of this).
     */
    public const BASELINE_UNIT = 8;

    /**
     * Safe margin ratio for the 80% canvas rule.
     * 10% margin on each side = 80% usable area.
     */
    public const SAFE_MARGIN_RATIO = 0.10;

    /**
     * Minimum margin in pixels (fallback for small canvases).
     */
    public const MINIMUM_MARGIN = 80;

    /**
     * Line height multipliers by context.
     */
    public const LINE_HEIGHT_SCALE = [
        'headline_tight' => 1.1,    // Multi-line headlines
        'headline_normal' => 1.2,   // Single-line headlines
        'body_tight' => 1.4,        // Dense text
        'body_normal' => 1.5,       // Standard text
        'body_loose' => 1.75,       // Loose, easy to scan
    ];

    /**
     * Tracking (letter-spacing) by font size.
     * Smaller fonts = more tracking, larger fonts = tighter.
     */
    public const TRACKING_SCALE = [
        'xs' => 0.05,   // +5% for 13px
        'sm' => 0.02,   // +2% for 16px
        'md' => 0.0,    // 0% for 20px
        'lg' => -0.01,  // -1% for 25px
        'xl' => -0.02,  // -2% for 31px+
        '2xl' => -0.02,
        '3xl' => -0.015,
        '4xl' => -0.01,
        '5xl' => -0.005, // Tighter for large headlines
        '6xl' => 0.0,    // Neutral for impact headlines
    ];

    /**
     * Allowed corner radius values.
     */
    public const CORNER_RADIUS = [0, 8, 12, 16, 24, 500]; // 500 = pill shape

    /**
     * Allowed stroke widths.
     */
    public const STROKE_WIDTH = [1, 2, 3, 4, 6, 8];

    /**
     * Industry-specific font pairs for professional design.
     */
    public const INDUSTRY_FONTS = [
        'medical' => [
            'heading' => 'Poppins',
            'heading_weight' => '600',
            'body' => 'Open Sans',
            'body_weight' => '400',
            'scale_ratio' => 1.25,
        ],
        'beauty' => [
            'heading' => 'Playfair Display',
            'heading_weight' => '500',
            'body' => 'Montserrat',
            'body_weight' => '300',
            'scale_ratio' => 1.333,
        ],
        'gastro' => [
            'heading' => 'Lora',
            'heading_weight' => '500',
            'heading_style' => 'italic',
            'body' => 'Montserrat',
            'body_weight' => '600',
            'scale_ratio' => 1.5,
        ],
        'fitness' => [
            'heading' => 'Oswald',
            'heading_weight' => '700',
            'body' => 'Roboto',
            'body_weight' => '400',
            'scale_ratio' => 1.414,
        ],
        'technology' => [
            'heading' => 'Inter',
            'heading_weight' => '700',
            'body' => 'Inter',
            'body_weight' => '400',
            'scale_ratio' => 1.25,
        ],
        'luxury' => [
            'heading' => 'Cormorant Garamond',
            'heading_weight' => '600',
            'body' => 'Montserrat',
            'body_weight' => '300',
            'scale_ratio' => 1.5,
        ],
        'default' => [
            'heading' => 'Montserrat',
            'heading_weight' => '700',
            'body' => 'Montserrat',
            'body_weight' => '400',
            'scale_ratio' => 1.25,
        ],
    ];

    /**
     * Default colors when no brand is provided.
     * Note: text_muted is derived dynamically from background for color harmony.
     */
    protected array $defaultColors = [
        'primary' => '#1E3A5F',
        'secondary' => '#0F2544',
        'accent' => '#D4AF37',
        'text_light' => '#FFFFFF',
        'text_muted' => '#8BA3BE', // 70% tint of #1E3A5F (harmonious, not generic gray)
        'text_dark' => '#1A1A2E',
        'background_dark' => '#1A1A2E',
        'background_light' => '#FFFFFF',
    ];

    /**
     * Get design tokens for a brand.
     */
    public function getTokensForBrand(?Brand $brand): array
    {
        $colors = $this->getColorsForBrand($brand);

        return [
            'colors' => $colors,
            'fontSizes' => self::FONT_SCALE,
            'spacing' => self::SPACING_SCALE,
            'cornerRadius' => self::CORNER_RADIUS,
            'strokeWidth' => self::STROKE_WIDTH,
        ];
    }

    /**
     * Get colors from brand or use defaults.
     * Dynamically calculates text_muted as a tint of the primary color for harmony.
     */
    protected function getColorsForBrand(?Brand $brand): array
    {
        if (!$brand) {
            return $this->defaultColors;
        }

        $brandColors = $brand->colors ?? [];

        // Get primary color to derive text_muted from it (color harmony)
        $primary = $brandColors['primary'] ?? $this->defaultColors['primary'];

        // If text_muted is not explicitly set, derive it from primary
        $textMuted = $brandColors['text_muted'] ?? $this->getSubtextColor($primary);

        return [
            'primary' => $primary,
            'secondary' => $brandColors['secondary'] ?? $this->defaultColors['secondary'],
            'accent' => $brandColors['accent'] ?? $this->defaultColors['accent'],
            'text_light' => $brandColors['text_light'] ?? $this->defaultColors['text_light'],
            'text_muted' => $textMuted,
            'text_dark' => $brandColors['text_dark'] ?? $this->defaultColors['text_dark'],
            'background_dark' => $brandColors['background_dark'] ?? $this->defaultColors['background_dark'],
            'background_light' => $brandColors['background_light'] ?? $this->defaultColors['background_light'],
        ];
    }

    /**
     * Get safe margins for canvas using 80% rule.
     * Returns margins that leave 80% usable canvas area.
     *
     * @return array{left: int, right: int, top: int, bottom: int}
     */
    public function getSafeMargins(int $width, int $height): array
    {
        $margins = [
            'left' => max(self::MINIMUM_MARGIN, (int)($width * self::SAFE_MARGIN_RATIO)),
            'right' => max(self::MINIMUM_MARGIN, (int)($width * self::SAFE_MARGIN_RATIO)),
            'top' => max(self::MINIMUM_MARGIN, (int)($height * self::SAFE_MARGIN_RATIO)),
            'bottom' => max(self::MINIMUM_MARGIN, (int)($height * self::SAFE_MARGIN_RATIO)),
        ];

        Log::channel('single')->debug('DesignTokens: Calculated safe margins (80% rule)', [
            'canvas' => ['width' => $width, 'height' => $height],
            'margins' => $margins,
            'usable_width' => $width - $margins['left'] - $margins['right'],
            'usable_height' => $height - $margins['top'] - $margins['bottom'],
        ]);

        return $margins;
    }

    /**
     * Get the usable canvas area after applying safe margins.
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    public function getUsableArea(int $width, int $height): array
    {
        $margins = $this->getSafeMargins($width, $height);

        return [
            'x' => $margins['left'],
            'y' => $margins['top'],
            'width' => $width - $margins['left'] - $margins['right'],
            'height' => $height - $margins['top'] - $margins['bottom'],
        ];
    }

    /**
     * Snap font size to nearest value in modular scale.
     */
    public function snapFontSize(int|float $size): int
    {
        return $this->findClosest((int) $size, array_values(self::FONT_SCALE));
    }

    /**
     * Snap spacing to nearest value in scale.
     */
    public function snapSpacing(int|float $spacing): int
    {
        return $this->findClosest((int) $spacing, self::SPACING_SCALE);
    }

    /**
     * Snap corner radius to nearest allowed value.
     */
    public function snapCornerRadius(int|float $radius): int
    {
        return $this->findClosest((int) $radius, self::CORNER_RADIUS);
    }

    /**
     * Snap stroke width to nearest allowed value.
     */
    public function snapStrokeWidth(int|float $width): int
    {
        return $this->findClosest((int) $width, self::STROKE_WIDTH);
    }

    /**
     * Find closest value in a scale.
     */
    protected function findClosest(int $value, array $scale): int
    {
        $closest = $scale[0];
        $minDiff = abs($scale[0] - $value);

        foreach ($scale as $s) {
            $diff = abs($s - $value);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $s;
            }
        }

        return $closest;
    }

    /**
     * Validate if a color is in the allowed palette.
     */
    public function validateColor(string $color, array $allowedColors): bool
    {
        $normalizedColor = strtoupper($color);
        $normalizedAllowed = array_map('strtoupper', $allowedColors);

        return in_array($normalizedColor, $normalizedAllowed);
    }

    /**
     * Snap layer properties to design tokens.
     */
    public function snapLayerToTokens(array $layer): array
    {
        $properties = $layer['properties'] ?? [];

        // Snap font size
        if (isset($properties['fontSize'])) {
            $properties['fontSize'] = $this->snapFontSize($properties['fontSize']);
        }

        // Snap corner radius
        if (isset($properties['cornerRadius'])) {
            $properties['cornerRadius'] = $this->snapCornerRadius($properties['cornerRadius']);
        }

        // Snap stroke width
        if (isset($properties['strokeWidth'])) {
            $properties['strokeWidth'] = $this->snapStrokeWidth($properties['strokeWidth']);
        }

        // Snap padding
        if (isset($properties['padding'])) {
            $properties['padding'] = $this->snapSpacing($properties['padding']);
        }

        $layer['properties'] = $properties;

        return $layer;
    }

    /**
     * Snap all layers to design tokens.
     */
    public function snapAllLayersToTokens(array $layers): array
    {
        return array_map([$this, 'snapLayerToTokens'], $layers);
    }

    /**
     * Get font pair for a specific industry.
     */
    public function getFontsForIndustry(?string $industry): array
    {
        return self::INDUSTRY_FONTS[$industry] ?? self::INDUSTRY_FONTS['default'];
    }

    /**
     * Merge brand colors with extracted image colors for accent candidates.
     */
    public function mergeWithImageColors(array $brandColors, array $imageColors): array
    {
        // Add extracted image colors as additional accent options
        if (!empty($imageColors['accent_candidates'])) {
            $brandColors['image_accent_1'] = $imageColors['accent_candidates'][0] ?? null;
            $brandColors['image_accent_2'] = $imageColors['accent_candidates'][1] ?? null;
        }

        // Also add specific palette colors if available
        if (!empty($imageColors['vibrant'])) {
            $brandColors['image_vibrant'] = $imageColors['vibrant'];
        }
        if (!empty($imageColors['muted'])) {
            $brandColors['image_muted'] = $imageColors['muted'];
        }

        return array_filter($brandColors);
    }

    /**
     * Get tokens formatted for AI system prompt.
     */
    public function getTokensForPrompt(?Brand $brand): string
    {
        $tokens = $this->getTokensForBrand($brand);

        $fontSizes = implode(', ', array_map(
            fn($key, $val) => "{$key}: {$val}px",
            array_keys(self::FONT_SCALE),
            array_values(self::FONT_SCALE)
        ));

        $spacing = implode(', ', self::SPACING_SCALE);
        $cornerRadius = implode(', ', self::CORNER_RADIUS);

        $colors = '';
        foreach ($tokens['colors'] as $name => $hex) {
            $colors .= "- {$name}: {$hex}\n";
        }

        return <<<TOKENS
DESIGN TOKENS (use ONLY these values):

FONT SIZES (modular scale 1.25):
{$fontSizes}

SPACING (8pt scale):
{$spacing} px

CORNER RADIUS:
{$cornerRadius} (500 = pill shape)

BRAND COLORS (use ONLY these):
{$colors}
TOKENS;
    }

    /**
     * Get tokens for prompt with industry-specific fonts.
     */
    public function getTokensForPromptWithIndustry(?Brand $brand, ?string $industry = null): string
    {
        $baseTokens = $this->getTokensForPrompt($brand);
        $fonts = $this->getFontsForIndustry($industry);

        $industryFonts = <<<FONTS

TYPOGRAPHY (industry-optimized):
- Heading font: {$fonts['heading']} ({$fonts['heading_weight']})
- Body font: {$fonts['body']} ({$fonts['body_weight']})
- Scale ratio: {$fonts['scale_ratio']}
FONTS;

        return $baseTokens . $industryFonts;
    }

    /**
     * Get tokens for prompt with image colors.
     */
    public function getTokensForPromptWithImageColors(?Brand $brand, array $imageColors): string
    {
        $tokens = $this->getTokensForBrand($brand);
        $allColors = $this->mergeWithImageColors($tokens['colors'], $imageColors);

        $fontSizes = implode(', ', array_map(
            fn($key, $val) => "{$key}: {$val}px",
            array_keys(self::FONT_SCALE),
            array_values(self::FONT_SCALE)
        ));

        $spacing = implode(', ', self::SPACING_SCALE);
        $cornerRadius = implode(', ', self::CORNER_RADIUS);

        $colors = '';
        foreach ($allColors as $name => $hex) {
            $colors .= "- {$name}: {$hex}\n";
        }

        return <<<TOKENS
DESIGN TOKENS (use ONLY these values):

FONT SIZES (modular scale 1.25):
{$fontSizes}

SPACING (8pt scale):
{$spacing} px

CORNER RADIUS:
{$cornerRadius} (500 = pill shape)

COLORS (brand + image-extracted):
{$colors}
NOTE: image_accent_* and image_vibrant colors are extracted from the photo and harmonize well with it.
TOKENS;
    }

    /**
     * Calculate optimal line height snapped to baseline grid.
     */
    public function calculateLineHeight(int $fontSize, string $context = 'body_normal'): float
    {
        $multiplier = self::LINE_HEIGHT_SCALE[$context] ?? 1.5;
        $rawLineHeight = $fontSize * $multiplier;

        // Snap to baseline grid (round up to nearest BASELINE_UNIT)
        $snappedLineHeight = ceil($rawLineHeight / self::BASELINE_UNIT) * self::BASELINE_UNIT;

        // Return as multiplier relative to font size
        return round($snappedLineHeight / $fontSize, 3);
    }

    /**
     * Calculate tracking (letter-spacing in em) for font size.
     */
    public function calculateTracking(int $fontSize): float
    {
        $scaleKey = $this->getFontScaleKey($fontSize);
        return self::TRACKING_SCALE[$scaleKey] ?? 0.0;
    }

    /**
     * Get scale key for font size.
     */
    public function getFontScaleKey(int $fontSize): string
    {
        $scaleValues = array_values(self::FONT_SCALE);
        $scaleKeys = array_keys(self::FONT_SCALE);

        // Find the closest scale key
        $closestIndex = 0;
        $minDiff = PHP_INT_MAX;

        foreach ($scaleValues as $index => $scaleSize) {
            $diff = abs($scaleSize - $fontSize);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestIndex = $index;
            }
        }

        return $scaleKeys[$closestIndex];
    }

    /**
     * Get optimal line height context based on layer type/name.
     */
    public function getLineHeightContext(string $layerName, string $layerType): string
    {
        $nameLower = strtolower($layerName);

        // CTA buttons use tight
        if ($layerType === 'textbox' || str_contains($nameLower, 'cta') || str_contains($nameLower, 'button')) {
            return 'headline_tight';
        }

        // Subtitles/subtext use body tight (check before 'title' to avoid false matches)
        if (str_contains($nameLower, 'subtext') || str_contains($nameLower, 'subtitle') || str_contains($nameLower, 'sub_')) {
            return 'body_tight';
        }

        // Headlines use normal line height
        if (str_contains($nameLower, 'headline') || str_contains($nameLower, 'title')) {
            return 'headline_normal';
        }

        return 'body_normal';
    }

    /**
     * Apply vertical rhythm to text layer.
     */
    public function applyVerticalRhythm(array $layer): array
    {
        $type = $layer['type'] ?? '';

        if (!in_array($type, ['text', 'textbox'])) {
            return $layer;
        }

        $fontSize = $layer['properties']['fontSize'] ?? 16;
        $name = $layer['name'] ?? '';

        // Calculate optimal line height if not set
        if (!isset($layer['properties']['lineHeight']) || $layer['properties']['lineHeight'] === 1.2) {
            $context = $this->getLineHeightContext($name, $type);
            $layer['properties']['lineHeight'] = $this->calculateLineHeight($fontSize, $context);
        }

        // Calculate tracking if not set
        if (!isset($layer['properties']['letterSpacing']) || $layer['properties']['letterSpacing'] === 0) {
            $tracking = $this->calculateTracking($fontSize);
            // Convert em to pixels for the layer (approximate)
            $layer['properties']['letterSpacing'] = round($tracking * $fontSize, 1);
        }

        return $layer;
    }

    /**
     * Apply vertical rhythm to all layers.
     */
    public function applyVerticalRhythmToLayers(array $layers): array
    {
        return array_map([$this, 'applyVerticalRhythm'], $layers);
    }

    /**
     * Generate a subtext color (30% lighter tint) from background color.
     * This creates color harmony instead of using generic #CCCCCC.
     *
     * @param string $backgroundColor Hex color of the background
     * @return string Hex color for subtext
     */
    public function getSubtextColor(string $backgroundColor): string
    {
        // Convert hex to RGB
        $hex = ltrim($backgroundColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate luminance to decide if we need tint or shade
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        if ($luminance < 0.5) {
            // Dark background - create a light tint (30% toward white)
            $factor = 0.7; // Mix 70% white
            $r = (int) ($r + ($factor * (255 - $r)));
            $g = (int) ($g + ($factor * (255 - $g)));
            $b = (int) ($b + ($factor * (255 - $b)));
        } else {
            // Light background - create a shade (30% toward black)
            $factor = 0.7; // Keep 70% of original
            $r = (int) ($r * $factor);
            $g = (int) ($g * $factor);
            $b = (int) ($b * $factor);
        }

        // Clamp values
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * Get harmonious subtext color for gradient overlays.
     * Returns rgba format for gradient stops.
     *
     * @param string $backgroundColor Hex color of the background
     * @param float $opacity Opacity for the rgba color (0-1)
     * @return string rgba() color string
     */
    public function getOverlayColor(string $backgroundColor, float $opacity = 0.8): string
    {
        $hex = ltrim($backgroundColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r},{$g},{$b},{$opacity})";
    }
}
