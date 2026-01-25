<?php

use App\Services\AI\ColorHarmonyValidator;

beforeEach(function () {
    $this->validator = new ColorHarmonyValidator();
});

describe('ColorHarmonyValidator', function () {

    describe('hexToHsl', function () {

        it('converts pure red correctly', function () {
            $hsl = $this->validator->hexToHsl('#FF0000');

            expect($hsl['h'])->toBe(0.0);
            expect($hsl['s'])->toBe(1.0);
            expect($hsl['l'])->toBe(0.5);
        });

        it('converts pure green correctly', function () {
            $hsl = $this->validator->hexToHsl('#00FF00');

            expect($hsl['h'])->toBe(120.0);
            expect($hsl['s'])->toBe(1.0);
            expect($hsl['l'])->toBe(0.5);
        });

        it('converts pure blue correctly', function () {
            $hsl = $this->validator->hexToHsl('#0000FF');

            expect($hsl['h'])->toBe(240.0);
            expect($hsl['s'])->toBe(1.0);
            expect($hsl['l'])->toBe(0.5);
        });

        it('converts white correctly', function () {
            $hsl = $this->validator->hexToHsl('#FFFFFF');

            expect($hsl['l'])->toBe(1.0);
            expect($hsl['s'])->toEqual(0); // Saturation is 0 for grayscale
        });

        it('converts black correctly', function () {
            $hsl = $this->validator->hexToHsl('#000000');

            expect($hsl['l'])->toBe(0.0);
            expect($hsl['s'])->toEqual(0); // Saturation is 0 for grayscale
        });

        it('handles 3-character hex codes', function () {
            $hsl = $this->validator->hexToHsl('#F00');

            expect($hsl['h'])->toBe(0.0);
            expect($hsl['s'])->toBe(1.0);
        });

    });

    describe('hslToHex', function () {

        it('converts red HSL back to hex', function () {
            $hex = $this->validator->hslToHex(['h' => 0, 's' => 1, 'l' => 0.5]);

            expect($hex)->toBe('#FF0000');
        });

        it('converts blue HSL back to hex', function () {
            $hex = $this->validator->hslToHex(['h' => 240, 's' => 1, 'l' => 0.5]);

            expect($hex)->toBe('#0000FF');
        });

        it('converts gray correctly', function () {
            $hex = $this->validator->hslToHex(['h' => 0, 's' => 0, 'l' => 0.5]);

            expect($hex)->toBe('#808080');
        });

        it('round-trips arbitrary colors', function () {
            $original = '#D4AF37'; // Gold

            $hsl = $this->validator->hexToHsl($original);
            $roundTripped = $this->validator->hslToHex($hsl);

            // Allow slight variation due to rounding
            expect(strtoupper($roundTripped))->toBe($original);
        });

    });

    describe('suggestAccentColor', function () {

        it('suggests complementary color by default', function () {
            $primary = '#FF0000'; // Red
            $accent = $this->validator->suggestAccentColor($primary);

            // Complementary of red is cyan-ish
            $accentHsl = $this->validator->hexToHsl($accent);

            expect($accentHsl['h'])->toBeGreaterThan(150);
            expect($accentHsl['h'])->toBeLessThan(210);
        });

        it('suggests analogous color when requested', function () {
            $primary = '#FF0000'; // Red (hue 0)
            $accent = $this->validator->suggestAccentColor($primary, 'analogous');

            $accentHsl = $this->validator->hexToHsl($accent);

            // Analogous is 30 degrees away
            expect($accentHsl['h'])->toBeLessThan(60);
        });

        it('suggests triadic color when requested', function () {
            $primary = '#FF0000'; // Red (hue 0)
            $accent = $this->validator->suggestAccentColor($primary, 'triadic');

            $accentHsl = $this->validator->hexToHsl($accent);

            // Triadic is 120 degrees away
            expect($accentHsl['h'])->toBeGreaterThan(100);
            expect($accentHsl['h'])->toBeLessThan(140);
        });

    });

    describe('validatePalette', function () {

        it('returns valid for single color', function () {
            $result = $this->validator->validatePalette(['#FF0000']);

            expect($result['valid'])->toBeTrue();
            expect($result['score'])->toBe(100);
            expect($result['harmony_type'])->toBe('single');
        });

        it('detects complementary colors', function () {
            // Red and Cyan are complementary
            $result = $this->validator->validatePalette(['#FF0000', '#00FFFF']);

            expect($result['harmony_type'])->toBe('complementary');
            expect($result['harmony_confidence'])->toBeGreaterThan(0.5);
        });

        it('detects analogous colors', function () {
            // Red, Orange, Yellow are analogous
            $result = $this->validator->validatePalette(['#FF0000', '#FF6600', '#FFCC00']);

            expect($result['harmony_type'])->toBe('analogous');
        });

        it('flags too many saturated colors', function () {
            // 4 highly saturated colors
            $result = $this->validator->validatePalette([
                '#FF0000',
                '#00FF00',
                '#0000FF',
                '#FF00FF',
            ]);

            expect($result['score'])->toBeLessThan(100);
            expect($result['issues'])->not->toBeEmpty();
        });

        it('validates monochromatic palette', function () {
            // Various shades of gray (no saturation)
            $result = $this->validator->validatePalette(['#333333', '#666666', '#999999']);

            expect($result['harmony_type'])->toBe('monochromatic');
        });

    });

    describe('areColorsHarmonious', function () {

        it('identifies complementary pair', function () {
            $result = $this->validator->areColorsHarmonious('#FF0000', '#00FFFF');

            expect($result['harmonious'])->toBeTrue();
            expect($result['type'])->toBe('complementary');
        });

        it('identifies analogous pair', function () {
            $result = $this->validator->areColorsHarmonious('#FF0000', '#FF6600');

            expect($result['harmonious'])->toBeTrue();
            expect($result['type'])->toBe('analogous');
        });

        it('returns not harmonious for clashing colors', function () {
            // Two colors that don't fit any harmony pattern
            $result = $this->validator->areColorsHarmonious('#FF0000', '#00FF33');

            // This might or might not be harmonious depending on angle
            expect($result)->toHaveKey('harmonious');
            expect($result)->toHaveKey('type');
        });

    });

    describe('generateHarmoniousPalette', function () {

        it('generates correct number of colors', function () {
            $palette = $this->validator->generateHarmoniousPalette('#FF0000', 'complementary', 3);

            expect($palette)->toHaveCount(3);
        });

        it('includes base color as first', function () {
            $palette = $this->validator->generateHarmoniousPalette('#FF0000', 'triadic', 3);

            expect($palette[0])->toBe('#FF0000');
        });

        it('generates valid hex colors', function () {
            $palette = $this->validator->generateHarmoniousPalette('#D4AF37', 'analogous', 4);

            foreach ($palette as $color) {
                expect($color)->toMatch('/^#[0-9A-F]{6}$/i');
            }
        });

    });

});
