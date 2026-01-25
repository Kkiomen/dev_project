<?php

use App\Services\AI\GridSnapService;

beforeEach(function () {
    $this->service = new GridSnapService();
});

describe('GridSnapService', function () {

    describe('snapToGrid', function () {

        it('snaps values to nearest multiple of 8', function () {
            expect($this->service->snapToGrid(0))->toBe(0);
            expect($this->service->snapToGrid(3))->toBe(0);
            expect($this->service->snapToGrid(4))->toBe(8); // 4 rounds up to 8
            expect($this->service->snapToGrid(5))->toBe(8);
            expect($this->service->snapToGrid(8))->toBe(8);
            expect($this->service->snapToGrid(11))->toBe(8);
            expect($this->service->snapToGrid(12))->toBe(16);
            expect($this->service->snapToGrid(137))->toBe(136);
            expect($this->service->snapToGrid(423))->toBe(424);
        });

        it('handles float values', function () {
            expect($this->service->snapToGrid(7.5))->toBe(8);
            expect($this->service->snapToGrid(15.9))->toBe(16);
            expect($this->service->snapToGrid(16.1))->toBe(16);
        });

        it('handles negative values', function () {
            expect($this->service->snapToGrid(-3))->toBe(0);
            expect($this->service->snapToGrid(-4))->toBe(-8); // -4 rounds to -8
            expect($this->service->snapToGrid(-8))->toBe(-8);
            expect($this->service->snapToGrid(-12))->toBe(-16);
        });

    });

    describe('snapLayer', function () {

        it('snaps layer coordinates to grid', function () {
            $layer = [
                'x' => 137,
                'y' => 423,
                'width' => 255,
                'height' => 100,
            ];

            $result = $this->service->snapLayer($layer);

            expect($result['x'])->toBe(136);
            expect($result['y'])->toBe(424);
            expect($result['width'])->toBe(256);
            expect($result['height'])->toBe(104);
        });

        it('ensures minimum width and height of 8', function () {
            $layer = [
                'x' => 0,
                'y' => 0,
                'width' => 3,
                'height' => 2,
            ];

            $result = $this->service->snapLayer($layer);

            expect($result['width'])->toBe(8);
            expect($result['height'])->toBe(8);
        });

        it('snaps cornerRadius in properties', function () {
            $layer = [
                'x' => 0,
                'y' => 0,
                'width' => 100,
                'height' => 50,
                'properties' => [
                    'cornerRadius' => 13,
                ],
            ];

            $result = $this->service->snapLayer($layer);

            expect($result['properties']['cornerRadius'])->toBe(16);
        });

        it('snaps padding in properties', function () {
            $layer = [
                'x' => 0,
                'y' => 0,
                'width' => 100,
                'height' => 50,
                'properties' => [
                    'padding' => 18,
                ],
            ];

            $result = $this->service->snapLayer($layer);

            expect($result['properties']['padding'])->toBe(16);
        });

        it('preserves other layer properties', function () {
            $layer = [
                'name' => 'test_layer',
                'type' => 'rectangle',
                'x' => 10,
                'y' => 20,
                'width' => 100,
                'height' => 50,
                'properties' => [
                    'fill' => '#FF0000',
                    'opacity' => 0.5,
                ],
            ];

            $result = $this->service->snapLayer($layer);

            expect($result['name'])->toBe('test_layer');
            expect($result['type'])->toBe('rectangle');
            expect($result['properties']['fill'])->toBe('#FF0000');
            expect($result['properties']['opacity'])->toBe(0.5);
        });

    });

    describe('snapAllLayers', function () {

        it('snaps multiple layers', function () {
            $layers = [
                ['x' => 5, 'y' => 10, 'width' => 100, 'height' => 50],
                ['x' => 137, 'y' => 423, 'width' => 200, 'height' => 150],
            ];

            $result = $this->service->snapAllLayers($layers);

            expect($result[0]['x'])->toBe(8);
            expect($result[0]['y'])->toBe(8);
            expect($result[1]['x'])->toBe(136);
            expect($result[1]['y'])->toBe(424);
        });

        it('handles empty array', function () {
            $result = $this->service->snapAllLayers([]);

            expect($result)->toBe([]);
        });

    });

    describe('isOnGrid', function () {

        it('returns true for values on grid', function () {
            expect($this->service->isOnGrid(0))->toBeTrue();
            expect($this->service->isOnGrid(8))->toBeTrue();
            expect($this->service->isOnGrid(16))->toBeTrue();
            expect($this->service->isOnGrid(1080))->toBeTrue();
        });

        it('returns false for values off grid', function () {
            expect($this->service->isOnGrid(1))->toBeFalse();
            expect($this->service->isOnGrid(7))->toBeFalse();
            expect($this->service->isOnGrid(137))->toBeFalse();
        });

    });

    describe('getGridValues', function () {

        it('returns valid grid values in range', function () {
            $result = $this->service->getGridValues(0, 32);

            expect($result)->toBe([0, 8, 16, 24, 32]);
        });

        it('snaps min value to grid', function () {
            $result = $this->service->getGridValues(5, 25);

            expect($result)->toBe([8, 16, 24]);
        });

    });

});
