<?php

use App\Models\Brand;
use App\Services\AI\DesignTokensService;

beforeEach(function () {
    $this->service = new DesignTokensService();
});

describe('DesignTokensService', function () {

    describe('snapFontSize', function () {

        it('snaps to nearest modular scale value', function () {
            // Scale: 13, 16, 20, 25, 31, 39, 49, 61
            expect($this->service->snapFontSize(12))->toBe(13);
            expect($this->service->snapFontSize(14))->toBe(13);
            expect($this->service->snapFontSize(15))->toBe(16);
            expect($this->service->snapFontSize(16))->toBe(16);
            expect($this->service->snapFontSize(18))->toBe(16);
            expect($this->service->snapFontSize(19))->toBe(20);
            expect($this->service->snapFontSize(22))->toBe(20);
            expect($this->service->snapFontSize(23))->toBe(25);
            expect($this->service->snapFontSize(28))->toBe(25);
            expect($this->service->snapFontSize(30))->toBe(31);
            expect($this->service->snapFontSize(35))->toBe(31);
            expect($this->service->snapFontSize(36))->toBe(39);
            expect($this->service->snapFontSize(44))->toBe(39);
            expect($this->service->snapFontSize(45))->toBe(49);
            expect($this->service->snapFontSize(55))->toBe(49);
            expect($this->service->snapFontSize(56))->toBe(61);
            // With extended scale: 5xl (76) and 6xl (95)
            expect($this->service->snapFontSize(68))->toBe(61);
            expect($this->service->snapFontSize(69))->toBe(76);  // 5xl
            expect($this->service->snapFontSize(85))->toBe(76);
            expect($this->service->snapFontSize(86))->toBe(95);  // 6xl
            expect($this->service->snapFontSize(100))->toBe(95); // 6xl is closest
        });

        it('handles exact scale values', function () {
            expect($this->service->snapFontSize(13))->toBe(13);
            expect($this->service->snapFontSize(16))->toBe(16);
            expect($this->service->snapFontSize(20))->toBe(20);
            expect($this->service->snapFontSize(25))->toBe(25);
            expect($this->service->snapFontSize(31))->toBe(31);
            expect($this->service->snapFontSize(39))->toBe(39);
            expect($this->service->snapFontSize(49))->toBe(49);
            expect($this->service->snapFontSize(61))->toBe(61);
            expect($this->service->snapFontSize(76))->toBe(76);  // 5xl
            expect($this->service->snapFontSize(95))->toBe(95);  // 6xl
        });

    });

    describe('snapSpacing', function () {

        it('snaps to nearest spacing scale value', function () {
            // Scale: 8, 16, 24, 32, 48, 64, 80, 96, 120
            expect($this->service->snapSpacing(5))->toBe(8);
            expect($this->service->snapSpacing(10))->toBe(8);
            expect($this->service->snapSpacing(13))->toBe(16);
            expect($this->service->snapSpacing(20))->toBe(16);
            expect($this->service->snapSpacing(21))->toBe(24);
            expect($this->service->snapSpacing(28))->toBe(24);
            expect($this->service->snapSpacing(29))->toBe(32);
            expect($this->service->snapSpacing(40))->toBe(32);
            expect($this->service->snapSpacing(41))->toBe(48);
            expect($this->service->snapSpacing(100))->toBe(96);
            expect($this->service->snapSpacing(110))->toBe(120);
        });

    });

    describe('snapCornerRadius', function () {

        it('snaps to allowed corner radius values', function () {
            // Allowed: 0, 8, 12, 16, 24, 500
            expect($this->service->snapCornerRadius(0))->toBe(0);
            expect($this->service->snapCornerRadius(3))->toBe(0);
            expect($this->service->snapCornerRadius(5))->toBe(8);
            expect($this->service->snapCornerRadius(8))->toBe(8);
            expect($this->service->snapCornerRadius(10))->toBe(8);
            expect($this->service->snapCornerRadius(11))->toBe(12);
            expect($this->service->snapCornerRadius(14))->toBe(12);
            expect($this->service->snapCornerRadius(15))->toBe(16);
            expect($this->service->snapCornerRadius(20))->toBe(16);
            expect($this->service->snapCornerRadius(21))->toBe(24);
            expect($this->service->snapCornerRadius(25))->toBe(24);
            expect($this->service->snapCornerRadius(100))->toBe(24);
            expect($this->service->snapCornerRadius(300))->toBe(500);
        });

        it('snaps high values to pill shape (500)', function () {
            expect($this->service->snapCornerRadius(999))->toBe(500);
            expect($this->service->snapCornerRadius(500))->toBe(500);
            expect($this->service->snapCornerRadius(400))->toBe(500);
        });

    });

    describe('snapStrokeWidth', function () {

        it('snaps to allowed stroke width values', function () {
            // Allowed: 1, 2, 3, 4, 6, 8
            // Note: floats are cast to int first
            expect($this->service->snapStrokeWidth(1))->toBe(1);
            expect($this->service->snapStrokeWidth(1.5))->toBe(1); // (int)1.5 = 1
            expect($this->service->snapStrokeWidth(2))->toBe(2);
            expect($this->service->snapStrokeWidth(2.4))->toBe(2); // (int)2.4 = 2
            expect($this->service->snapStrokeWidth(3))->toBe(3);
            expect($this->service->snapStrokeWidth(4))->toBe(4);
            expect($this->service->snapStrokeWidth(5))->toBe(4); // 5 is closest to 4 or 6, picks 4
            expect($this->service->snapStrokeWidth(6))->toBe(6);
            expect($this->service->snapStrokeWidth(7))->toBe(6); // 7 is closest to 6 or 8
            expect($this->service->snapStrokeWidth(8))->toBe(8);
            expect($this->service->snapStrokeWidth(10))->toBe(8);
        });

    });

    describe('snapLayerToTokens', function () {

        it('snaps all relevant properties', function () {
            $layer = [
                'type' => 'text',
                'properties' => [
                    'fontSize' => 45,
                    'cornerRadius' => 15,
                    'strokeWidth' => 5,
                    'padding' => 20,
                    'fill' => '#FF0000',
                ],
            ];

            $result = $this->service->snapLayerToTokens($layer);

            expect($result['properties']['fontSize'])->toBe(49);
            expect($result['properties']['cornerRadius'])->toBe(16);
            expect($result['properties']['strokeWidth'])->toBe(4);
            expect($result['properties']['padding'])->toBe(16);
            expect($result['properties']['fill'])->toBe('#FF0000');
        });

        it('preserves properties without snapping rules', function () {
            $layer = [
                'type' => 'text',
                'name' => 'test',
                'x' => 100,
                'y' => 200,
                'properties' => [
                    'text' => 'Hello World',
                    'fontFamily' => 'Montserrat',
                    'align' => 'center',
                ],
            ];

            $result = $this->service->snapLayerToTokens($layer);

            expect($result['name'])->toBe('test');
            expect($result['x'])->toBe(100);
            expect($result['y'])->toBe(200);
            expect($result['properties']['text'])->toBe('Hello World');
            expect($result['properties']['fontFamily'])->toBe('Montserrat');
            expect($result['properties']['align'])->toBe('center');
        });

    });

    describe('snapAllLayersToTokens', function () {

        it('snaps multiple layers', function () {
            $layers = [
                ['properties' => ['fontSize' => 45]],
                ['properties' => ['fontSize' => 18, 'cornerRadius' => 10]],
            ];

            $result = $this->service->snapAllLayersToTokens($layers);

            expect($result[0]['properties']['fontSize'])->toBe(49);
            expect($result[1]['properties']['fontSize'])->toBe(16);
            expect($result[1]['properties']['cornerRadius'])->toBe(8);
        });

    });

    describe('getTokensForBrand', function () {

        it('returns default colors when no brand provided', function () {
            $tokens = $this->service->getTokensForBrand(null);

            expect($tokens['colors'])->toHaveKey('primary');
            expect($tokens['colors'])->toHaveKey('secondary');
            expect($tokens['colors'])->toHaveKey('accent');
            expect($tokens['fontSizes'])->toBe(DesignTokensService::FONT_SCALE);
            expect($tokens['spacing'])->toBe(DesignTokensService::SPACING_SCALE);
            expect($tokens['cornerRadius'])->toBe(DesignTokensService::CORNER_RADIUS);
            expect($tokens['strokeWidth'])->toBe(DesignTokensService::STROKE_WIDTH);
        });

    });

    describe('validateColor', function () {

        it('validates colors case-insensitively', function () {
            $allowed = ['#FF0000', '#00FF00', '#0000FF'];

            expect($this->service->validateColor('#ff0000', $allowed))->toBeTrue();
            expect($this->service->validateColor('#FF0000', $allowed))->toBeTrue();
            expect($this->service->validateColor('#FFFFFF', $allowed))->toBeFalse();
        });

    });

    describe('getTokensForPrompt', function () {

        it('returns formatted string for AI prompt', function () {
            $result = $this->service->getTokensForPrompt(null);

            expect($result)->toContain('DESIGN TOKENS');
            expect($result)->toContain('FONT SIZES');
            expect($result)->toContain('SPACING');
            expect($result)->toContain('CORNER RADIUS');
            expect($result)->toContain('BRAND COLORS');
        });

    });

    describe('calculateLineHeight', function () {

        it('calculates line height snapped to baseline grid', function () {
            // For 49px headline with 1.2 multiplier = 58.8, snapped to 64 (8*8)
            // 64 / 49 = 1.306
            $result = $this->service->calculateLineHeight(49, 'headline_normal');

            expect($result)->toBeGreaterThan(1.0);
            expect($result)->toBeLessThan(2.0);
        });

        it('returns different values for different contexts', function () {
            $tight = $this->service->calculateLineHeight(24, 'headline_tight');
            $normal = $this->service->calculateLineHeight(24, 'body_normal');
            $loose = $this->service->calculateLineHeight(24, 'body_loose');

            expect($tight)->toBeLessThan($normal);
            expect($normal)->toBeLessThan($loose);
        });

        it('uses body_normal as default context', function () {
            $result = $this->service->calculateLineHeight(16);
            $explicit = $this->service->calculateLineHeight(16, 'body_normal');

            expect($result)->toBe($explicit);
        });

    });

    describe('calculateTracking', function () {

        it('returns positive tracking for small fonts', function () {
            $result = $this->service->calculateTracking(13);

            expect($result)->toBeGreaterThan(0);
        });

        it('returns zero tracking for medium fonts', function () {
            $result = $this->service->calculateTracking(20);

            expect($result)->toBe(0.0);
        });

        it('returns negative tracking for large fonts', function () {
            $result = $this->service->calculateTracking(49);

            expect($result)->toBeLessThan(0);
        });

    });

    describe('getFontScaleKey', function () {

        it('returns correct scale key for exact values', function () {
            expect($this->service->getFontScaleKey(13))->toBe('xs');
            expect($this->service->getFontScaleKey(16))->toBe('sm');
            expect($this->service->getFontScaleKey(20))->toBe('md');
            expect($this->service->getFontScaleKey(25))->toBe('lg');
            expect($this->service->getFontScaleKey(31))->toBe('xl');
            expect($this->service->getFontScaleKey(39))->toBe('2xl');
            expect($this->service->getFontScaleKey(49))->toBe('3xl');
            expect($this->service->getFontScaleKey(61))->toBe('4xl');
        });

        it('returns closest scale key for in-between values', function () {
            expect($this->service->getFontScaleKey(14))->toBe('xs');
            expect($this->service->getFontScaleKey(15))->toBe('sm');
            expect($this->service->getFontScaleKey(18))->toBe('sm');
            expect($this->service->getFontScaleKey(22))->toBe('md');
        });

    });

    describe('getLineHeightContext', function () {

        it('returns headline_normal for headline layers', function () {
            expect($this->service->getLineHeightContext('headline', 'text'))->toBe('headline_normal');
            expect($this->service->getLineHeightContext('Main Title', 'text'))->toBe('headline_normal');
        });

        it('returns body_tight for subtext layers', function () {
            expect($this->service->getLineHeightContext('subtext', 'text'))->toBe('body_tight');
            expect($this->service->getLineHeightContext('subtitle', 'text'))->toBe('body_tight');
        });

        it('returns headline_tight for CTA buttons', function () {
            expect($this->service->getLineHeightContext('cta_button', 'textbox'))->toBe('headline_tight');
            expect($this->service->getLineHeightContext('any', 'textbox'))->toBe('headline_tight');
        });

        it('returns body_normal as default', function () {
            expect($this->service->getLineHeightContext('random_name', 'text'))->toBe('body_normal');
        });

    });

    describe('applyVerticalRhythm', function () {

        it('applies line height and tracking to text layer', function () {
            $layer = [
                'name' => 'headline',
                'type' => 'text',
                'properties' => [
                    'text' => 'Test',
                    'fontSize' => 49,
                ],
            ];

            $result = $this->service->applyVerticalRhythm($layer);

            expect($result['properties'])->toHaveKey('lineHeight');
            expect($result['properties'])->toHaveKey('letterSpacing');
            expect($result['properties']['lineHeight'])->toBeGreaterThan(1.0);
        });

        it('does not modify non-text layers', function () {
            $layer = [
                'name' => 'background',
                'type' => 'rectangle',
                'properties' => [
                    'fill' => '#000000',
                ],
            ];

            $result = $this->service->applyVerticalRhythm($layer);

            expect($result['properties'])->not->toHaveKey('lineHeight');
            expect($result['properties'])->not->toHaveKey('letterSpacing');
        });

        it('preserves existing custom line height', function () {
            $layer = [
                'name' => 'text',
                'type' => 'text',
                'properties' => [
                    'text' => 'Test',
                    'fontSize' => 24,
                    'lineHeight' => 1.8, // Custom value
                ],
            ];

            $result = $this->service->applyVerticalRhythm($layer);

            expect($result['properties']['lineHeight'])->toBe(1.8);
        });

    });

    describe('applyVerticalRhythmToLayers', function () {

        it('applies vertical rhythm to all text layers', function () {
            $layers = [
                ['name' => 'background', 'type' => 'rectangle', 'properties' => []],
                ['name' => 'headline', 'type' => 'text', 'properties' => ['fontSize' => 49]],
                ['name' => 'cta', 'type' => 'textbox', 'properties' => ['fontSize' => 16]],
            ];

            $result = $this->service->applyVerticalRhythmToLayers($layers);

            // Rectangle should be unchanged
            expect($result[0]['properties'])->not->toHaveKey('lineHeight');

            // Text layers should have rhythm applied
            expect($result[1]['properties'])->toHaveKey('lineHeight');
            expect($result[2]['properties'])->toHaveKey('lineHeight');
        });

    });

});
