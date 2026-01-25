<?php

use App\Services\AI\TextOverlayService;

beforeEach(function () {
    $this->service = new TextOverlayService();
});

describe('TextOverlayService', function () {

    describe('addTextOverlays', function () {

        it('adds overlay when text is on photo', function () {
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
                    'y' => 500,
                    'width' => 1000,
                    'height' => 60,
                    'properties' => ['fill' => '#FFFFFF'],
                ],
            ];

            $result = $this->service->addTextOverlays($layers, 1080, 1080);

            // Should have 3 layers now (photo + overlay + headline)
            expect(count($result))->toBe(3);

            // Find the overlay
            $overlay = collect($result)->firstWhere('name', 'overlay_for_headline');
            expect($overlay)->not->toBeNull();
            expect($overlay['type'])->toBe('rectangle');
            expect($overlay['properties']['opacity'])->toBeLessThan(1);
        });

        it('does not add overlay for textbox with fill', function () {
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
                    'name' => 'cta',
                    'type' => 'textbox',
                    'x' => 430,
                    'y' => 900,
                    'width' => 220,
                    'height' => 50,
                    'properties' => [
                        'fill' => '#D4AF37',
                        'textColor' => '#FFFFFF',
                    ],
                ],
            ];

            $result = $this->service->addTextOverlays($layers, 1080, 1080);

            // Should still have 2 layers (no overlay needed - textbox has fill)
            expect(count($result))->toBe(2);
        });

        it('does not add overlay when no photo', function () {
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
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 500,
                    'width' => 1000,
                    'height' => 60,
                ],
            ];

            $result = $this->service->addTextOverlays($layers, 1080, 1080);

            // Should still have 2 layers
            expect(count($result))->toBe(2);
        });

        it('does not add duplicate overlays', function () {
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
                    'name' => 'existing_overlay',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 480,
                    'width' => 1080,
                    'height' => 100,
                    'properties' => ['opacity' => 0.5, 'fill' => '#000000'],
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

            $result = $this->service->addTextOverlays($layers, 1080, 1080);

            // Should still have 3 layers (existing overlay is enough)
            expect(count($result))->toBe(3);
        });

        it('handles text not overlapping photo', function () {
            $layers = [
                [
                    'name' => 'photo',
                    'type' => 'image',
                    'x' => 0,
                    'y' => 0,
                    'width' => 1080,
                    'height' => 500,
                ],
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 40,
                    'y' => 600,
                    'width' => 1000,
                    'height' => 60,
                ],
            ];

            $result = $this->service->addTextOverlays($layers, 1080, 1080);

            // No overlay needed - text is below photo
            expect(count($result))->toBe(2);
        });

    });

    describe('createGradientOverlay', function () {

        it('creates top gradient overlay', function () {
            $overlay = $this->service->createGradientOverlay('top', 1080, 1080);

            expect($overlay['name'])->toBe('gradient_overlay_top');
            expect($overlay['y'])->toBe(0);
            expect($overlay['properties']['fillType'])->toBe('gradient');
        });

        it('creates bottom gradient overlay', function () {
            $overlay = $this->service->createGradientOverlay('bottom', 1080, 1080);

            expect($overlay['name'])->toBe('gradient_overlay_bottom');
            expect($overlay['y'])->toBeGreaterThan(0);
            expect($overlay['properties']['fillType'])->toBe('gradient');
        });

    });

});
