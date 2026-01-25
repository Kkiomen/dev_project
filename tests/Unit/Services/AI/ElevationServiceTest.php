<?php

use App\Services\AI\ElevationService;

beforeEach(function () {
    $this->service = new ElevationService();
});

describe('ElevationService', function () {

    describe('getShadowForElevation', function () {

        it('returns no shadow for elevation 0', function () {
            $result = $this->service->getShadowForElevation(0);

            expect($result['shadowEnabled'])->toBeFalse();
        });

        it('returns shadow properties for elevation 1', function () {
            $result = $this->service->getShadowForElevation(1);

            expect($result['shadowEnabled'])->toBeTrue();
            expect($result['shadowColor'])->toBe('#000000');
            expect($result['shadowBlur'])->toBe(2);
            expect($result['shadowOffsetY'])->toBe(1);
            expect($result['shadowOpacity'])->toBe(0.08);
        });

        it('returns stronger shadow for elevation 3', function () {
            $result = $this->service->getShadowForElevation(3);

            expect($result['shadowEnabled'])->toBeTrue();
            expect($result['shadowBlur'])->toBe(8);
            expect($result['shadowOffsetY'])->toBe(4);
            expect($result['shadowOpacity'])->toBe(0.12);
        });

        it('returns maximum shadow for elevation 5', function () {
            $result = $this->service->getShadowForElevation(5);

            expect($result['shadowEnabled'])->toBeTrue();
            expect($result['shadowBlur'])->toBe(24);
            expect($result['shadowOffsetY'])->toBe(12);
            expect($result['shadowOpacity'])->toBe(0.16);
        });

        it('clamps elevation to valid range', function () {
            $resultHigh = $this->service->getShadowForElevation(10);
            $result5 = $this->service->getShadowForElevation(5);

            expect($resultHigh)->toBe($result5);

            $resultLow = $this->service->getShadowForElevation(-5);
            $result0 = $this->service->getShadowForElevation(0);

            expect($resultLow)->toBe($result0);
        });

    });

    describe('getAllShadowLayers', function () {

        it('returns empty array for elevation 0', function () {
            $result = $this->service->getAllShadowLayers(0);

            expect($result)->toBeEmpty();
        });

        it('returns multiple shadow layers for higher elevations', function () {
            $result = $this->service->getAllShadowLayers(3);

            expect($result)->toHaveCount(2);
            expect($result[0])->toHaveKey('blur');
            expect($result[0])->toHaveKey('offsetY');
            expect($result[0])->toHaveKey('opacity');
        });

    });

    describe('getElevationForLayerType', function () {

        it('returns 0 for background layers', function () {
            expect($this->service->getElevationForLayerType('background'))->toBe(0);
        });

        it('returns 0 for image layers', function () {
            expect($this->service->getElevationForLayerType('image'))->toBe(0);
        });

        it('returns 3 for textbox (CTA) layers', function () {
            expect($this->service->getElevationForLayerType('textbox'))->toBe(3);
        });

        it('detects CTA by name', function () {
            expect($this->service->getElevationForLayerType('text', 'cta_button'))->toBe(3);
            expect($this->service->getElevationForLayerType('rectangle', 'Button Container'))->toBe(3);
        });

        it('returns 2 for card/panel layers', function () {
            expect($this->service->getElevationForLayerType('rectangle', 'card_background'))->toBe(2);
            expect($this->service->getElevationForLayerType('rectangle', 'info_panel'))->toBe(2);
        });

        it('returns 1 for accent layers', function () {
            expect($this->service->getElevationForLayerType('rectangle', 'accent_line'))->toBe(1);
        });

    });

    describe('applyElevationToLayer', function () {

        it('applies elevation shadow to CTA button', function () {
            $layer = [
                'name' => 'cta_button',
                'type' => 'textbox',
                'properties' => [
                    'text' => 'Click Me',
                ],
            ];

            $result = $this->service->applyElevationToLayer($layer);

            expect($result['properties']['shadowEnabled'])->toBeTrue();
            expect($result['properties']['shadowBlur'])->toBe(8);
        });

        it('does not apply shadow to background', function () {
            $layer = [
                'name' => 'background',
                'type' => 'rectangle',
                'properties' => [
                    'fill' => '#000000',
                ],
            ];

            $result = $this->service->applyElevationToLayer($layer);

            expect($result['properties'])->not->toHaveKey('shadowEnabled');
        });

        it('preserves existing properties', function () {
            $layer = [
                'name' => 'cta_button',
                'type' => 'textbox',
                'properties' => [
                    'text' => 'Click Me',
                    'fill' => '#FF0000',
                ],
            ];

            $result = $this->service->applyElevationToLayer($layer);

            expect($result['properties']['text'])->toBe('Click Me');
            expect($result['properties']['fill'])->toBe('#FF0000');
        });

    });

    describe('applyElevationToLayers', function () {

        it('applies elevation to all layers', function () {
            $layers = [
                ['name' => 'background', 'type' => 'rectangle', 'properties' => []],
                ['name' => 'cta', 'type' => 'textbox', 'properties' => []],
                ['name' => 'photo', 'type' => 'image', 'properties' => []],
            ];

            $result = $this->service->applyElevationToLayers($layers);

            // Background should have no shadow
            expect($result[0]['properties'])->not->toHaveKey('shadowEnabled');

            // CTA should have level 3 shadow
            expect($result[1]['properties']['shadowEnabled'])->toBeTrue();
            expect($result[1]['properties']['shadowBlur'])->toBe(8);

            // Image should have no shadow
            expect($result[2]['properties'])->not->toHaveKey('shadowEnabled');
        });

    });

    describe('getCssBoxShadow', function () {

        it('returns none for elevation 0', function () {
            expect($this->service->getCssBoxShadow(0))->toBe('none');
        });

        it('returns valid CSS for elevation 3', function () {
            $result = $this->service->getCssBoxShadow(3);

            expect($result)->toContain('0 4px 8px rgba(0, 0, 0, 0.12)');
        });

    });

    describe('getFloatingEffect', function () {

        it('returns elevation states for interactive elements', function () {
            $result = $this->service->getFloatingEffect(3);

            expect($result)->toHaveKey('normal');
            expect($result)->toHaveKey('hover');
            expect($result)->toHaveKey('pressed');

            // Hover should be higher elevation
            expect($result['hover']['shadowBlur'])->toBeGreaterThan($result['normal']['shadowBlur']);

            // Pressed should be lower elevation
            expect($result['pressed']['shadowBlur'])->toBeLessThan($result['normal']['shadowBlur']);
        });

    });

});
