<?php

namespace App\Services\AI;

use App\Services\AI\DesignTokensService;
use Illuminate\Support\Facades\Log;

class TemplateValidator
{
    /**
     * Fonts that should be replaced with modern alternatives.
     */
    protected array $forbiddenFonts = [
        'Arial',
        'Helvetica',
        'Times New Roman',
        'Times',
        'Courier',
        'Courier New',
    ];

    /**
     * Default replacement font.
     */
    protected string $defaultFont = 'Montserrat';

    /**
     * Keywords that indicate a CTA button layer.
     */
    protected array $ctaKeywords = [
        'cta',
        'button',
        'przycisk',
        'action',
        'call to action',
        'zamów',
        'kup',
        'sprawdź',
        'dowiedz',
        'zapisz',
        'dołącz',
        'rozpocznij',
        'pobierz',
        'zobacz',
        'read more',
        'learn more',
        'shop now',
        'buy now',
        'get started',
        'link in bio',
    ];

    /**
     * Validate and auto-fix template layers.
     */
    public function validateAndFix(array $layers, int $templateWidth): array
    {
        $fixedLayers = [];
        $fixes = [];
        $removedLayers = [];

        foreach ($layers as $layer) {
            $originalLayer = $layer;
            $layerType = $layer['type'] ?? '';
            $layerName = strtolower($layer['name'] ?? '');

            // BLOCK: Remove ellipses that look like decorative blobs
            if ($layerType === 'ellipse' && $this->isDecorativeBlob($layer, $templateWidth)) {
                $removedLayers[] = [
                    'layer' => $layer['name'] ?? 'unknown',
                    'reason' => 'decorative_blob_removed',
                ];
                continue; // Skip this layer entirely
            }

            // Fix CTA button styling (pill shape, proper size)
            if ($this->isCtaButton($layer)) {
                $layer = $this->fixCtaButton($layer, $templateWidth);
                if ($layer !== $originalLayer) {
                    $fixes[] = [
                        'layer' => $layer['name'] ?? 'unknown',
                        'fix' => 'cta_style',
                    ];
                }
            }

            // Fix forbidden fonts
            $layer = $this->fixForbiddenFonts($layer);
            if (($layer['properties']['fontFamily'] ?? null) !== ($originalLayer['properties']['fontFamily'] ?? null)) {
                $fixes[] = [
                    'layer' => $layer['name'] ?? 'unknown',
                    'fix' => 'font_family',
                    'old_font' => $originalLayer['properties']['fontFamily'] ?? 'unknown',
                    'new_font' => $layer['properties']['fontFamily'],
                ];
            }

            // Note: We now allow dark backgrounds and gradients for intentional dark themes
            // Only convert gradients on small decorative elements, not main backgrounds

            $fixedLayers[] = $layer;
        }

        if (! empty($fixes) || ! empty($removedLayers)) {
            Log::channel('single')->info('TemplateValidator auto-fixes applied', [
                'fixes_count' => count($fixes),
                'fixes' => $fixes,
                'removed_count' => count($removedLayers),
                'removed' => $removedLayers,
            ]);
        }

        return $fixedLayers;
    }

    /**
     * Check if an ellipse is a decorative blob (corner decoration).
     */
    protected function isDecorativeBlob(array $layer, int $templateWidth): bool
    {
        $x = $layer['x'] ?? 0;
        $y = $layer['y'] ?? 0;
        $width = $layer['width'] ?? 0;
        $height = $layer['height'] ?? 0;
        $name = strtolower($layer['name'] ?? '');

        // If it's named something like "blob", "accent", "decoration" - it's decorative
        $decorativeNames = ['blob', 'accent', 'decoration', 'shape', 'circle', 'background'];
        foreach ($decorativeNames as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        // If it's positioned in corners (within 20% of edges) and large - it's a corner blob
        $cornerThreshold = $templateWidth * 0.2;
        $isInCorner = ($x < $cornerThreshold || $x > $templateWidth - $cornerThreshold - $width);
        $isLarge = ($width > 150 || $height > 150);

        if ($isInCorner && $isLarge) {
            return true;
        }

        // If opacity is low (decorative) - it's decorative
        $opacity = $layer['properties']['opacity'] ?? 1;
        if ($opacity < 0.5 && $isLarge) {
            return true;
        }

        return false;
    }

    /**
     * Check if layer has gradient fill.
     */
    protected function hasGradient(array $layer): bool
    {
        $fillType = $layer['properties']['fillType'] ?? 'solid';
        return $fillType === 'gradient';
    }

    /**
     * Convert gradient to solid color.
     */
    protected function convertGradientToSolid(array $layer): array
    {
        // Use the start color or default to white
        $solidColor = $layer['properties']['gradientStartColor'] ?? '#FFFFFF';

        $layer['properties']['fillType'] = 'solid';
        $layer['properties']['fill'] = $solidColor;
        unset($layer['properties']['gradientStartColor']);
        unset($layer['properties']['gradientEndColor']);
        unset($layer['properties']['gradientAngle']);

        return $layer;
    }

    /**
     * Check if a layer is a CTA button.
     */
    protected function isCtaButton(array $layer): bool
    {
        $name = strtolower($layer['name'] ?? '');
        $type = $layer['type'] ?? '';
        $text = strtolower($layer['properties']['text'] ?? '');

        // Must be a rectangle or textbox type
        if (! in_array($type, ['rectangle', 'textbox'])) {
            return false;
        }

        // Check name and text for CTA keywords
        foreach ($this->ctaKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fix CTA button styling - make it pill-shaped with proper size.
     */
    protected function fixCtaButton(array $layer, int $templateWidth): array
    {
        $currentWidth = $layer['width'] ?? 0;
        $currentHeight = $layer['height'] ?? 0;

        // CTA buttons should be compact (180-280px), not full width
        $maxWidth = 280;
        $minWidth = 160;
        $idealHeight = 50;

        // If button is too wide (more than 30% of template), make it compact
        if ($currentWidth > $templateWidth * 0.3) {
            $layer['width'] = 220;
        } elseif ($currentWidth < $minWidth) {
            $layer['width'] = $minWidth;
        }

        // Set proper height
        if ($currentHeight < 40 || $currentHeight > 70) {
            $layer['height'] = $idealHeight;
        }

        // Ensure pill shape (high corner radius)
        if (! isset($layer['properties']['cornerRadius']) || $layer['properties']['cornerRadius'] < 20) {
            $layer['properties']['cornerRadius'] = 25;
        }

        return $layer;
    }

    /**
     * Replace forbidden fonts with modern alternatives.
     */
    protected function fixForbiddenFonts(array $layer): array
    {
        if (! isset($layer['properties']['fontFamily'])) {
            return $layer;
        }

        $currentFont = $layer['properties']['fontFamily'];

        if (in_array($currentFont, $this->forbiddenFonts)) {
            $layer['properties']['fontFamily'] = $this->defaultFont;
        }

        return $layer;
    }

    /**
     * Validate a single layer without auto-fixing (returns issues).
     */
    public function validateLayer(array $layer, int $templateWidth): array
    {
        $issues = [];

        // Check for decorative blobs
        if (($layer['type'] ?? '') === 'ellipse' && $this->isDecorativeBlob($layer, $templateWidth)) {
            $issues[] = [
                'type' => 'decorative_blob',
                'message' => 'Decorative ellipse/blob detected - should be removed',
            ];
        }

        // Check for forbidden fonts
        $currentFont = $layer['properties']['fontFamily'] ?? null;
        if ($currentFont && in_array($currentFont, $this->forbiddenFonts)) {
            $issues[] = [
                'type' => 'forbidden_font',
                'message' => "Font '{$currentFont}' is not allowed, use Google Fonts instead",
                'current' => $currentFont,
                'suggested' => $this->defaultFont,
            ];
        }

        return $issues;
    }

    /**
     * Get all validation issues for a template.
     */
    public function getValidationIssues(array $layers, int $templateWidth): array
    {
        $allIssues = [];

        foreach ($layers as $layer) {
            $layerIssues = $this->validateLayer($layer, $templateWidth);
            if (! empty($layerIssues)) {
                $allIssues[$layer['name'] ?? 'unknown'] = $layerIssues;
            }
        }

        return $allIssues;
    }

    /**
     * Check if template has all required elements.
     * Returns array of missing elements.
     */
    public function checkCompleteness(array $layers, int $templateWidth, int $templateHeight): array
    {
        $missing = [];

        $hasHeadline = false;
        $hasCta = false;
        $hasPhoto = false;
        $hasSubtext = false;
        $hasBackground = false;
        $hasAccent = false;

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');
            $text = strtolower($layer['properties']['text'] ?? '');
            $fontSize = $layer['properties']['fontSize'] ?? 0;
            $width = $layer['width'] ?? 0;
            $height = $layer['height'] ?? 0;

            // Check for background (full-size rectangle)
            if ($type === 'rectangle') {
                $isFullSize = ($width >= $templateWidth * 0.9 && $height >= $templateHeight * 0.9);
                if ($isFullSize || str_contains($name, 'background') || str_contains($name, 'bg')) {
                    $hasBackground = true;
                }
            }

            // Check for headline (large text, 36px+)
            if (in_array($type, ['text', 'textbox']) && $fontSize >= 36) {
                $hasHeadline = true;
            }
            if (str_contains($name, 'headline') || str_contains($name, 'title') || str_contains($name, 'header')) {
                $hasHeadline = true;
            }

            // Check for CTA
            if ($this->isCtaButton($layer)) {
                $hasCta = true;
            }
            if (str_contains($name, 'cta') || str_contains($name, 'button')) {
                $hasCta = true;
            }

            // Check for photo/image
            if ($type === 'image') {
                $hasPhoto = true;
            }

            // Check for subtext/body text
            if (in_array($type, ['text', 'textbox']) && $fontSize < 36 && $fontSize > 0) {
                // Not a headline, not super tiny
                if (!$this->isCtaButton($layer)) {
                    $hasSubtext = true;
                }
            }

            // Check for accent shapes (lines or small rectangles)
            if ($type === 'line') {
                $hasAccent = true;
            }
            if ($type === 'rectangle' && !$hasBackground) {
                // Small rectangles are accents
                $isSmall = ($width < $templateWidth * 0.5 && $height < $templateHeight * 0.3);
                if ($isSmall || str_contains($name, 'accent') || str_contains($name, 'line') || str_contains($name, 'decoration')) {
                    $hasAccent = true;
                }
            }
        }

        if (!$hasHeadline) {
            $missing[] = 'headline';
        }
        if (!$hasCta) {
            $missing[] = 'cta_button';
        }
        if (!$hasPhoto) {
            $missing[] = 'photo';
        }
        if (!$hasSubtext) {
            $missing[] = 'subtext';
        }
        if (!$hasAccent) {
            $missing[] = 'accent';
        }

        Log::channel('single')->info('Template completeness check', [
            'has_headline' => $hasHeadline,
            'has_cta' => $hasCta,
            'has_photo' => $hasPhoto,
            'has_subtext' => $hasSubtext,
            'has_background' => $hasBackground,
            'has_accent' => $hasAccent,
            'missing' => $missing,
            'layers_count' => count($layers),
        ]);

        return $missing;
    }

    /**
     * Add missing required elements to template.
     * Returns the layers array with added elements.
     */
    public function addMissingElements(array $layers, array $missing, int $templateWidth, int $templateHeight, ?array $designPlan = null): array
    {
        $headline = $designPlan['headline'] ?? 'Twój Nagłówek Tutaj';
        $subtext = $designPlan['subtext'] ?? 'Twój opis lub tagline';
        $ctaText = $designPlan['cta_text'] ?? 'Sprawdź Teraz';

        foreach ($missing as $element) {
            if ($element === 'headline') {
                Log::channel('single')->warning('Auto-adding missing headline');
                $layers[] = [
                    'name' => 'Auto_Headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => $templateHeight * 0.35,
                    'width' => $templateWidth - 80,
                    'height' => 120,
                    'properties' => [
                        'text' => $headline,
                        'fontFamily' => 'Montserrat',
                        'fontSize' => 48,
                        'fontWeight' => 'bold',
                        'fill' => '#FFFFFF',
                        'align' => 'center',
                        'textTransform' => 'uppercase',
                    ],
                ];
            }

            if ($element === 'cta_button') {
                Log::channel('single')->warning('Auto-adding missing CTA button');
                $layers[] = [
                    'name' => 'Auto_CTA_Button',
                    'type' => 'textbox',
                    'x' => ($templateWidth - 220) / 2,
                    'y' => $templateHeight - 120,
                    'width' => 220,
                    'height' => 50,
                    'properties' => [
                        'text' => $ctaText,
                        'fontFamily' => 'Montserrat',
                        'fontSize' => 16,
                        'fontWeight' => '600',
                        'fill' => '#D4AF37',
                        'textColor' => '#FFFFFF',
                        'align' => 'center',
                        'padding' => 16,
                        'cornerRadius' => 25,
                    ],
                ];
            }

            if ($element === 'photo') {
                Log::channel('single')->warning('Auto-adding missing photo placeholder');
                $layers[] = [
                    'name' => 'Auto_Photo_Placeholder',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => $templateWidth,
                    'height' => $templateHeight * 0.5,
                    'properties' => [
                        'fill' => '#2A2A2A',
                        'cornerRadius' => 0,
                    ],
                ];
            }

            if ($element === 'subtext') {
                Log::channel('single')->warning('Auto-adding missing subtext');
                $layers[] = [
                    'name' => 'Auto_Subtext',
                    'type' => 'text',
                    'x' => 40,
                    'y' => $templateHeight * 0.55,
                    'width' => $templateWidth - 80,
                    'height' => 60,
                    'properties' => [
                        'text' => $subtext,
                        'fontFamily' => 'Montserrat',
                        'fontSize' => 18,
                        'fontWeight' => 'normal',
                        'fill' => '#CCCCCC',
                        'align' => 'center',
                    ],
                ];
            }

            if ($element === 'accent') {
                // Find headline position to place accent line ABOVE it (decorative element)
                $headlineY = null;
                $headlineHeight = 50;
                $headlineX = $templateWidth / 2;
                $headlineWidth = 400;
                foreach ($layers as $layer) {
                    $layerName = strtolower($layer['name'] ?? '');
                    if (str_contains($layerName, 'headline') || str_contains($layerName, 'title')) {
                        $headlineY = $layer['y'] ?? null;
                        $headlineHeight = $layer['height'] ?? 50;
                        $headlineX = $layer['x'] ?? ($templateWidth / 2);
                        $headlineWidth = $layer['width'] ?? 400;
                        break;
                    }
                }

                // Position accent line: 24px ABOVE headline (as decorative element above text)
                // This prevents the line from cutting through subtext/CTA below
                $accentLineHeight = 4;
                $accentY = $headlineY !== null
                    ? max(40, $headlineY - 24 - $accentLineHeight)  // 24px gap above headline
                    : $templateHeight * 0.25;  // Fallback: upper quarter

                // Left-align accent line with headline start
                $accentWidth = 120;
                $accentX = $headlineX;  // Start at same X as headline

                Log::channel('single')->warning('Auto-adding missing accent line', [
                    'positioned_relative_to' => $headlineY !== null ? 'headline (ABOVE)' : 'fallback',
                    'accent_y' => $accentY,
                    'headline_y' => $headlineY,
                    'gap_to_headline' => $headlineY !== null ? $headlineY - $accentY - $accentLineHeight : null,
                ]);

                $layers[] = [
                    'name' => 'Auto_Accent_Line',
                    'type' => 'line',
                    'x' => (int)$accentX,
                    'y' => (int)$accentY,
                    'width' => $accentWidth,
                    'height' => 4,
                    'properties' => [
                        'points' => [0, 0, 120, 0],
                        'stroke' => '#D4AF37',
                        'strokeWidth' => 3,
                        'lineCap' => 'round',
                    ],
                ];
            }
        }

        return $layers;
    }

    /**
     * Sort layers to ensure correct z-order for Instagram graphics.
     *
     * Order (bottom to top):
     * 0. Background
     * 1. Photo/Image
     * 2. Gradient overlays (for text readability)
     * 3. Accent/decorative elements
     * 4. Text protection overlays (overlay_for_*)
     * 5. Text layers (subtext, headline) - MUST be above overlays
     * 6. CTA button - ALWAYS on top
     */
    public function sortLayersByZOrder(array $layers): array
    {
        usort($layers, function ($a, $b) {
            $priorityA = $this->getLayerZPriority($a);
            $priorityB = $this->getLayerZPriority($b);

            return $priorityA <=> $priorityB;
        });

        Log::channel('single')->debug('TemplateValidator: Z-order sorted', [
            'order' => array_map(fn($l) => $l['name'] ?? 'unknown', $layers),
        ]);

        return $layers;
    }

    /**
     * Get z-index priority for a layer.
     * Lower = behind, Higher = in front
     */
    protected function getLayerZPriority(array $layer): int
    {
        $name = strtolower($layer['name'] ?? '');
        $type = strtolower($layer['type'] ?? '');

        // 0. Background - always at bottom
        if (str_contains($name, 'background') || str_contains($name, 'bg')) {
            return 0;
        }

        // 1. Photo/Image - just above background
        if ($type === 'image' || str_contains($name, 'photo')) {
            return 10;
        }

        // 2. Gradient overlays - right after photo for readability
        if (str_contains($name, 'gradient_overlay') || str_contains($name, 'gradient')) {
            return 20;
        }

        // 3. Accent elements, lines, shapes
        if ($type === 'line' || str_contains($name, 'accent') || str_contains($name, 'decoration')) {
            return 30;
        }

        // 4. Text protection overlays (overlay_for_*)
        if (str_contains($name, 'overlay_for_') || str_contains($name, 'text_overlay')) {
            return 40;
        }

        // 4.5. Generic overlays (NOT gradient, NOT for text) - SKIP these if gradient exists
        if (str_contains($name, 'overlay') && $type === 'rectangle') {
            return 25; // Between gradient and accent
        }

        // 5. Text layers - MUST be above all overlays
        if ($type === 'text') {
            // Subtext slightly lower than headline
            if (str_contains($name, 'subtext') || str_contains($name, 'sub')) {
                return 50;
            }
            // Headline higher
            if (str_contains($name, 'headline') || str_contains($name, 'title')) {
                return 55;
            }
            return 52; // Generic text
        }

        // 6. CTA/Button - ALWAYS on top (textbox type)
        if ($type === 'textbox' || str_contains($name, 'cta') || str_contains($name, 'button')) {
            return 100;
        }

        // Default - middle layer
        return 35;
    }

    /**
     * Snap layer properties to design tokens using the DesignTokensService.
     * This is a convenience method that integrates with the design system.
     */
    public function snapToDesignTokens(array $layers, DesignTokensService $tokens): array
    {
        return $tokens->snapAllLayersToTokens($layers);
    }
}
