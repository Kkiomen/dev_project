<?php

namespace App\Services\AI;

use App\Services\OpenAiClientService;
use Illuminate\Support\Facades\Log;

/**
 * Self-Correction Service.
 *
 * Reviews generated templates and suggests/applies corrections
 * for common design issues.
 */
class SelfCorrectionService
{
    public function __construct(
        protected OpenAiClientService $openAiClient,
        protected ContrastValidator $contrastValidator,
        protected TypographyHierarchyValidator $typographyValidator,
        protected GridSnapService $gridSnapService,
        protected DesignTokensService $designTokensService,
        protected ?TextOverlayService $textOverlayService = null,
        protected ?TextPositioningService $textPositioningService = null,
        protected ?TextOptimizationService $textOptimizationService = null,
        protected ?ElevationService $elevationService = null
    ) {
        $this->textOverlayService = $textOverlayService ?? new TextOverlayService();
        $this->textPositioningService = $textPositioningService ?? new TextPositioningService();
        $this->textOptimizationService = $textOptimizationService ?? new TextOptimizationService();
        $this->elevationService = $elevationService ?? new ElevationService();
    }

    /**
     * Review and correct a template layout.
     */
    public function reviewAndCorrect(array $layers, array $imageAnalysis, int $templateWidth = 1080, int $templateHeight = 1080): array
    {
        $corrections = [];
        $correctedLayers = $layers;

        // Step 0: FIRST calculate proper text layer heights based on content
        // This MUST happen before text positioning to avoid overlaps
        Log::channel('single')->info('SelfCorrection: Step 0 - Calculating text layer heights');
        $correctedLayers = $this->textOptimizationService->optimizeTextLayers($correctedLayers);
        $corrections[] = ['type' => 'text_height_calculation', 'applied' => true];

        // Step 1: Fix text positioning (avoid overlapping text) - now uses correct heights
        $correctedLayers = $this->textPositioningService->fixTextPositioning(
            $correctedLayers,
            $templateWidth,
            $templateHeight
        );
        $corrections[] = ['type' => 'text_positioning', 'applied' => true];

        // Step 2: Check for text overlapping focal point
        if (!empty($imageAnalysis['busy_zones'])) {
            $result = $this->fixTextOverlap($correctedLayers, $imageAnalysis);
            $correctedLayers = $result['layers'];
            $corrections = array_merge($corrections, $result['corrections']);
        }

        // Step 3: Add gradient overlay for modern Instagram look
        // NOTE: We skip solid overlays (addTextOverlays) - gradient overlays are preferred
        // Solid white/black overlays wash out photos, gradients are more elegant
        Log::channel('single')->info('SelfCorrection: Step 3 - Adding automatic gradient overlay (skipping solid overlays)');
        $correctedLayers = $this->textOverlayService->addAutomaticGradientOverlay(
            $correctedLayers,
            $templateWidth,
            $templateHeight
        );
        $corrections[] = ['type' => 'gradient_overlay', 'applied' => true];

        // Step 3.5: Remove any existing solid overlays (white/black rectangles over photos)
        // This removes old-style overlays that wash out photo colors
        $correctedLayers = $this->removeSolidOverlays($correctedLayers);
        $corrections[] = ['type' => 'solid_overlays_removed', 'applied' => true];

        // Step 4: Fix typography hierarchy
        $hierarchyIssues = $this->typographyValidator->validateHierarchy($correctedLayers);
        if (!empty($hierarchyIssues)) {
            $correctedLayers = $this->typographyValidator->fixHierarchy($correctedLayers);
            $corrections[] = ['type' => 'typography_hierarchy', 'issues_fixed' => count($hierarchyIssues)];
        }

        // Step 4.5: Balance visual weight - boost subtext if headline dominates too much
        Log::channel('single')->info('SelfCorrection: Step 4.5 - Balancing visual weight');
        $weightResult = $this->balanceVisualWeight($correctedLayers);
        $correctedLayers = $weightResult['layers'];
        if (!empty($weightResult['corrections'])) {
            $corrections = array_merge($corrections, $weightResult['corrections']);
        }

        // Step 5: Fix contrast issues
        $backgroundColor = $this->getTemplateBackgroundColor($correctedLayers);
        $contrastIssues = $this->contrastValidator->validateLayers($correctedLayers, $backgroundColor);
        if (!empty($contrastIssues)) {
            $correctedLayers = $this->contrastValidator->fixContrastIssues($correctedLayers, $backgroundColor);
            $corrections[] = ['type' => 'contrast', 'issues_fixed' => count($contrastIssues)];
        }

        // Step 6: Snap to grid
        $correctedLayers = $this->gridSnapService->snapAllLayers($correctedLayers);

        // Step 7: Snap to design tokens
        $correctedLayers = $this->designTokensService->snapAllLayersToTokens($correctedLayers);

        // Step 8: Ensure margins are consistent (using 80% canvas rule)
        Log::channel('single')->info('SelfCorrection: Step 8 - Fixing margins (80% canvas rule)');
        $result = $this->fixMargins($correctedLayers, $templateWidth, $templateHeight);
        $correctedLayers = $result['layers'];
        $corrections = array_merge($corrections, $result['corrections']);

        // Step 9: Ensure CTA is prominent and present
        $result = $this->ensureCtaProminent($correctedLayers, $templateWidth, $templateHeight);
        $correctedLayers = $result['layers'];
        $corrections = array_merge($corrections, $result['corrections']);

        // Step 10: Final CTA enforcement - if still no CTA, add one
        if (!$this->hasCta($correctedLayers)) {
            $correctedLayers = $this->addMissingCta($correctedLayers, $templateWidth, $templateHeight);
            $corrections[] = ['type' => 'cta_added', 'reason' => 'missing'];
        }

        // Step 10.5: Apply soft glow shadows to CTA buttons (modern Instagram style)
        Log::channel('single')->info('SelfCorrection: Step 10.5 - Applying soft glow shadows to CTA');
        $correctedLayers = $this->elevationService->applySoftGlowToCtaLayers($correctedLayers);
        $corrections[] = ['type' => 'soft_glow_applied', 'applied' => true];

        // Step 11: Final text positioning check after all modifications
        // Run again to ensure no overlaps after all the size/position changes
        Log::channel('single')->info('SelfCorrection: Step 11 - Final text positioning check');
        $correctedLayers = $this->textPositioningService->fixTextPositioning(
            $correctedLayers,
            $templateWidth,
            $templateHeight
        );
        $corrections[] = ['type' => 'final_text_positioning', 'applied' => true];

        // Step 12: HARD MARGIN CONSTRAINTS - Final enforcement
        // Text can NEVER escape below safe margins, regardless of other corrections
        Log::channel('single')->info('SelfCorrection: Step 12 - Hard margin enforcement');
        $marginResult = $this->enforceHardMargins($correctedLayers, $templateWidth, $templateHeight);
        $correctedLayers = $marginResult['layers'];
        if (!empty($marginResult['corrections'])) {
            $corrections = array_merge($corrections, $marginResult['corrections']);
        }

        if (!empty($corrections)) {
            Log::channel('single')->info('Self-correction applied', [
                'corrections_count' => count($corrections),
                'corrections' => $corrections,
            ]);
        }

        return [
            'layers' => $correctedLayers,
            'corrections' => $corrections,
            'corrections_applied' => count($corrections) > 0,
        ];
    }

    /**
     * Check if template has a CTA button.
     */
    protected function hasCta(array $layers): bool
    {
        foreach ($layers as $layer) {
            if ($this->isCtaButton($layer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Instagram CTA minimum dimensions.
     * CTA buttons on social graphics need to be prominent and tappable.
     * Font size 39px is from Major Third modular scale (16 * 1.25^5).
     *
     * Height is calculated dynamically: fontSize * paddingMultiplier * 2 + fontSize
     * This prevents giant empty buttons when font is small.
     */
    protected const CTA_MIN_WIDTH = 280;
    protected const CTA_FONT_SIZE = 20; // Modular scale 'md' - smaller for 70-20-10 balance
    protected const CTA_PADDING_MULTIPLIER = 1.0; // paddingY = fontSize * 1.0 (more compact button)

    /**
     * Add missing CTA button.
     */

    protected function addMissingCta(array $layers, int $templateWidth, int $templateHeight): array
    {
        // Calculate proportional dimensions
        $fontSize = self::CTA_FONT_SIZE;
        $paddingY = (int)($fontSize * self::CTA_PADDING_MULTIPLIER);
        $proportionalHeight = $paddingY * 2 + $fontSize;

        Log::channel('single')->warning('Adding missing CTA button', [
            'proportional_height' => $proportionalHeight,
            'min_width' => self::CTA_MIN_WIDTH,
            'font_size' => $fontSize,
            'padding' => $paddingY,
        ]);

        $layers[] = [
            'name' => 'cta_button',
            'type' => 'textbox',
            'x' => (int) (($templateWidth - self::CTA_MIN_WIDTH) / 2),
            'y' => $templateHeight - $proportionalHeight - 40, // 40px from bottom edge
            'width' => self::CTA_MIN_WIDTH,
            'height' => $proportionalHeight,
            'properties' => [
                'text' => 'Sprawdź Teraz',
                'fontFamily' => 'Montserrat',
                'fontSize' => $fontSize,
                'fontWeight' => '600',
                'fill' => '#D4AF37',
                'textColor' => '#FFFFFF',
                'align' => 'center',
                'padding' => $paddingY,
                'cornerRadius' => 500, // Pill shape
            ],
        ];

        return $layers;
    }

    /**
     * Fix text overlapping with focal point.
     *
     * IMPORTANT: Only moves text that is ACTUALLY on the photo area.
     * Text in designated text zones (below/beside photo) should NOT be moved.
     *
     * FULL OVERLAY MODE: When busy_zone covers >70% of image, don't move text.
     * Instead, rely on gradient overlay for contrast - there's no "safe" zone anyway.
     */
    protected function fixTextOverlap(array $layers, array $imageAnalysis): array
    {
        $corrections = [];
        $busyZones = $imageAnalysis['busy_zones'] ?? [];
        $safeZones = $imageAnalysis['safe_zones'] ?? [];

        if (empty($busyZones)) {
            return ['layers' => $layers, 'corrections' => $corrections];
        }

        // Find photo layer to determine photo area
        $photoLayer = null;
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');
            if ($type === 'image' || str_contains($name, 'photo')) {
                $photoLayer = $layer;
                break;
            }
        }

        // If no photo, no need to fix overlaps (text isn't on photo)
        if (!$photoLayer) {
            Log::channel('single')->info('SelfCorrection: No photo layer found, skipping text overlap fix');
            return ['layers' => $layers, 'corrections' => $corrections];
        }

        // FULL OVERLAY MODE CHECK: If busy_zone covers >70% of photo, don't move text
        // The image is too "busy" everywhere - rely on overlay for contrast instead
        $photoW = $photoLayer['width'] ?? 1080;
        $photoH = $photoLayer['height'] ?? 1080;
        $photoArea = $photoW * $photoH;

        $totalBusyArea = 0;
        foreach ($busyZones as $zone) {
            $zoneArea = ($zone['width'] ?? 0) * ($zone['height'] ?? 0);
            $totalBusyArea += $zoneArea;
        }

        $busyRatio = $photoArea > 0 ? $totalBusyArea / $photoArea : 0;

        if ($busyRatio > 0.7) {
            Log::channel('single')->warning('SelfCorrection: FULL OVERLAY MODE - busy zones cover >70% of photo', [
                'busy_ratio' => round($busyRatio * 100, 1) . '%',
                'action' => 'Keeping text centered, relying on gradient overlay for contrast',
            ]);

            $corrections[] = [
                'type' => 'full_overlay_mode',
                'busy_ratio' => $busyRatio,
                'reason' => 'No safe zone available - entire image is busy',
            ];

            return ['layers' => $layers, 'corrections' => $corrections];
        }

        $photoX = $photoLayer['x'] ?? 0;
        $photoY = $photoLayer['y'] ?? 0;
        $photoW = $photoLayer['width'] ?? 1080;
        $photoH = $photoLayer['height'] ?? 1080;

        // Scale busy zones to photo area (they come as full-image coordinates)
        $scaledBusyZones = $this->scaleBusyZonesToPhoto($busyZones, $photoLayer);

        Log::channel('single')->info('SelfCorrection: Photo area and scaled busy zones', [
            'photo' => ['x' => $photoX, 'y' => $photoY, 'w' => $photoW, 'h' => $photoH],
            'original_busy_zones' => $busyZones,
            'scaled_busy_zones' => $scaledBusyZones,
        ]);

        $fixedLayers = [];

        // Track Y positions used in each safe zone to avoid overlap
        $usedYPositions = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';

            // Only check text layers
            if (!in_array($type, ['text', 'textbox'])) {
                $fixedLayers[] = $layer;
                continue;
            }

            $layerX = $layer['x'] ?? 0;
            $layerY = $layer['y'] ?? 0;
            $layerW = $layer['width'] ?? 200;
            $layerH = $layer['height'] ?? 50;

            // CRITICAL: Check if text is actually ON the photo area
            $onPhoto = $this->rectanglesOverlap(
                $layerX, $layerY, $layerW, $layerH,
                $photoX, $photoY, $photoW, $photoH
            );

            // If text is NOT on photo, don't move it (it's in the text zone)
            if (!$onPhoto) {
                Log::channel('single')->info('SelfCorrection: Layer not on photo, keeping position', [
                    'layer' => $layer['name'] ?? 'unknown',
                    'position' => ['x' => $layerX, 'y' => $layerY],
                    'photo_ends_at_y' => $photoY + $photoH,
                ]);
                $fixedLayers[] = $layer;
                continue;
            }

            // Only check overlap with scaled busy zones (within photo area)
            $overlaps = $this->checkOverlap($layer, $scaledBusyZones);

            if ($overlaps) {
                // Find best safe zone
                $bestZone = $this->findBestSafeZone($layer, $safeZones, $scaledBusyZones);

                if ($bestZone) {
                    $oldY = $layer['y'];
                    $oldX = $layer['x'];
                    $zoneKey = $bestZone['position'] ?? 'default';
                    $layerHeight = $layer['height'] ?? 50;
                    $spacing = 16; // Space between stacked layers

                    // Calculate Y position avoiding overlap with other layers in same zone
                    if (!isset($usedYPositions[$zoneKey])) {
                        // First layer in this zone - place at zone start
                        $layer['y'] = $bestZone['y'];
                        $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
                    } else {
                        // Subsequent layer - stack below previous
                        $layer['y'] = $usedYPositions[$zoneKey];
                        $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
                    }

                    // Center horizontally in safe zone, but ensure layer stays within canvas
                    $layerWidth = $layer['width'] ?? 200;
                    $zoneWidth = $bestZone['width'] ?? 1000;
                    $canvasWidth = 1080; // Standard Instagram canvas
                    $safeMargin = $this->designTokensService->getSafeMargins($canvasWidth, $canvasWidth)['left'];

                    if ($layerWidth <= $zoneWidth) {
                        // Layer fits in zone - center it
                        $layer['x'] = $bestZone['x'] + (int)(($zoneWidth - $layerWidth) / 2);
                    } else {
                        // Layer wider than zone - keep at safe margin from left
                        $layer['x'] = $safeMargin;

                        // If layer is too wide for canvas with margins, reduce width
                        $maxWidth = $canvasWidth - (2 * $safeMargin);
                        if ($layerWidth > $maxWidth) {
                            $layer['width'] = $maxWidth;
                            $layerWidth = $maxWidth;
                            Log::channel('single')->info('SelfCorrection: Reduced layer width to fit canvas', [
                                'layer' => $layer['name'] ?? 'unknown',
                                'new_width' => $maxWidth,
                            ]);
                        }
                    }

                    // CRITICAL: Ensure layer doesn't extend past right edge of canvas
                    $rightEdge = $layer['x'] + $layerWidth;
                    if ($rightEdge > $canvasWidth - $safeMargin) {
                        // Move left to fit within safe margins
                        $layer['x'] = $canvasWidth - $safeMargin - $layerWidth;
                        Log::channel('single')->info('SelfCorrection: Moved layer to prevent right overflow', [
                            'layer' => $layer['name'] ?? 'unknown',
                            'new_x' => $layer['x'],
                        ]);
                    }

                    // Ensure X is never negative and respects left margin
                    $layer['x'] = max($safeMargin, $layer['x']);

                    $corrections[] = [
                        'type' => 'text_overlap',
                        'layer' => $layer['name'] ?? 'unknown',
                        'moved_from' => ['x' => $oldX, 'y' => $oldY],
                        'moved_to' => ['x' => $layer['x'], 'y' => $layer['y']],
                        'moved_to_zone' => $bestZone['position'],
                    ];
                }
            }

            $fixedLayers[] = $layer;
        }

        return ['layers' => $fixedLayers, 'corrections' => $corrections];
    }

    /**
     * Scale busy zones from full-image coordinates to photo layer position.
     */
    protected function scaleBusyZonesToPhoto(array $busyZones, array $photoLayer): array
    {
        $photoX = $photoLayer['x'] ?? 0;
        $photoY = $photoLayer['y'] ?? 0;
        $photoW = $photoLayer['width'] ?? 1080;
        $photoH = $photoLayer['height'] ?? 1080;

        // Assume busy zones come from 1080x1080 analysis
        $analysisSize = 1080;

        $scaled = [];
        foreach ($busyZones as $zone) {
            $zoneX = $zone['x'] ?? 0;
            $zoneY = $zone['y'] ?? 0;
            $zoneW = $zone['width'] ?? 0;
            $zoneH = $zone['height'] ?? 0;

            // Scale to photo dimensions
            $scaledX = $photoX + ($zoneX / $analysisSize) * $photoW;
            $scaledY = $photoY + ($zoneY / $analysisSize) * $photoH;
            $scaledW = ($zoneW / $analysisSize) * $photoW;
            $scaledH = ($zoneH / $analysisSize) * $photoH;

            // Clip to photo boundaries
            $scaledH = min($scaledH, $photoH - ($scaledY - $photoY));

            $scaled[] = [
                'position' => $zone['position'] ?? 'focal',
                'x' => (int) $scaledX,
                'y' => (int) $scaledY,
                'width' => (int) $scaledW,
                'height' => (int) $scaledH,
                'reason' => $zone['reason'] ?? 'Scaled from image analysis',
            ];
        }

        return $scaled;
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
    protected function checkOverlap(array $layer, array $busyZones): bool
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
            if ($lx < $zx + $zw && $lx + $lw > $zx && $ly < $zy + $zh && $ly + $lh > $zy) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the best safe zone for a layer.
     */
    protected function findBestSafeZone(array $layer, array $safeZones, array $busyZones): ?array
    {
        if (empty($safeZones)) {
            return null;
        }

        $layerName = strtolower($layer['name'] ?? '');

        // Headlines prefer top, CTAs prefer bottom
        if (str_contains($layerName, 'headline') || str_contains($layerName, 'title')) {
            // Find a top zone
            foreach ($safeZones as $zone) {
                if (str_contains($zone['position'] ?? '', 'top')) {
                    return $zone;
                }
            }
        }

        if (str_contains($layerName, 'cta') || str_contains($layerName, 'button')) {
            // Find a bottom zone
            foreach ($safeZones as $zone) {
                if (str_contains($zone['position'] ?? '', 'bottom')) {
                    return $zone;
                }
            }
        }

        // Return the zone with lowest brightness (best for white text)
        $bestZone = $safeZones[0];
        $lowestBrightness = $safeZones[0]['brightness'] ?? 1;

        foreach ($safeZones as $zone) {
            $brightness = $zone['brightness'] ?? 1;
            if ($brightness < $lowestBrightness) {
                $lowestBrightness = $brightness;
                $bestZone = $zone;
            }
        }

        return $bestZone;
    }

    /**
     * Fix inconsistent margins using the 80% canvas rule.
     *
     * IMPORTANT: Respects archetype text zones. For split layouts (hero_right, split_content),
     * text positioned at x >= 400 is in a designated "right text zone" and should not be
     * moved to left margin - that would put it ON the photo!
     */
    protected function fixMargins(array $layers, int $templateWidth, int $templateHeight = 1080): array
    {
        $corrections = [];
        // Use dynamic safe margins from design tokens (10% of canvas = 80% usable area)
        $safeMargins = $this->designTokensService->getSafeMargins($templateWidth, $templateHeight);
        $standardMargin = $safeMargins['left']; // All margins are equal in the 80% rule
        $fixedLayers = [];

        Log::channel('single')->debug('SelfCorrection: fixMargins - Using 80% canvas rule', [
            'canvas' => ['width' => $templateWidth, 'height' => $templateHeight],
            'safe_margins' => $safeMargins,
            'standard_margin' => $standardMargin,
        ]);

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Skip full-width elements like backgrounds
            if (str_contains($name, 'background') || str_contains($name, 'bg')) {
                $fixedLayers[] = $layer;
                continue;
            }

            if (str_contains($name, 'photo') || str_contains($name, 'image')) {
                $fixedLayers[] = $layer;
                continue;
            }

            // Skip overlay layers
            if (str_contains($name, 'overlay') || str_contains($name, 'gradient')) {
                $fixedLayers[] = $layer;
                continue;
            }

            $x = $layer['x'] ?? 0;
            $width = $layer['width'] ?? 0;

            // CRITICAL: Detect right text zone (archetype split layouts)
            // Text at x >= 400 is intentionally in the right half (hero_right, split_content)
            // Don't move it to left margin - that would put it on top of the photo!
            $isRightZone = $x >= 400;

            // Check left margin - but respect archetype zones
            if (!$isRightZone && $x > 0 && $x < $standardMargin) {
                $oldX = $x;
                $layer['x'] = $standardMargin;
                $corrections[] = [
                    'type' => 'margin_fix',
                    'layer' => $layer['name'] ?? 'unknown',
                    'side' => 'left',
                    'old_value' => $oldX,
                    'new_value' => $standardMargin,
                ];
            }

            // For right-zone text, ensure there's margin from the right edge of canvas
            // (not from the left edge, which is where the photo is)
            if ($isRightZone) {
                $rightEdge = $x + $width;
                $rightMargin = $templateWidth - $rightEdge;

                if ($rightMargin > 0 && $rightMargin < $standardMargin) {
                    $layer['width'] = $templateWidth - $x - $standardMargin;
                    $corrections[] = [
                        'type' => 'margin_fix',
                        'layer' => $layer['name'] ?? 'unknown',
                        'side' => 'right',
                        'old_margin' => $rightMargin,
                        'new_margin' => $standardMargin,
                        'note' => 'Right-zone text - adjusted width only',
                    ];
                }
            } else {
                // Left-zone text: check right margin normally
                $rightEdge = $x + $width;
                $rightMargin = $templateWidth - $rightEdge;
                if ($rightMargin > 0 && $rightMargin < $standardMargin) {
                    $layer['width'] = $templateWidth - $x - $standardMargin;
                    $corrections[] = [
                        'type' => 'margin_fix',
                        'layer' => $layer['name'] ?? 'unknown',
                        'side' => 'right',
                        'old_margin' => $rightMargin,
                        'new_margin' => $standardMargin,
                    ];
                }
            }

            $fixedLayers[] = $layer;
        }

        if (!empty($corrections)) {
            Log::channel('single')->info('SelfCorrection: Margin corrections applied', [
                'corrections_count' => count($corrections),
                'corrections' => $corrections,
            ]);
        }

        return ['layers' => $fixedLayers, 'corrections' => $corrections];
    }

    /**
     * Ensure CTA button is prominent and properly positioned.
     * Enforces Instagram-friendly minimum dimensions for tap targets.
     */
    protected function ensureCtaProminent(array $layers, int $templateWidth, int $templateHeight): array
    {
        $corrections = [];
        $fixedLayers = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Find CTA button
            if (!$this->isCtaButton($layer)) {
                $fixedLayers[] = $layer;
                continue;
            }

            Log::channel('single')->debug('SelfCorrection: Processing CTA button', [
                'name' => $layer['name'] ?? 'unknown',
                'current_width' => $layer['width'] ?? 0,
                'current_height' => $layer['height'] ?? 0,
                'current_fontSize' => $layer['properties']['fontSize'] ?? 'not set',
            ]);

            // Ensure CTA is in lower portion of template
            $y = $layer['y'] ?? 0;
            if ($y < $templateHeight * 0.6) {
                $layer['y'] = $templateHeight - 120; // 120px from bottom
                $corrections[] = [
                    'type' => 'cta_position',
                    'layer' => $layer['name'] ?? 'unknown',
                    'moved_from_y' => $y,
                    'moved_to_y' => $layer['y'],
                ];
            }

            // Enforce font size for CTA (keep it balanced - not too big!)
            // Target: 10-15% visual weight means CTA should be smaller than subtext
            $fontSize = $layer['properties']['fontSize'] ?? 16;
            $maxCtaFontSize = 25; // Max: 'lg' in modular scale

            if ($fontSize < self::CTA_FONT_SIZE) {
                $oldFontSize = $fontSize;
                $layer['properties']['fontSize'] = self::CTA_FONT_SIZE;
                $fontSize = self::CTA_FONT_SIZE;
                $corrections[] = [
                    'type' => 'cta_fontSize_enforced',
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_fontSize' => $oldFontSize,
                    'new_fontSize' => self::CTA_FONT_SIZE,
                ];
            } elseif ($fontSize > $maxCtaFontSize) {
                // CTA too large - reduces headline visual dominance
                $oldFontSize = $fontSize;
                $layer['properties']['fontSize'] = self::CTA_FONT_SIZE;
                $fontSize = self::CTA_FONT_SIZE;
                $corrections[] = [
                    'type' => 'cta_fontSize_reduced',
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_fontSize' => $oldFontSize,
                    'new_fontSize' => self::CTA_FONT_SIZE,
                    'reason' => 'CTA too large, reducing for 70-20-10 visual weight balance',
                ];
            }

            // Calculate proportional padding based on font size
            $paddingY = (int)($fontSize * self::CTA_PADDING_MULTIPLIER);
            $layer['properties']['padding'] = $paddingY;

            // Calculate proportional height: paddingTop + fontSize + paddingBottom
            $proportionalHeight = $paddingY * 2 + $fontSize;
            $currentHeight = $layer['height'] ?? 0;

            if ($currentHeight < $proportionalHeight || $currentHeight > $proportionalHeight * 1.5) {
                $oldHeight = $layer['height'] ?? 0;
                $layer['height'] = $proportionalHeight;
                $corrections[] = [
                    'type' => 'cta_height_proportional',
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_height' => $oldHeight,
                    'new_height' => $proportionalHeight,
                    'formula' => "padding({$paddingY}) * 2 + fontSize({$fontSize})",
                ];
            }

            // Enforce minimum width
            $width = $layer['width'] ?? 0;
            if ($width < self::CTA_MIN_WIDTH) {
                $oldWidth = $layer['width'] ?? 0;
                $layer['width'] = self::CTA_MIN_WIDTH;
                $corrections[] = [
                    'type' => 'cta_width_enforced',
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_width' => $oldWidth,
                    'new_width' => self::CTA_MIN_WIDTH,
                ];
            }

            // Ensure CTA is centered (recalculate with new width)
            $finalWidth = $layer['width'] ?? self::CTA_MIN_WIDTH;
            $expectedX = (int)(($templateWidth - $finalWidth) / 2);
            $x = $layer['x'] ?? 0;

            if (abs($x - $expectedX) > 50) {
                $layer['x'] = $expectedX;
                $corrections[] = [
                    'type' => 'cta_centered',
                    'layer' => $layer['name'] ?? 'unknown',
                    'moved_from_x' => $x,
                    'moved_to_x' => $layer['x'],
                ];
            }

            Log::channel('single')->info('SelfCorrection: CTA button corrected', [
                'name' => $layer['name'] ?? 'unknown',
                'final_width' => $layer['width'],
                'final_height' => $layer['height'],
                'final_fontSize' => $layer['properties']['fontSize'] ?? 'not set',
            ]);

            $fixedLayers[] = $layer;
        }

        return ['layers' => $fixedLayers, 'corrections' => $corrections];
    }

    /**
     * Check if a layer is a CTA button.
     */
    protected function isCtaButton(array $layer): bool
    {
        $name = strtolower($layer['name'] ?? '');
        $type = $layer['type'] ?? '';
        $text = strtolower($layer['properties']['text'] ?? '');

        $ctaKeywords = ['cta', 'button', 'action'];

        foreach ($ctaKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($text, $keyword)) {
                return true;
            }
        }

        return $type === 'textbox';
    }

    /**
     * Get the template's background color.
     */
    protected function getTemplateBackgroundColor(array $layers): string
    {
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            if ($type === 'rectangle') {
                if (str_contains($name, 'background') || str_contains($name, 'bg')) {
                    return $layer['properties']['fill'] ?? '#FFFFFF';
                }

                // Check if it's a full-size rectangle
                $width = $layer['width'] ?? 0;
                $height = $layer['height'] ?? 0;
                if ($width >= 1000 && $height >= 1000) {
                    return $layer['properties']['fill'] ?? '#FFFFFF';
                }
            }
        }

        return '#FFFFFF';
    }

    /**
     * Count text layers in the template.
     */
    protected function countTextLayers(array $layers): int
    {
        return count(array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox'])));
    }

    /**
     * Remove solid overlays (white/black rectangles) that wash out photos.
     * Keeps gradient overlays which are more elegant.
     */
    protected function removeSolidOverlays(array $layers): array
    {
        $filteredLayers = [];
        $removedCount = 0;

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');
            $properties = $layer['properties'] ?? [];

            // Only check rectangles
            if ($type !== 'rectangle') {
                $filteredLayers[] = $layer;
                continue;
            }

            // Keep background layers
            if (str_contains($name, 'background') || str_contains($name, 'bg')) {
                $filteredLayers[] = $layer;
                continue;
            }

            // Keep gradient overlays (they're the modern approach)
            $fillType = $properties['fillType'] ?? 'solid';
            if ($fillType === 'gradient' || str_contains($name, 'gradient')) {
                $filteredLayers[] = $layer;
                continue;
            }

            // Check if this is a solid overlay that washes out photos
            $opacity = $properties['opacity'] ?? 1;
            $fill = strtoupper($properties['fill'] ?? '');
            $width = $layer['width'] ?? 0;
            $height = $layer['height'] ?? 0;

            // Identify overlays: semi-transparent large rectangles with white/black fill
            $isLargeRectangle = $width > 500 && $height > 200;
            $isSemiTransparent = $opacity < 1 && $opacity > 0;
            $isOverlayColor = in_array($fill, ['#FFFFFF', '#FFF', '#000000', '#000', 'WHITE', 'BLACK']);
            $isNamedOverlay = str_contains($name, 'overlay');

            // Remove if it's a solid overlay washing out the photo
            if (($isLargeRectangle && $isSemiTransparent && $isOverlayColor) || ($isNamedOverlay && $fillType !== 'gradient')) {
                Log::channel('single')->info('SelfCorrection: Removing solid overlay', [
                    'name' => $layer['name'] ?? 'unknown',
                    'fill' => $fill,
                    'opacity' => $opacity,
                    'size' => ['width' => $width, 'height' => $height],
                    'reason' => 'Solid overlays wash out photos - using gradient overlay instead',
                ]);
                $removedCount++;
                continue; // Skip this layer
            }

            $filteredLayers[] = $layer;
        }

        if ($removedCount > 0) {
            Log::channel('single')->info('SelfCorrection: Removed solid overlays', [
                'removed_count' => $removedCount,
            ]);
        }

        return $filteredLayers;
    }

    /**
     * HARD MARGIN ENFORCEMENT - Final step.
     * Text and content layers can NEVER be positioned below safe margins.
     * This runs last and overrides any previous positioning decisions.
     */
    protected function enforceHardMargins(array $layers, int $templateWidth, int $templateHeight): array
    {
        $corrections = [];
        $fixedLayers = [];

        // Get safe margins (80% canvas rule)
        $margins = $this->designTokensService->getSafeMargins($templateWidth, $templateHeight);
        $minX = $margins['left'];
        $minY = $margins['top'];
        $maxX = $templateWidth - $margins['right'];
        $maxY = $templateHeight - $margins['bottom'];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Skip backgrounds, photos, and gradient overlays
            if ($type === 'image' ||
                str_contains($name, 'background') ||
                str_contains($name, 'gradient') ||
                str_contains($name, 'overlay')) {
                $fixedLayers[] = $layer;
                continue;
            }

            // Only enforce on text and textbox (content layers)
            // Allow decorative elements (accent lines, shapes) to break margins for visual interest
            $isDecorative = str_contains($name, 'accent') ||
                            str_contains($name, 'line') ||
                            str_contains($name, 'shape') ||
                            str_contains($name, 'decoration') ||
                            $type === 'line';

            if (!in_array($type, ['text', 'textbox']) || $isDecorative) {
                $fixedLayers[] = $layer;
                continue;
            }

            $x = $layer['x'] ?? 0;
            $y = $layer['y'] ?? 0;
            $width = $layer['width'] ?? 200;
            $height = $layer['height'] ?? 50;
            $wasFixed = false;
            $oldX = $x;
            $oldY = $y;

            // HARD CONSTRAINT: X position must be >= minX
            if ($x < $minX) {
                $layer['x'] = $minX;
                $wasFixed = true;
            }

            // HARD CONSTRAINT: Y position must be >= minY
            if ($y < $minY) {
                $layer['y'] = $minY;
                $wasFixed = true;
            }

            // HARD CONSTRAINT: Right edge must be <= maxX
            if ($x + $width > $maxX) {
                // First try moving left
                $newX = $maxX - $width;
                if ($newX >= $minX) {
                    $layer['x'] = $newX;
                } else {
                    // Layer is too wide - reduce width and position at minX
                    $layer['x'] = $minX;
                    $layer['width'] = $maxX - $minX;
                }
                $wasFixed = true;
            }

            // HARD CONSTRAINT: Bottom edge must be <= maxY (except CTA which can be lower)
            $isCta = str_contains($name, 'cta') || str_contains($name, 'button') || $type === 'textbox';
            $effectiveMaxY = $isCta ? ($templateHeight - 40) : $maxY;

            if ($y + $height > $effectiveMaxY) {
                $layer['y'] = $effectiveMaxY - $height;
                $wasFixed = true;
            }

            if ($wasFixed) {
                Log::channel('single')->warning('SelfCorrection: Hard margin enforced', [
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_position' => ['x' => $oldX, 'y' => $oldY],
                    'new_position' => ['x' => $layer['x'], 'y' => $layer['y']],
                    'margins' => ['minX' => $minX, 'minY' => $minY, 'maxX' => $maxX, 'maxY' => $maxY],
                ]);

                $corrections[] = [
                    'type' => 'hard_margin_enforced',
                    'layer' => $layer['name'] ?? 'unknown',
                    'old_position' => ['x' => $oldX, 'y' => $oldY],
                    'new_position' => ['x' => $layer['x'], 'y' => $layer['y']],
                ];
            }

            $fixedLayers[] = $layer;
        }

        return ['layers' => $fixedLayers, 'corrections' => $corrections];
    }

    /**
     * Balance visual weight when headline dominates too much (>85%).
     * Boosts subtext to next modular scale step and adds fontWeight for optical balance.
     */
    protected function balanceVisualWeight(array $layers): array
    {
        $corrections = [];
        $fixedLayers = $layers;

        // Calculate current visual weight distribution
        $weightResult = $this->typographyValidator->calculateVisualWeight($layers);
        $distribution = $weightResult['distribution'] ?? [];

        $headlineWeight = $distribution['headline'] ?? 0;
        $subtextWeight = $distribution['subtext'] ?? 0;

        Log::channel('single')->debug('SelfCorrection: Visual weight check', [
            'headline_weight' => $headlineWeight . '%',
            'subtext_weight' => $subtextWeight . '%',
            'threshold' => '85%',
        ]);

        // If headline dominates too much (>85%), boost subtext
        if ($headlineWeight > 85 && $subtextWeight < 15) {
            Log::channel('single')->warning('SelfCorrection: Headline dominates visual weight, boosting subtext', [
                'current_headline_weight' => $headlineWeight . '%',
                'current_subtext_weight' => $subtextWeight . '%',
            ]);

            // Modular scale for boosting
            $modularScale = [13, 16, 20, 25, 31, 39, 49, 61];

            foreach ($fixedLayers as &$layer) {
                $name = strtolower($layer['name'] ?? '');
                $type = $layer['type'] ?? '';

                if (!in_array($type, ['text', 'textbox'])) {
                    continue;
                }

                // Find subtext layers
                $isSubtext = str_contains($name, 'subtext') ||
                             str_contains($name, 'subtitle') ||
                             str_contains($name, 'description') ||
                             str_contains($name, 'tagline');

                if ($isSubtext) {
                    $currentSize = $layer['properties']['fontSize'] ?? 20;

                    // Find next modular scale step
                    $nextSize = $currentSize;
                    foreach ($modularScale as $scaleSize) {
                        if ($scaleSize > $currentSize) {
                            $nextSize = $scaleSize;
                            break;
                        }
                    }

                    // Only boost if we found a larger size
                    if ($nextSize > $currentSize) {
                        $layer['properties']['fontSize'] = $nextSize;

                        // Add medium weight for optical balance
                        $layer['properties']['fontWeight'] = '500';

                        // Add slight letter spacing for visual expansion
                        $layer['properties']['letterSpacing'] = ($layer['properties']['letterSpacing'] ?? 0) + 0.5;

                        $corrections[] = [
                            'type' => 'visual_weight_balanced',
                            'layer' => $layer['name'] ?? 'subtext',
                            'old_fontSize' => $currentSize,
                            'new_fontSize' => $nextSize,
                            'added_fontWeight' => '500',
                            'reason' => 'Headline dominated at ' . round($headlineWeight, 1) . '% - boosted subtext',
                        ];

                        Log::channel('single')->info('SelfCorrection: Boosted subtext for visual balance', [
                            'layer' => $layer['name'] ?? 'subtext',
                            'old_size' => $currentSize,
                            'new_size' => $nextSize,
                        ]);
                    }
                }
            }
        }

        return ['layers' => $fixedLayers, 'corrections' => $corrections];
    }

    /**
     * Ensure all content layers respect minimum margins (80% canvas rule).
     */
    public function ensureMinimumMargins(array $layers, int $templateWidth, int $templateHeight): array
    {
        $safeMargins = $this->designTokensService->getSafeMargins($templateWidth, $templateHeight);
        $usableArea = $this->designTokensService->getUsableArea($templateWidth, $templateHeight);
        $layersAdjusted = 0;

        Log::channel('single')->info('SelfCorrection: Ensuring minimum margins (80% rule)', [
            'canvas' => ['width' => $templateWidth, 'height' => $templateHeight],
            'safe_margins' => $safeMargins,
            'usable_area' => $usableArea,
            'total_layers' => count($layers),
        ]);

        $fixedLayers = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Skip full-width elements like backgrounds, images
            if (str_contains($name, 'background') ||
                str_contains($name, 'bg') ||
                $type === 'image' ||
                str_contains($name, 'overlay')) {
                $fixedLayers[] = $layer;
                continue;
            }

            $x = $layer['x'] ?? 0;
            $y = $layer['y'] ?? 0;
            $width = $layer['width'] ?? 0;
            $height = $layer['height'] ?? 0;

            // Ensure X is within safe margins
            if ($x < $safeMargins['left']) {
                $layer['x'] = $safeMargins['left'];
            }

            // Ensure Y is within safe margins
            if ($y < $safeMargins['top']) {
                $layer['y'] = $safeMargins['top'];
            }

            // Ensure layer doesn't exceed right margin
            $rightEdge = $layer['x'] + $width;
            $maxRight = $templateWidth - $safeMargins['right'];
            if ($rightEdge > $maxRight) {
                $layer['width'] = $maxRight - $layer['x'];
            }

            // Ensure layer doesn't exceed bottom margin
            $bottomEdge = $layer['y'] + $height;
            $maxBottom = $templateHeight - $safeMargins['bottom'];
            if ($bottomEdge > $maxBottom) {
                $layer['y'] = $maxBottom - $height;
            }

            $fixedLayers[] = $layer;
        }

        return $fixedLayers;
    }
}
