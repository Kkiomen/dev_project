<?php

use App\Services\AI\SelfCorrectionService;
use App\Services\AI\ContrastValidator;
use App\Services\AI\TypographyHierarchyValidator;
use App\Services\AI\GridSnapService;
use App\Services\AI\DesignTokensService;
use App\Services\OpenAiClientService;

beforeEach(function () {
    $this->openAiClient = Mockery::mock(OpenAiClientService::class);
    $this->contrastValidator = new ContrastValidator();
    $this->typographyValidator = new TypographyHierarchyValidator();
    $this->gridSnapService = new GridSnapService();
    $this->designTokensService = new DesignTokensService();

    $this->service = new SelfCorrectionService(
        $this->openAiClient,
        $this->contrastValidator,
        $this->typographyValidator,
        $this->gridSnapService,
        $this->designTokensService
    );
});

afterEach(function () {
    Mockery::close();
});

describe('SelfCorrectionService', function () {

    describe('reviewAndCorrect', function () {

        it('snaps all layers to grid', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 137,
                    'y' => 423,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 48, 'fill' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            expect($result['layers'][0]['x'])->toBe(136);
            expect($result['layers'][0]['y'])->toBe(424);
        });

        it('snaps font sizes to design tokens', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 200,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 45, 'fill' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            // 45 should snap to 49 (nearest in modular scale)
            expect($result['layers'][0]['properties']['fontSize'])->toBe(49);
        });

        it('fixes typography hierarchy', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 200,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 20, 'fill' => '#FFFFFF'],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 300,
                    'width' => 400,
                    'height' => 40,
                    'properties' => ['fontSize' => 24, 'fill' => '#CCCCCC'],
                ],
                [
                    'name' => 'cta',
                    'type' => 'textbox',
                    'x' => 40,
                    'y' => 400,
                    'width' => 200,
                    'height' => 50,
                    'properties' => ['fontSize' => 16, 'fill' => '#D4AF37', 'textColor' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            $headline = collect($result['layers'])->firstWhere('name', 'headline');
            $subtext = collect($result['layers'])->firstWhere('name', 'subtext');

            expect($headline['properties']['fontSize'])->toBeGreaterThan($subtext['properties']['fontSize']);
        });

        it('fixes contrast issues', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                    'properties' => ['fill' => '#FFFFFF'],
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 200,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 48, 'fill' => '#EEEEEE'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            $headline = collect($result['layers'])->firstWhere('name', 'headline');

            // Light gray on white should be fixed to black
            expect($headline['properties']['fill'])->toBe('#000000');
        });

        it('moves text away from busy zones', function () {
            $layers = [
                [
                    'name' => 'photo',
                    'type' => 'image',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 300,
                    'y' => 400,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 48, 'fill' => '#FFFFFF'],
                ],
            ];

            $imageAnalysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 300, 'width' => 600, 'height' => 400],
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 800, 'width' => 1000, 'height' => 200, 'brightness' => 0.2],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, $imageAnalysis, 1080, 1080);

            // Find headline layer (photo layer is also in result)
            $headline = collect($result['layers'])->firstWhere('name', 'headline');
            // Should be moved to safe zone
            expect($headline['y'])->toBe(800);
        });

        it('does not overlap multiple text layers in same safe zone', function () {
            $layers = [
                [
                    'name' => 'photo',
                    'type' => 'image',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 300,
                    'y' => 400,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 48, 'fill' => '#FFFFFF'],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'x' => 300,
                    'y' => 500,
                    'width' => 400,
                    'height' => 40,
                    'properties' => ['fontSize' => 20, 'fill' => '#CCCCCC'],
                ],
            ];

            $imageAnalysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 300, 'width' => 600, 'height' => 400],
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 780, 'width' => 1000, 'height' => 260, 'brightness' => 0.2],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, $imageAnalysis, 1080, 1080);

            $headline = collect($result['layers'])->firstWhere('name', 'headline');
            $subtext = collect($result['layers'])->firstWhere('name', 'subtext');

            // Headline and subtext should have DIFFERENT Y positions
            expect($headline['y'])->not->toBe($subtext['y']);

            // Subtext should be below headline
            expect($subtext['y'])->toBeGreaterThan($headline['y']);
        });

        it('fixes inconsistent margins', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 10,
                    'y' => 200,
                    'width' => 1060,
                    'height' => 60,
                    'properties' => ['fontSize' => 48, 'fill' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            // Left margin should be at least 40
            expect($result['layers'][0]['x'])->toBeGreaterThanOrEqual(40);
        });

        it('centers CTA button', function () {
            $layers = [
                [
                    'name' => 'cta_button',
                    'type' => 'textbox',
                    'x' => 100,
                    'y' => 900,
                    'width' => 220,
                    'height' => 50,
                    'properties' => ['fontSize' => 16, 'fill' => '#D4AF37', 'textColor' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            // CTA should be approximately centered
            // With minimum CTA width of 280px: (1080 - 280) / 2 = 400, snapped to grid
            // The exact value depends on grid snapping
            expect($result['layers'][0]['x'])->toBeGreaterThanOrEqual(392);
            expect($result['layers'][0]['x'])->toBeLessThanOrEqual(408);
        });

        it('moves CTA to lower portion if too high', function () {
            $layers = [
                [
                    'name' => 'cta',
                    'type' => 'textbox',
                    'x' => 430,
                    'y' => 200,
                    'width' => 220,
                    'height' => 50,
                    'properties' => ['fontSize' => 16, 'fill' => '#D4AF37', 'textColor' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            // CTA should be moved to bottom
            expect($result['layers'][0]['y'])->toBeGreaterThanOrEqual(900);
        });

        it('returns corrections_applied flag', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 137,
                    'y' => 423,
                    'width' => 400,
                    'height' => 60,
                    'properties' => ['fontSize' => 45, 'fill' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->reviewAndCorrect($layers, [], 1080, 1080);

            expect($result)->toHaveKey('corrections_applied');
            expect($result)->toHaveKey('corrections');
            expect($result)->toHaveKey('layers');
        });

    });

});
