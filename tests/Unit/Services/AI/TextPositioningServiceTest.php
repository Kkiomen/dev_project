<?php

use App\Services\AI\TextPositioningService;

beforeEach(function () {
    $this->service = new TextPositioningService();
});

describe('TextPositioningService', function () {

    describe('fixTextPositioning', function () {

        it('fixes overlapping text layers', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 500,
                    'width' => 1000,
                    'height' => 60,
                    'properties' => ['fontSize' => 48],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 510, // Overlaps with headline!
                    'width' => 1000,
                    'height' => 40,
                    'properties' => ['fontSize' => 20],
                ],
            ];

            $result = $this->service->fixTextPositioning($layers, 1080, 1080);

            $headline = collect($result)->firstWhere('name', 'headline');
            $subtext = collect($result)->firstWhere('name', 'subtext');

            // Subtext should be below headline (no overlap)
            $headlineBottom = $headline['y'] + $headline['height'];
            expect($subtext['y'])->toBeGreaterThanOrEqual($headlineBottom);
        });

        it('positions CTA at bottom', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 200,
                    'width' => 1000,
                    'height' => 60,
                    'properties' => ['fontSize' => 48],
                ],
                [
                    'name' => 'cta_button',
                    'type' => 'textbox',
                    'x' => 100,
                    'y' => 250, // Should be at bottom, not here
                    'width' => 220,
                    'height' => 50,
                    'properties' => ['fontSize' => 16],
                ],
            ];

            $result = $this->service->fixTextPositioning($layers, 1080, 1080);

            $cta = collect($result)->firstWhere('name', 'cta_button');

            // CTA should be near bottom
            expect($cta['y'])->toBeGreaterThanOrEqual(900);
        });

        it('maintains proper margins', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 10, // Too close to edge
                    'y' => 200,
                    'width' => 1060, // Too wide
                    'height' => 60,
                    'properties' => ['fontSize' => 48],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'x' => 5, // Also too close
                    'y' => 210, // Overlapping - triggers repositioning
                    'width' => 800,
                    'height' => 40,
                    'properties' => ['fontSize' => 20],
                ],
            ];

            $result = $this->service->fixTextPositioning($layers, 1080, 1080);

            $headline = collect($result)->firstWhere('name', 'headline');
            $subtext = collect($result)->firstWhere('name', 'subtext');

            // Should have proper left margin
            expect($headline['x'])->toBeGreaterThanOrEqual(40);
            expect($subtext['x'])->toBeGreaterThanOrEqual(40);

            // Width should allow for right margin
            expect($headline['x'] + $headline['width'])->toBeLessThanOrEqual(1040);
        });

        it('handles single text layer', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 500,
                    'width' => 1000,
                    'height' => 60,
                ],
            ];

            $result = $this->service->fixTextPositioning($layers, 1080, 1080);

            // Should return unchanged
            expect(count($result))->toBe(1);
        });

        it('preserves non-text layers', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 1080,
                ],
                [
                    'name' => 'photo',
                    'type' => 'image',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 600,
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 500,
                    'width' => 1000,
                    'height' => 60,
                ],
            ];

            $result = $this->service->fixTextPositioning($layers, 1080, 1080);

            $background = collect($result)->firstWhere('name', 'background');
            $photo = collect($result)->firstWhere('name', 'photo');

            expect($background)->not->toBeNull();
            expect($photo)->not->toBeNull();
            expect($background['x'])->toBe(0);
            expect($photo['y'])->toBe(0);
        });

    });

    describe('centerHorizontally', function () {

        it('centers a layer', function () {
            $layer = [
                'name' => 'cta',
                'x' => 100,
                'width' => 220,
            ];

            $result = $this->service->centerHorizontally($layer, 1080);

            // (1080 - 220) / 2 = 430
            expect($result['x'])->toBe(430);
        });

    });

    describe('alignLeft', function () {

        it('aligns text layers to left margin', function () {
            $layers = [
                ['name' => 'headline', 'type' => 'text', 'x' => 100],
                ['name' => 'subtext', 'type' => 'text', 'x' => 200],
                ['name' => 'background', 'type' => 'rectangle', 'x' => 0],
            ];

            $result = $this->service->alignLeft($layers, 40);

            expect($result[0]['x'])->toBe(40);
            expect($result[1]['x'])->toBe(40);
            expect($result[2]['x'])->toBe(0); // Non-text unchanged
        });

    });

    describe('calculateNextYPosition', function () {

        it('calculates position after existing text', function () {
            $layers = [
                ['type' => 'text', 'y' => 100, 'height' => 60],
                ['type' => 'text', 'y' => 200, 'height' => 40],
            ];

            $nextY = $this->service->calculateNextYPosition($layers, 24);

            // Last text ends at 200 + 40 = 240, plus spacing 24 = 264
            expect($nextY)->toBe(264);
        });

        it('returns spacing when no text layers', function () {
            $layers = [
                ['type' => 'rectangle', 'y' => 0, 'height' => 1080],
            ];

            $nextY = $this->service->calculateNextYPosition($layers, 24);

            expect($nextY)->toBe(24);
        });

    });

});
