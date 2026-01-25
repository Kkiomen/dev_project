<?php

use App\Services\AI\FormatService;

beforeEach(function () {
    $this->service = new FormatService();
});

describe('FormatService', function () {

    describe('getFormat', function () {

        it('returns square format by default', function () {
            $result = $this->service->getFormat('square');

            expect($result['width'])->toBe(1080);
            expect($result['height'])->toBe(1080);
            expect($result['ratio'])->toBe('1:1');
        });

        it('returns portrait format (4:5)', function () {
            $result = $this->service->getFormat('portrait');

            expect($result['width'])->toBe(1080);
            expect($result['height'])->toBe(1350);
            expect($result['ratio'])->toBe('4:5');
        });

        it('returns story format (9:16)', function () {
            $result = $this->service->getFormat('story');

            expect($result['width'])->toBe(1080);
            expect($result['height'])->toBe(1920);
            expect($result['ratio'])->toBe('9:16');
            expect($result['safe_zone'])->toHaveKey('top');
            expect($result['safe_zone'])->toHaveKey('bottom');
        });

        it('returns landscape format (16:9)', function () {
            $result = $this->service->getFormat('landscape');

            expect($result['width'])->toBe(1920);
            expect($result['height'])->toBe(1080);
            expect($result['ratio'])->toBe('16:9');
        });

        it('returns square for unknown format', function () {
            $result = $this->service->getFormat('unknown_format');

            expect($result['width'])->toBe(1080);
            expect($result['height'])->toBe(1080);
        });

    });

    describe('getFormatNames', function () {

        it('returns all available format names', function () {
            $result = $this->service->getFormatNames();

            expect($result)->toContain('square');
            expect($result)->toContain('portrait');
            expect($result)->toContain('story');
            expect($result)->toContain('landscape');
        });

    });

    describe('getFormatsForPlatform', function () {

        it('returns formats for instagram_feed', function () {
            $result = $this->service->getFormatsForPlatform('instagram_feed');

            expect($result)->toHaveKey('square');
        });

        it('returns formats for instagram_stories', function () {
            $result = $this->service->getFormatsForPlatform('instagram_stories');

            expect($result)->toHaveKey('story');
        });

        it('returns empty array for unknown platform', function () {
            $result = $this->service->getFormatsForPlatform('unknown_platform');

            expect($result)->toBeEmpty();
        });

    });

    describe('getBestFormatForPlatform', function () {

        it('returns square for instagram_feed', function () {
            $result = $this->service->getBestFormatForPlatform('instagram_feed');

            expect($result)->toBe('square');
        });

        it('returns story for instagram_stories', function () {
            $result = $this->service->getBestFormatForPlatform('instagram_stories');

            expect($result)->toBe('story');
        });

        it('returns square for unknown platform', function () {
            $result = $this->service->getBestFormatForPlatform('unknown');

            expect($result)->toBe('square');
        });

    });

    describe('getSafeZone', function () {

        it('returns safe zone for story format', function () {
            $result = $this->service->getSafeZone('story');

            expect($result['top'])->toBe(250);
            expect($result['bottom'])->toBe(250);
        });

        it('returns zero safe zone for formats without it', function () {
            $result = $this->service->getSafeZone('square');

            expect($result['top'])->toBe(0);
            expect($result['bottom'])->toBe(0);
            expect($result['left'])->toBe(0);
            expect($result['right'])->toBe(0);
        });

    });

    describe('scaleArchetypeForFormat', function () {

        it('scales archetype for portrait format', function () {
            $archetype = [
                'text_zone' => ['x' => 80, 'y' => 200, 'width' => 400, 'height' => 680],
                'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 1080, 'height' => 1080],
            ];

            $result = $this->service->scaleArchetypeForFormat($archetype, 'portrait');

            // X should stay same (same width)
            expect($result['text_zone']['x'])->toBe(80);

            // Y should scale (1350/1080 = 1.25)
            expect($result['text_zone']['y'])->toBe(250);

            // Height should scale
            expect($result['text_zone']['height'])->toBe(850);
        });

        it('scales archetype for landscape format', function () {
            $archetype = [
                'text_zone' => ['x' => 80, 'y' => 200, 'width' => 400, 'height' => 680],
            ];

            $result = $this->service->scaleArchetypeForFormat($archetype, 'landscape');

            // Width should scale (1920/1080 = 1.78)
            expect($result['text_zone']['x'])->toBeGreaterThan(80);
            expect($result['text_zone']['width'])->toBeGreaterThan(400);
        });

    });

    describe('adjustLayersForSafeZone', function () {

        it('adjusts text layers for story safe zone', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'y' => 100, // Too high - in top safe zone
                    'height' => 50,
                ],
            ];

            $result = $this->service->adjustLayersForSafeZone($layers, 'story');

            // Should be moved below top safe zone (250)
            expect($result[0]['y'])->toBeGreaterThanOrEqual(270);
        });

        it('does not adjust background layers', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'y' => 0,
                    'height' => 1920,
                ],
            ];

            $result = $this->service->adjustLayersForSafeZone($layers, 'story');

            expect($result[0]['y'])->toBe(0);
        });

        it('does nothing for formats without safe zone', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'y' => 50,
                    'height' => 50,
                ],
            ];

            $result = $this->service->adjustLayersForSafeZone($layers, 'square');

            expect($result[0]['y'])->toBe(50);
        });

    });

    describe('scaleLayersForFormat', function () {

        it('scales layer positions for portrait format', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 100,
                    'y' => 500,
                    'width' => 880,
                    'height' => 100,
                    'properties' => ['fontSize' => 49],
                ],
            ];

            $result = $this->service->scaleLayersForFormat($layers, 'portrait');

            // X should stay same (same width)
            expect($result[0]['x'])->toBe(100);

            // Y should scale (500 * 1.25 = 625)
            expect($result[0]['y'])->toBe(625);

            // Height should scale
            expect($result[0]['height'])->toBe(125);
        });

        it('scales font size proportionally', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 0,
                    'y' => 0,
                    'width' => 100,
                    'height' => 50,
                    'properties' => ['fontSize' => 40],
                ],
            ];

            $result = $this->service->scaleLayersForFormat($layers, 'landscape');

            // Font should scale but have minimum
            expect($result[0]['properties']['fontSize'])->toBeGreaterThanOrEqual(12);
        });

    });

    describe('getRecommendedFormat', function () {

        it('recommends portrait for Instagram beauty content', function () {
            $result = $this->service->getRecommendedFormat('instagram', 'beauty');

            expect($result)->toBe('portrait');
        });

        it('falls back to platform best for other industries', function () {
            $result = $this->service->getRecommendedFormat('instagram_stories', 'technology');

            expect($result)->toBe('story');
        });

    });

});
