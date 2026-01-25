<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Visual Critic Service.
 *
 * Reviews generated templates against premium design criteria
 * and provides feedback/suggestions for improvement.
 */
class VisualCriticService
{
    /**
     * Critique criteria with weights for premium scoring.
     */
    public const CRITIQUE_CRITERIA = [
        'typography_hierarchy' => [
            'weight' => 0.18,
            'checks' => ['headline_dominance', 'scale_consistency', 'tracking_appropriateness'],
        ],
        'composition_balance' => [
            'weight' => 0.22,
            'checks' => ['rule_of_thirds', 'visual_weight', 'negative_space'],
        ],
        'color_harmony' => [
            'weight' => 0.13,
            'checks' => ['palette_coherence', 'contrast_wcag', 'accent_usage'],
        ],
        'depth_and_shadow' => [
            'weight' => 0.12,
            'checks' => ['elevation_consistency', 'light_direction', 'shadow_softness'],
        ],
        'image_text_integration' => [
            'weight' => 0.20,
            'checks' => ['focal_point_clear', 'text_readability', 'overlay_appropriateness'],
        ],
        'aesthetic_quality' => [
            'weight' => 0.15,
            'checks' => ['visual_weight_distribution', 'asymmetry_score', 'dynamism_score'],
        ],
    ];

    /**
     * Minimum score to pass review (0-100).
     */
    public const MINIMUM_SCORE = 75;

    /**
     * Premium headline minimum font size.
     */
    public const PREMIUM_HEADLINE_MIN_SIZE = 39;

    /**
     * Maximum coverage ratio (content vs canvas).
     */
    public const MAX_COVERAGE_RATIO = 0.7;

    /**
     * Hard gates - minimum thresholds that must be met regardless of total score.
     */
    protected const MINIMUM_INTEGRATION_SCORE = 60;
    protected const MAXIMUM_CRITICAL_ISSUES = 1;
    protected const CRITICAL_ISSUE_PATTERNS = [
        'integration:overlap',  // Text overlaps focal point
        'color:contrast',       // WCAG contrast failure
    ];

    public function __construct(
        protected DesignTokensService $designTokensService,
        protected ContrastValidator $contrastValidator,
        protected ElevationService $elevationService,
        protected ?ColorHarmonyValidator $colorHarmonyValidator = null
    ) {
        $this->colorHarmonyValidator = $colorHarmonyValidator ?? new ColorHarmonyValidator();
    }

    /**
     * Review generated template and return critique.
     */
    public function critique(array $layers, array $imageAnalysis, int $width, int $height): array
    {
        $scores = [];
        $issues = [];

        // Typography check
        $typoResult = $this->evaluateTypography($layers);
        $scores['typography_hierarchy'] = $typoResult['score'];
        $issues = array_merge($issues, $typoResult['issues']);

        // Composition check
        $compResult = $this->evaluateComposition($layers, $width, $height);
        $scores['composition_balance'] = $compResult['score'];
        $issues = array_merge($issues, $compResult['issues']);

        // Color harmony check
        $colorResult = $this->evaluateColorHarmony($layers, $imageAnalysis);
        $scores['color_harmony'] = $colorResult['score'];
        $issues = array_merge($issues, $colorResult['issues']);

        // Depth check
        $depthResult = $this->evaluateDepth($layers);
        $scores['depth_and_shadow'] = $depthResult['score'];
        $issues = array_merge($issues, $depthResult['issues']);

        // Image-text integration
        $integrationResult = $this->evaluateIntegration($layers, $imageAnalysis, $width, $height);
        $scores['image_text_integration'] = $integrationResult['score'];
        $issues = array_merge($issues, $integrationResult['issues']);

        // Aesthetic quality check
        $aestheticResult = $this->evaluateAestheticQuality($layers, $width, $height);
        $scores['aesthetic_quality'] = $aestheticResult['score'];
        $issues = array_merge($issues, $aestheticResult['issues']);

        // Calculate weighted total
        $totalScore = 0;
        foreach (self::CRITIQUE_CRITERIA as $criterion => $config) {
            $totalScore += ($scores[$criterion] ?? 0) * $config['weight'];
        }

        // Check hard gates first
        $gateFailures = $this->checkHardGates($scores, $issues);
        $gatesPassed = empty($gateFailures);

        // Both gates AND score must pass
        $passed = $gatesPassed && $totalScore >= self::MINIMUM_SCORE;

        $result = [
            'passed' => $passed,
            'total_score' => round($totalScore, 1),
            'scores' => $scores,
            'issues' => $issues,
            'gate_failures' => $gateFailures,
            'suggestions' => $this->generateSuggestions($issues),
            'verdict' => $passed ? 'APPROVED' : 'NEEDS_REVISION',
        ];

        Log::channel('single')->info('Visual Critic review', [
            'passed' => $passed,
            'gates_passed' => $gatesPassed,
            'gate_failures' => $gateFailures,
            'total_score' => $result['total_score'],
            'scores' => $scores,
            'issues_count' => count($issues),
        ]);

        return $result;
    }

    /**
     * Check hard gates that must pass regardless of total score.
     */
    protected function checkHardGates(array $scores, array $issues): array
    {
        $failed = [];

        // Gate 1: Integration score must meet minimum
        $integrationScore = $scores['image_text_integration'] ?? 0;
        if ($integrationScore < self::MINIMUM_INTEGRATION_SCORE) {
            $failed[] = "integration_score:{$integrationScore} < " . self::MINIMUM_INTEGRATION_SCORE;
        }

        // Gate 2: No critical issues
        foreach ($issues as $issue) {
            foreach (self::CRITICAL_ISSUE_PATTERNS as $pattern) {
                if (str_contains($issue, $pattern)) {
                    $failed[] = "critical_issue:{$pattern}";
                    break;
                }
            }
        }

        // Gate 3: Issue count limit (allow only cosmetic issues)
        $criticalIssueCount = 0;
        foreach ($issues as $issue) {
            // Count non-cosmetic issues
            if (!str_contains($issue, 'aesthetic:') && !str_contains($issue, 'analysis')) {
                $criticalIssueCount++;
            }
        }
        if ($criticalIssueCount > self::MAXIMUM_CRITICAL_ISSUES) {
            $failed[] = "issue_count:{$criticalIssueCount} > " . self::MAXIMUM_CRITICAL_ISSUES;
        }

        return $failed;
    }

    /**
     * Evaluate typography hierarchy.
     */
    protected function evaluateTypography(array $layers): array
    {
        $issues = [];
        $score = 100;

        $textLayers = array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox']));

        if (empty($textLayers)) {
            return ['score' => 50, 'issues' => ['No text layers found']];
        }

        $fontSizes = array_map(fn($l) => $l['properties']['fontSize'] ?? 16, $textLayers);

        if (count($fontSizes) > 1) {
            // Check scale consistency (Major Third 1.25)
            $sortedSizes = $fontSizes;
            sort($sortedSizes);
            $sortedSizes = array_values(array_unique($sortedSizes)); // Re-index after unique

            for ($i = 1; $i < count($sortedSizes); $i++) {
                $ratio = $sortedSizes[$i] / $sortedSizes[$i - 1];
                if ($ratio < 1.15 || $ratio > 1.45) {
                    $issues[] = "typography:scale - Font sizes {$sortedSizes[$i-1]}px and {$sortedSizes[$i]}px don't follow modular scale (ratio: " . round($ratio, 2) . ")";
                    $score -= 15;
                }
            }
        }

        // Check headline dominance
        $maxSize = max($fontSizes);
        if ($maxSize < self::PREMIUM_HEADLINE_MIN_SIZE) {
            $issues[] = "typography:headline - Headline too small ({$maxSize}px). Premium requires " . self::PREMIUM_HEADLINE_MIN_SIZE . "px+ for impact";
            $score -= 20;
        }

        // Check for font variety (should have at least 2 different sizes)
        if (count(array_unique($fontSizes)) < 2 && count($textLayers) > 1) {
            $issues[] = "typography:variety - All text layers have same size. Use hierarchy for visual interest";
            $score -= 10;
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Evaluate composition balance.
     */
    protected function evaluateComposition(array $layers, int $width, int $height): array
    {
        $issues = [];
        $score = 100;

        // Rule of thirds check
        $thirdX = $width / 3;
        $thirdY = $height / 3;

        $textLayers = array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox']));

        foreach ($textLayers as $layer) {
            $x = $layer['x'] ?? 0;
            $y = $layer['y'] ?? 0;
            $layerWidth = $layer['width'] ?? 200;
            $name = $layer['name'] ?? 'unknown';

            // Check if text aligns with power points or is properly centered
            $alignsWithGrid = (
                abs($x - $thirdX) < 50 ||
                abs($x - $thirdX * 2) < 50 ||
                abs($x + $layerWidth / 2 - $width / 2) < 50 || // Centered
                $x < 100 || // Left aligned
                $x + $layerWidth > $width - 100 // Right aligned
            );

            if (!$alignsWithGrid && $x > 120 && $x < $width - 320) {
                $issues[] = "composition:alignment - Text '{$name}' not aligned with composition grid (x={$x})";
                $score -= 10;
            }
        }

        // Negative space check (at least 30% should be "breathing room")
        $totalLayerArea = 0;
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            // Don't count background, image, or overlay in coverage
            if (!in_array($type, ['rectangle', 'image']) ||
                str_contains(strtolower($layer['name'] ?? ''), 'background') ||
                str_contains(strtolower($layer['name'] ?? ''), 'overlay')) {
                continue;
            }
            $totalLayerArea += ($layer['width'] ?? 0) * ($layer['height'] ?? 0);
        }

        $canvasArea = $width * $height;
        $coverageRatio = $totalLayerArea / $canvasArea;

        if ($coverageRatio > self::MAX_COVERAGE_RATIO) {
            $issues[] = "composition:crowded - Design too crowded (" . round($coverageRatio * 100) . "% coverage). Premium needs breathing room";
            $score -= 25;
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Evaluate color harmony.
     */
    protected function evaluateColorHarmony(array $layers, array $imageAnalysis): array
    {
        $issues = [];
        $score = 100;

        // Check if we have colors from image analysis
        $imageColors = $imageAnalysis['colors']['accent_candidates'] ?? [];

        // Collect all colors from layers for harmony validation
        $paletteColors = [];

        // Check for proper contrast on text layers
        $textLayers = array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox']));

        foreach ($textLayers as $layer) {
            $fill = $layer['properties']['fill'] ?? '#000000';
            $paletteColors[] = $fill;

            // Check if white text is used (common issue)
            if (strtoupper($fill) === '#FFFFFF') {
                // Verify there's an overlay or dark background
                $hasSupport = false;
                foreach ($layers as $supportLayer) {
                    $name = strtolower($supportLayer['name'] ?? '');
                    if (str_contains($name, 'overlay') || str_contains($name, 'background')) {
                        $hasSupport = true;
                        break;
                    }
                }

                if (!$hasSupport) {
                    $issues[] = "color:contrast - White text without dark background/overlay detected";
                    $score -= 20;
                }
            }
        }

        // Collect colors from rectangles and textboxes
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            if (in_array($type, ['rectangle', 'textbox'])) {
                $fill = $layer['properties']['fill'] ?? null;
                if ($fill && $fill !== 'transparent') {
                    $paletteColors[] = $fill;
                }
            }
        }

        // Remove duplicates and neutral colors for harmony check
        $paletteColors = array_unique($paletteColors);
        $paletteColors = array_filter($paletteColors, function ($color) {
            $hsl = $this->colorHarmonyValidator->hexToHsl($color);
            // Exclude near-whites, near-blacks, and grays
            return $hsl['s'] > 0.1 && $hsl['l'] > 0.1 && $hsl['l'] < 0.9;
        });
        $paletteColors = array_values($paletteColors);

        // Validate color harmony if we have multiple colors
        if (count($paletteColors) >= 2) {
            $harmonyResult = $this->colorHarmonyValidator->validatePalette($paletteColors);

            if (!$harmonyResult['valid']) {
                foreach ($harmonyResult['issues'] as $harmonyIssue) {
                    $issues[] = $harmonyIssue;
                }
                $score -= (100 - $harmonyResult['score']) * 0.3; // Proportional penalty
            }
        }

        // Check accent color usage
        $hasAccent = false;
        foreach ($layers as $layer) {
            $name = strtolower($layer['name'] ?? '');
            if (str_contains($name, 'accent') || str_contains($name, 'cta')) {
                $hasAccent = true;
                break;
            }
        }

        if (!$hasAccent) {
            $issues[] = "color:accent - No accent color element found. Add visual pop with accent";
            $score -= 10;
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Evaluate depth and shadow usage.
     */
    protected function evaluateDepth(array $layers): array
    {
        $issues = [];
        $score = 100;

        $hasShadow = false;
        $ctaHasShadow = false;

        foreach ($layers as $layer) {
            $shadowEnabled = $layer['properties']['shadowEnabled'] ?? false;

            if ($shadowEnabled) {
                $hasShadow = true;
            }

            // Check if CTA has shadow (it should float)
            $name = strtolower($layer['name'] ?? '');
            $type = $layer['type'] ?? '';
            if ((str_contains($name, 'cta') || str_contains($name, 'button') || $type === 'textbox') && $shadowEnabled) {
                $ctaHasShadow = true;
            }
        }

        // CTA should have elevation
        $ctaLayers = array_filter($layers, fn($l) =>
            ($l['type'] ?? '') === 'textbox' ||
            str_contains(strtolower($l['name'] ?? ''), 'cta') ||
            str_contains(strtolower($l['name'] ?? ''), 'button')
        );

        if (!empty($ctaLayers) && !$ctaHasShadow) {
            $issues[] = "depth:cta - CTA button lacks elevation. Add shadow for floating effect";
            $score -= 15;
        }

        // General shadow usage
        if (!$hasShadow) {
            $issues[] = "depth:flat - No shadows used. Premium designs use subtle elevation";
            $score -= 10;
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Evaluate image-text integration.
     */
    protected function evaluateIntegration(array $layers, array $imageAnalysis, int $width, int $height): array
    {
        $issues = [];
        $score = 100;

        // Check for overlay when image is full-bleed (do this first, before busy zone check)
        $hasFullBleedImage = false;
        $hasOverlay = false;

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            if ($type === 'image') {
                $layerWidth = $layer['width'] ?? 0;
                $layerHeight = $layer['height'] ?? 0;
                if ($layerWidth >= $width * 0.9 && $layerHeight >= $height * 0.9) {
                    $hasFullBleedImage = true;
                }
            }

            if (str_contains($name, 'overlay')) {
                $hasOverlay = true;
            }
        }

        // Full-bleed images with text should have overlay
        $hasTextOnImage = !empty(array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox'])));
        if ($hasFullBleedImage && $hasTextOnImage && !$hasOverlay) {
            $issues[] = "integration:overlay - Full-bleed image with text needs overlay for readability";
            $score -= 20;
        }

        // Now check busy zones for focal point overlap
        $busyZones = $imageAnalysis['busy_zones'] ?? [];

        if (empty($busyZones)) {
            // No image analysis available - basic checks only
            if (empty($issues)) {
                return ['score' => 80, 'issues' => ['integration:analysis - No image analysis available for focal point check']];
            }
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        foreach ($layers as $layer) {
            if (!in_array($layer['type'] ?? '', ['text', 'textbox'])) {
                continue;
            }

            $layerX = $layer['x'] ?? 0;
            $layerY = $layer['y'] ?? 0;
            $layerW = $layer['width'] ?? 0;
            $layerH = $layer['height'] ?? 0;
            $name = $layer['name'] ?? 'unknown';

            foreach ($busyZones as $zone) {
                if ($this->rectanglesOverlap(
                    $layerX, $layerY, $layerW, $layerH,
                    $zone['x'], $zone['y'], $zone['width'], $zone['height']
                )) {
                    $issues[] = "integration:overlap - Text '{$name}' overlaps with focal point at ({$zone['x']}, {$zone['y']})";
                    $score -= 30;
                }
            }
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Evaluate aesthetic quality (visual weight, asymmetry, dynamism).
     */
    protected function evaluateAestheticQuality(array $layers, int $width, int $height): array
    {
        $issues = [];
        $score = 100;

        Log::channel('single')->info('VisualCritic: Evaluating aesthetic quality', [
            'layer_count' => count($layers),
            'canvas' => ['width' => $width, 'height' => $height],
        ]);

        // 1. Visual Weight Distribution (70-20-10 rule)
        $weightResult = $this->evaluateVisualWeight($layers);
        Log::channel('single')->debug('VisualCritic: Visual weight evaluation', [
            'valid' => $weightResult['valid'],
            'distribution' => $weightResult['distribution'],
            'target' => '70% headline, 20% subtext, 10% CTA',
        ]);

        if (!$weightResult['valid']) {
            $issues[] = "aesthetic:weight - Visual weight distribution off. Target: 70% headline, 20% subtext, 10% CTA. Current: " .
                       "Headline {$weightResult['distribution']['headline']}%, " .
                       "Subtext {$weightResult['distribution']['subtext']}%, " .
                       "CTA {$weightResult['distribution']['cta']}%";
            $score -= 20;
        }

        // 2. Asymmetry Score (penalize perfectly centered static layouts)
        $asymmetryResult = $this->evaluateAsymmetry($layers, $width, $height);
        Log::channel('single')->debug('VisualCritic: Asymmetry evaluation', [
            'valid' => $asymmetryResult['valid'],
            'centered_ratio' => $asymmetryResult['centered_ratio'] ?? 'N/A',
        ]);

        if (!$asymmetryResult['valid']) {
            $issues[] = "aesthetic:asymmetry - Layout too symmetric/static. Add visual tension with off-center elements";
            $score -= 15;
        }

        // 3. Dynamism Score (check for accent elements and variation)
        $dynamismResult = $this->evaluateDynamism($layers);
        Log::channel('single')->debug('VisualCritic: Dynamism evaluation', [
            'valid' => $dynamismResult['valid'],
            'has_accent' => $dynamismResult['has_accent'],
            'has_line' => $dynamismResult['has_line'],
            'size_ratio' => $dynamismResult['size_ratio'],
        ]);

        if (!$dynamismResult['valid']) {
            $issues[] = "aesthetic:dynamism - Layout lacks visual interest. Add accent elements or size variation";
            $score -= 15;
        }

        Log::channel('single')->info('VisualCritic: Aesthetic quality result', [
            'final_score' => max(0, $score),
            'issues_count' => count($issues),
        ]);

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Evaluate visual weight distribution (70-20-10 rule).
     *
     * Uses LINEAR font size proportion (same as TypographyHierarchyValidator)
     * to ensure consistent measurements across the pipeline.
     *
     * Why LINEAR instead of QUADRATIC (fontSize²):
     * - Quadratic causes headline (61px) to dominate at 90%+ vs subtext (20px)
     * - Linear gives realistic proportions: 61/(61+20+20) = 60%
     * - Matches the 70-20-10 target more intuitively
     */
    protected function evaluateVisualWeight(array $layers): array
    {
        $textLayers = array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox']));

        if (count($textLayers) < 2) {
            return ['valid' => true, 'distribution' => ['headline' => 100, 'subtext' => 0, 'cta' => 0]];
        }

        $headline = null;
        $subtext = null;
        $cta = null;

        foreach ($textLayers as $layer) {
            $name = strtolower($layer['name'] ?? '');
            $type = $layer['type'] ?? '';

            if (str_contains($name, 'headline') || str_contains($name, 'title')) {
                $headline = $layer;
            } elseif (str_contains($name, 'subtext') || str_contains($name, 'sub')) {
                $subtext = $layer;
            } elseif ($type === 'textbox' || str_contains($name, 'cta') || str_contains($name, 'button')) {
                $cta = $layer;
            }
        }

        // Use LINEAR font size calculation (consistent with TypographyHierarchyValidator)
        $headlineSize = $headline['properties']['fontSize'] ?? 0;
        $subtextSize = $subtext['properties']['fontSize'] ?? 0;
        $ctaSize = $cta['properties']['fontSize'] ?? 0;

        $total = $headlineSize + $subtextSize + $ctaSize;

        if ($total < 1) {
            return ['valid' => true, 'distribution' => ['headline' => 0, 'subtext' => 0, 'cta' => 0]];
        }

        $distribution = [
            'headline' => round(($headlineSize / $total) * 100, 1),
            'subtext' => round(($subtextSize / $total) * 100, 1),
            'cta' => round(($ctaSize / $total) * 100, 1),
        ];

        // Target: 70-20-10 (allow reasonable deviation)
        // Headline: 55-85% (centered on 70%)
        // Subtext: 10-35% (centered on 20%)
        // CTA: 5-20% (centered on 10%)
        $headlineValid = $distribution['headline'] >= 55 && $distribution['headline'] <= 85;
        $subtextValid = $distribution['subtext'] >= 10 && $distribution['subtext'] <= 35;
        $ctaValid = $distribution['cta'] >= 5 && $distribution['cta'] <= 20;

        return [
            'valid' => $headlineValid && $subtextValid && $ctaValid,
            'distribution' => $distribution,
        ];
    }

    /**
     * Evaluate asymmetry (penalize perfectly centered, static layouts).
     */
    protected function evaluateAsymmetry(array $layers, int $width, int $height): array
    {
        $textLayers = array_filter($layers, fn($l) => in_array($l['type'] ?? '', ['text', 'textbox']));

        if (empty($textLayers)) {
            return ['valid' => true, 'score' => 100];
        }

        $centeredCount = 0;
        $totalTextLayers = 0;
        $centerX = $width / 2;

        foreach ($textLayers as $layer) {
            $totalTextLayers++;
            $layerX = $layer['x'] ?? 0;
            $layerWidth = $layer['width'] ?? 200;
            $layerCenterX = $layerX + ($layerWidth / 2);

            // Check if layer is perfectly centered (within 50px of center)
            if (abs($layerCenterX - $centerX) < 50) {
                $centeredCount++;
            }
        }

        // If ALL text layers are centered, layout is too static
        $centeredRatio = $totalTextLayers > 0 ? $centeredCount / $totalTextLayers : 0;
        $valid = $centeredRatio < 0.8 || $totalTextLayers <= 2;

        return [
            'valid' => $valid,
            'score' => $valid ? 100 : 50,
            'centered_ratio' => $centeredRatio,
        ];
    }

    /**
     * Evaluate dynamism (accent elements, size variation, visual interest).
     */
    protected function evaluateDynamism(array $layers): array
    {
        $hasAccent = false;
        $hasLine = false;
        $fontSizes = [];

        foreach ($layers as $layer) {
            $name = strtolower($layer['name'] ?? '');
            $type = $layer['type'] ?? '';

            // Check for accent elements
            if (str_contains($name, 'accent') || str_contains($name, 'highlight')) {
                $hasAccent = true;
            }

            // Check for decorative lines
            if ($type === 'line' || str_contains($name, 'line') || str_contains($name, 'divider')) {
                $hasLine = true;
            }

            // Collect font sizes for variation check
            if (in_array($type, ['text', 'textbox'])) {
                $fontSizes[] = $layer['properties']['fontSize'] ?? 16;
            }
        }

        // Check font size variation (should have at least 2 different sizes)
        $uniqueSizes = array_unique($fontSizes);
        $hasSizeVariation = count($uniqueSizes) >= 2;

        // Calculate size ratio (largest to smallest)
        $sizeRatio = 1;
        if (!empty($fontSizes)) {
            $maxSize = max($fontSizes);
            $minSize = min($fontSizes);
            if ($minSize > 0) {
                $sizeRatio = $maxSize / $minSize;
            }
        }

        // Valid if has accent OR line, AND has size variation with good ratio
        $valid = ($hasAccent || $hasLine) && $hasSizeVariation && $sizeRatio >= 2;

        return [
            'valid' => $valid,
            'has_accent' => $hasAccent,
            'has_line' => $hasLine,
            'has_size_variation' => $hasSizeVariation,
            'size_ratio' => round($sizeRatio, 2),
        ];
    }

    /**
     * Check if two rectangles overlap.
     */
    protected function rectanglesOverlap(
        float $x1, float $y1, float $w1, float $h1,
        float $x2, float $y2, float $w2, float $h2
    ): bool {
        return !(
            $x1 + $w1 < $x2 ||
            $x2 + $w2 < $x1 ||
            $y1 + $h1 < $y2 ||
            $y2 + $h2 < $y1
        );
    }

    /**
     * Generate actionable suggestions from issues.
     */
    protected function generateSuggestions(array $issues): array
    {
        $suggestions = [];

        foreach ($issues as $issue) {
            if (str_contains($issue, 'modular scale')) {
                $suggestions[] = 'Use font sizes from Major Third scale: 13, 16, 20, 25, 31, 39, 49, 61px';
            }
            if (str_contains($issue, 'Headline too small')) {
                $suggestions[] = 'Increase headline to 39px or 49px for scroll-stopping impact';
            }
            if (str_contains($issue, 'crowded')) {
                $suggestions[] = 'Remove decorative elements or increase margins to 80-100px';
            }
            if (str_contains($issue, 'No shadows')) {
                $suggestions[] = 'Add subtle shadow to CTA button: blur 8px, opacity 12%';
            }
            if (str_contains($issue, 'focal point') || str_contains($issue, 'overlap')) {
                $suggestions[] = 'Move text to safe zone or add semi-transparent overlay';
            }
            if (str_contains($issue, 'elevation') || str_contains($issue, 'CTA button lacks')) {
                $suggestions[] = 'Apply elevation level 3 to CTA: shadowBlur=8, shadowOffsetY=4, shadowOpacity=0.12';
            }
            if (str_contains($issue, 'overlay for readability')) {
                $suggestions[] = 'Add dark overlay (opacity 0.5-0.6) between image and text';
            }
            if (str_contains($issue, 'White text without')) {
                $suggestions[] = 'Add dark background or overlay, or change text color to dark';
            }
        }

        return array_unique($suggestions);
    }

    /**
     * Apply automatic fixes based on critique.
     */
    public function applyFixes(array $layers, array $critique, int $width, int $height): array
    {
        $fixedLayers = $layers;
        $issues = $critique['issues'] ?? [];

        foreach ($issues as $issue) {
            // Fix CTA elevation
            if (str_contains($issue, 'CTA button lacks elevation')) {
                $fixedLayers = $this->elevationService->applyElevationToLayers($fixedLayers);
            }

            // Fix headline size
            if (str_contains($issue, 'Headline too small')) {
                $fixedLayers = $this->fixHeadlineSize($fixedLayers);
            }
        }

        return $fixedLayers;
    }

    /**
     * Fix headline size to premium minimum.
     */
    protected function fixHeadlineSize(array $layers): array
    {
        foreach ($layers as &$layer) {
            $name = strtolower($layer['name'] ?? '');
            $type = $layer['type'] ?? '';

            if ($type === 'text' && (str_contains($name, 'headline') || str_contains($name, 'title'))) {
                $currentSize = $layer['properties']['fontSize'] ?? 16;
                if ($currentSize < self::PREMIUM_HEADLINE_MIN_SIZE) {
                    $layer['properties']['fontSize'] = self::PREMIUM_HEADLINE_MIN_SIZE;
                }
            }
        }

        return $layers;
    }
}
