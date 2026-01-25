<?php

use App\Services\AI\VisualCriticService;
use App\Services\AI\DesignTokensService;
use App\Services\AI\ContrastValidator;
use App\Services\AI\ElevationService;

beforeEach(function () {
    $this->designTokens = new DesignTokensService();
    $this->contrastValidator = new ContrastValidator();
    $this->elevationService = new ElevationService();

    $this->service = new VisualCriticService(
        $this->designTokens,
        $this->contrastValidator,
        $this->elevationService
    );
});

describe('VisualCriticService', function () {

    describe('critique', function () {

        it('approves well-designed template', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                    'properties' => ['fill' => '#1E3A5F'],
                ],
                [
                    'name' => 'overlay',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                    'properties' => ['fill' => '#000000', 'opacity' => 0.5],
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 80,
                    'y' => 300,
                    'width' => 920,
                    'height' => 100,
                    'properties' => [
                        'text' => 'Premium Design',
                        'fontSize' => 49,
                        'fill' => '#FFFFFF',
                    ],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'x' => 80,
                    'y' => 420,
                    'width' => 920,
                    'height' => 50,
                    'properties' => [
                        'text' => 'Beautiful typography',
                        'fontSize' => 20,
                        'fill' => '#CCCCCC',
                    ],
                ],
                [
                    'name' => 'cta_button',
                    'type' => 'textbox',
                    'x' => 430,
                    'y' => 920,
                    'width' => 220,
                    'height' => 50,
                    'properties' => [
                        'text' => 'Learn More',
                        'fontSize' => 16,
                        'fill' => '#D4AF37',
                        'shadowEnabled' => true,
                        'shadowBlur' => 8,
                        'shadowOffsetY' => 4,
                        'shadowOpacity' => 0.12,
                    ],
                ],
            ];

            $imageAnalysis = [
                'success' => true,
                'busy_zones' => [],
                'colors' => ['accent_candidates' => ['#D4AF37']],
            ];

            $result = $this->service->critique($layers, $imageAnalysis, 1080, 1080);

            expect($result['passed'])->toBeTrue();
            expect($result['total_score'])->toBeGreaterThanOrEqual(75);
            expect($result['verdict'])->toBe('APPROVED');
        });

        it('rejects template with small headline', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 80,
                    'y' => 300,
                    'width' => 920,
                    'height' => 50,
                    'properties' => [
                        'text' => 'Too Small',
                        'fontSize' => 20, // Too small for headline
                        'fill' => '#FFFFFF',
                    ],
                ],
            ];

            $result = $this->service->critique($layers, [], 1080, 1080);

            expect($result['scores']['typography_hierarchy'])->toBeLessThan(100);

            $hasHeadlineIssue = false;
            foreach ($result['issues'] as $issue) {
                if (str_contains($issue, 'Headline too small')) {
                    $hasHeadlineIssue = true;
                    break;
                }
            }
            expect($hasHeadlineIssue)->toBeTrue();
        });

        it('rejects template with CTA missing shadow', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 80,
                    'y' => 300,
                    'width' => 920,
                    'height' => 100,
                    'properties' => [
                        'text' => 'Good Headline',
                        'fontSize' => 49,
                        'fill' => '#FFFFFF',
                    ],
                ],
                [
                    'name' => 'cta_button',
                    'type' => 'textbox',
                    'x' => 430,
                    'y' => 920,
                    'width' => 220,
                    'height' => 50,
                    'properties' => [
                        'text' => 'Click',
                        'fill' => '#D4AF37',
                        'shadowEnabled' => false, // No shadow
                    ],
                ],
            ];

            $result = $this->service->critique($layers, [], 1080, 1080);

            expect($result['scores']['depth_and_shadow'])->toBeLessThan(100);

            $hasCtaIssue = false;
            foreach ($result['issues'] as $issue) {
                if (str_contains($issue, 'CTA button lacks elevation')) {
                    $hasCtaIssue = true;
                    break;
                }
            }
            expect($hasCtaIssue)->toBeTrue();
        });

        it('detects text overlapping focal point', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 200,
                    'y' => 200,
                    'width' => 400,
                    'height' => 100,
                    'properties' => [
                        'text' => 'Overlapping Text',
                        'fontSize' => 49,
                    ],
                ],
            ];

            $imageAnalysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 150, 'y' => 150, 'width' => 500, 'height' => 500],
                ],
            ];

            $result = $this->service->critique($layers, $imageAnalysis, 1080, 1080);

            expect($result['scores']['image_text_integration'])->toBeLessThan(100);

            $hasOverlapIssue = false;
            foreach ($result['issues'] as $issue) {
                if (str_contains($issue, 'overlaps with focal point')) {
                    $hasOverlapIssue = true;
                    break;
                }
            }
            expect($hasOverlapIssue)->toBeTrue();
        });

        it('warns about missing overlay for full-bleed images', function () {
            $layers = [
                [
                    'name' => 'photo',
                    'type' => 'image',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                    'properties' => [],
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 80,
                    'y' => 300,
                    'width' => 920,
                    'height' => 100,
                    'properties' => [
                        'text' => 'Text on Image',
                        'fontSize' => 49,
                    ],
                ],
            ];

            $result = $this->service->critique($layers, ['success' => true, 'busy_zones' => []], 1080, 1080);

            $hasOverlayIssue = false;
            foreach ($result['issues'] as $issue) {
                if (str_contains($issue, 'overlay for readability')) {
                    $hasOverlayIssue = true;
                    break;
                }
            }
            expect($hasOverlayIssue)->toBeTrue();
        });

    });

    describe('generateSuggestions', function () {

        it('suggests modular scale for font issues', function () {
            $layers = [
                [
                    'name' => 'text1',
                    'type' => 'text',
                    'properties' => ['fontSize' => 18],
                ],
                [
                    'name' => 'text2',
                    'type' => 'text',
                    'properties' => ['fontSize' => 19], // Bad ratio
                ],
            ];

            $result = $this->service->critique($layers, [], 1080, 1080);

            // Check suggestions contain modular scale advice
            $hasSuggestion = false;
            foreach ($result['suggestions'] as $suggestion) {
                if (str_contains($suggestion, 'Major Third scale')) {
                    $hasSuggestion = true;
                    break;
                }
            }
            expect($hasSuggestion)->toBeTrue();
        });

    });

    describe('applyFixes', function () {

        it('fixes headline size', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => [
                        'text' => 'Small Headline',
                        'fontSize' => 25,
                    ],
                ],
            ];

            $critique = [
                'issues' => ['typography:headline - Headline too small (25px).'],
            ];

            $result = $this->service->applyFixes($layers, $critique, 1080, 1080);

            expect($result[0]['properties']['fontSize'])->toBe(39);
        });

        it('applies elevation to CTA', function () {
            $layers = [
                [
                    'name' => 'cta_button',
                    'type' => 'textbox',
                    'properties' => [
                        'text' => 'Click',
                        'shadowEnabled' => false,
                    ],
                ],
            ];

            $critique = [
                'issues' => ['depth:cta - CTA button lacks elevation.'],
            ];

            $result = $this->service->applyFixes($layers, $critique, 1080, 1080);

            expect($result[0]['properties']['shadowEnabled'])->toBeTrue();
        });

    });

});
