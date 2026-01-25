<?php

use App\Services\AI\ImageAnalysisService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new ImageAnalysisService();
});

describe('ImageAnalysisService', function () {

    describe('analyzeImage', function () {

        it('returns analysis data on successful response', function () {
            Http::fake([
                '*/analyze' => Http::response([
                    'success' => true,
                    'image' => ['width' => 1920, 'height' => 1080],
                    'focal_point' => ['x' => 960, 'y' => 540, 'normalized' => ['x' => 0.5, 'y' => 0.5]],
                    'brightness' => [
                        'top-left' => 0.3,
                        'top-right' => 0.4,
                        'bottom-left' => 0.2,
                        'bottom-right' => 0.5,
                        'overall' => 0.35,
                        'is_dark' => true,
                    ],
                    'suggested_text_position' => 'bottom-left',
                    'safe_zones' => [
                        ['position' => 'bottom-left', 'x' => 40, 'y' => 580, 'width' => 460, 'height' => 460],
                    ],
                    'busy_zones' => [
                        ['position' => 'focal', 'x' => 200, 'y' => 100, 'width' => 600, 'height' => 600],
                    ],
                ], 200),
            ]);

            $result = $this->service->analyzeImage('https://example.com/image.jpg');

            expect($result['success'])->toBeTrue();
            expect($result['focal_point']['x'])->toBe(960);
            expect($result['brightness']['is_dark'])->toBeTrue();
            expect($result['suggested_text_position'])->toBe('bottom-left');
            expect($result['safe_zones'])->not->toBeEmpty();
            expect($result['busy_zones'])->not->toBeEmpty();
        });

        it('returns default analysis on service failure', function () {
            Http::fake([
                '*/analyze' => Http::response(['error' => 'Service unavailable'], 500),
            ]);

            $result = $this->service->analyzeImage('https://example.com/image.jpg');

            expect($result['success'])->toBeFalse();
            expect($result['focal_point'])->toHaveKey('x');
            expect($result['focal_point'])->toHaveKey('y');
            expect($result['brightness'])->toHaveKey('overall');
            expect($result['suggested_text_position'])->toBe('bottom');
            expect($result['safe_zones'])->not->toBeEmpty();
        });

        it('returns default analysis on connection error', function () {
            Http::fake([
                '*/analyze' => function () {
                    throw new \Exception('Connection refused');
                },
            ]);

            $result = $this->service->analyzeImage('https://example.com/image.jpg');

            expect($result['success'])->toBeFalse();
            expect($result)->toHaveKey('focal_point');
            expect($result)->toHaveKey('brightness');
            expect($result)->toHaveKey('safe_zones');
        });

    });

    describe('isAvailable', function () {

        it('returns true when service is healthy', function () {
            Http::fake([
                '*/health' => Http::response(['status' => 'healthy'], 200),
            ]);

            expect($this->service->isAvailable())->toBeTrue();
        });

        it('returns false when service is unavailable', function () {
            Http::fake([
                '*/health' => Http::response([], 500),
            ]);

            expect($this->service->isAvailable())->toBeFalse();
        });

        it('returns false on connection error', function () {
            Http::fake([
                '*/health' => function () {
                    throw new \Exception('Connection refused');
                },
            ]);

            expect($this->service->isAvailable())->toBeFalse();
        });

    });

    describe('adjustLayersToAnalysis', function () {

        it('moves text layers away from busy zones when on photo', function () {
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
                    'y' => 300,
                    'width' => 400,
                    'height' => 60,
                ],
            ];

            $analysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 200, 'width' => 600, 'height' => 600],
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 800, 'width' => 1000, 'height' => 200],
                ],
                'suggested_text_position' => 'bottom',
            ];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            // Text layer should be moved to safe zone
            $textLayer = collect($result)->firstWhere('name', 'headline');
            expect($textLayer['y'])->toBe(800);
        });

        it('does not move text layers that are outside photo area', function () {
            $layers = [
                [
                    'name' => 'photo',
                    'type' => 'image',
                    'x' => 480,  // Photo on right side
                    'y' => 0,
                    'width' => 600,
                    'height' => 1080,
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 80,   // Text on left side (outside photo)
                    'y' => 200,
                    'width' => 400,
                    'height' => 60,
                ],
            ];

            $analysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 337, 'y' => 0, 'width' => 720, 'height' => 1080],  // Covers photo area
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 800, 'width' => 1000, 'height' => 200],
                ],
                'suggested_text_position' => 'bottom',
            ];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            // Text layer should NOT be moved (it's in the text zone, outside photo)
            $textLayer = collect($result)->firstWhere('name', 'headline');
            expect($textLayer['x'])->toBe(80);
            expect($textLayer['y'])->toBe(200);
        });

        it('does not move layers outside busy zones', function () {
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
                    'x' => 40,
                    'y' => 850,
                    'width' => 400,
                    'height' => 60,
                ],
            ];

            $analysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 200, 'width' => 600, 'height' => 400],  // Does not cover y=850
                ],
                'safe_zones' => [],
                'suggested_text_position' => 'bottom',
            ];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            // Layer should remain in place (not in busy zone)
            $textLayer = collect($result)->firstWhere('name', 'headline');
            expect($textLayer['y'])->toBe(850);
        });

        it('ignores non-text layers', function () {
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
                    'name' => 'background',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                ],
            ];

            $analysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 200, 'width' => 600, 'height' => 600],
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 800, 'width' => 1000, 'height' => 200],
                ],
            ];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            $bgLayer = collect($result)->firstWhere('name', 'background');
            expect($bgLayer['x'])->toBe(0);
            expect($bgLayer['y'])->toBe(0);
        });

        it('returns unchanged layers when analysis failed', function () {
            $layers = [
                ['name' => 'headline', 'type' => 'text', 'x' => 100, 'y' => 200],
            ];

            $analysis = ['success' => false];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            expect($result)->toBe($layers);
        });

        it('returns unchanged layers when no photo layer exists', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 300,
                    'y' => 300,
                    'width' => 400,
                    'height' => 60,
                ],
            ];

            $analysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 200, 'width' => 600, 'height' => 600],
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 800, 'width' => 1000, 'height' => 200],
                ],
            ];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            // No photo = no adjustment needed
            expect($result[0]['x'])->toBe(300);
            expect($result[0]['y'])->toBe(300);
        });

        it('stacks multiple text layers in same safe zone', function () {
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
                    'y' => 300,
                    'width' => 400,
                    'height' => 60,
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'x' => 300,
                    'y' => 400,
                    'width' => 400,
                    'height' => 40,
                ],
            ];

            $analysis = [
                'success' => true,
                'busy_zones' => [
                    ['x' => 200, 'y' => 200, 'width' => 600, 'height' => 600],
                ],
                'safe_zones' => [
                    ['position' => 'bottom', 'x' => 40, 'y' => 800, 'width' => 1000, 'height' => 200],
                ],
                'suggested_text_position' => 'bottom',
            ];

            $result = $this->service->adjustLayersToAnalysis($layers, $analysis);

            $headline = collect($result)->firstWhere('name', 'headline');
            $subtext = collect($result)->firstWhere('name', 'subtext');

            // Layers should have DIFFERENT Y positions (stacked)
            expect($headline['y'])->not->toBe($subtext['y']);
            expect($subtext['y'])->toBeGreaterThan($headline['y']);
        });

    });

    describe('getRecommendedTextColor', function () {

        it('returns white for dark image', function () {
            $analysis = [
                'brightness' => ['is_dark' => true],
                'safe_zones' => [],
            ];

            $color = $this->service->getRecommendedTextColor($analysis);

            expect($color)->toBe('#FFFFFF');
        });

        it('returns black for light image', function () {
            $analysis = [
                'brightness' => ['is_dark' => false],
                'safe_zones' => [],
            ];

            $color = $this->service->getRecommendedTextColor($analysis);

            expect($color)->toBe('#000000');
        });

        it('returns color from safe zone for position', function () {
            $analysis = [
                'brightness' => ['is_dark' => false],
                'safe_zones' => [
                    ['position' => 'bottom', 'recommended_text_color' => '#FFFFFF'],
                ],
            ];

            $color = $this->service->getRecommendedTextColor($analysis, 'bottom');

            expect($color)->toBe('#FFFFFF');
        });

    });

});
