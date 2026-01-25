<?php

use App\Services\AI\ContrastValidator;

beforeEach(function () {
    $this->validator = new ContrastValidator();
});

describe('ContrastValidator', function () {

    describe('calculateContrastRatio', function () {

        it('calculates correct ratio for black on white', function () {
            $ratio = $this->validator->calculateContrastRatio('#000000', '#FFFFFF');

            expect($ratio)->toBeGreaterThan(20);
            expect($ratio)->toBeLessThan(22);
        });

        it('calculates correct ratio for white on black', function () {
            $ratio = $this->validator->calculateContrastRatio('#FFFFFF', '#000000');

            expect($ratio)->toBeGreaterThan(20);
            expect($ratio)->toBeLessThan(22);
        });

        it('returns 1 for same colors', function () {
            $ratio = $this->validator->calculateContrastRatio('#FF0000', '#FF0000');

            expect($ratio)->toBe(1.0);
        });

        it('handles 3-character hex codes', function () {
            $ratio = $this->validator->calculateContrastRatio('#000', '#FFF');

            expect($ratio)->toBeGreaterThan(20);
        });

    });

    describe('validateContrast', function () {

        it('returns all fields for high contrast', function () {
            $result = $this->validator->validateContrast('#000000', '#FFFFFF');

            expect($result)->toHaveKey('ratio');
            expect($result)->toHaveKey('passes_aa_normal');
            expect($result)->toHaveKey('passes_aa_large');
            expect($result)->toHaveKey('passes_aaa_normal');
            expect($result)->toHaveKey('passes_aaa_large');
            expect($result['passes_aa_normal'])->toBeTrue();
            expect($result['passes_aaa_normal'])->toBeTrue();
        });

        it('detects low contrast correctly', function () {
            // Light gray on white - poor contrast
            $result = $this->validator->validateContrast('#CCCCCC', '#FFFFFF');

            expect($result['ratio'])->toBeLessThan(4.5);
            expect($result['passes_aa_normal'])->toBeFalse();
        });

        it('passes AA for large text with lower ratio', function () {
            // ~3.5:1 ratio - passes AA for large text only
            $result = $this->validator->validateContrast('#767676', '#FFFFFF');

            expect($result['ratio'])->toBeGreaterThan(3.0);
            expect($result['passes_aa_large'])->toBeTrue();
        });

    });

    describe('suggestTextColor', function () {

        it('returns white for dark backgrounds', function () {
            $color = $this->validator->suggestTextColor('#000000');

            expect($color)->toBe('#FFFFFF');
        });

        it('returns black for light backgrounds', function () {
            $color = $this->validator->suggestTextColor('#FFFFFF');

            expect($color)->toBe('#000000');
        });

        it('returns preferred color if it has enough contrast', function () {
            $color = $this->validator->suggestTextColor('#000000', '#FFFF00');

            expect($color)->toBe('#FFFF00');
        });

        it('suggests alternative when preferred lacks contrast', function () {
            // Yellow on white - poor contrast
            $color = $this->validator->suggestTextColor('#FFFFFF', '#FFFF00');

            expect($color)->toBe('#000000');
        });

    });

    describe('hasEnoughContrast', function () {

        it('returns true for black on white', function () {
            expect($this->validator->hasEnoughContrast('#000000', '#FFFFFF'))->toBeTrue();
        });

        it('returns false for light gray on white', function () {
            expect($this->validator->hasEnoughContrast('#CCCCCC', '#FFFFFF'))->toBeFalse();
        });

        it('uses lower threshold for large text', function () {
            // ~4:1 ratio - fails normal but passes large
            $result = $this->validator->hasEnoughContrast('#757575', '#FFFFFF', true);

            expect($result)->toBeTrue();
        });

    });

    describe('validateLayers', function () {

        it('finds contrast issues in text layers', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => [
                        'fill' => '#CCCCCC',
                        'fontSize' => 48,
                    ],
                ],
            ];

            $issues = $this->validator->validateLayers($layers, '#FFFFFF');

            expect($issues)->not->toBeEmpty();
            expect($issues[0]['type'])->toBe('contrast_violation');
            expect($issues[0]['layer'])->toBe('headline');
        });

        it('ignores non-text layers', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'properties' => [
                        'fill' => '#CCCCCC',
                    ],
                ],
            ];

            $issues = $this->validator->validateLayers($layers, '#FFFFFF');

            expect($issues)->toBeEmpty();
        });

        it('returns no issues for good contrast', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => [
                        'fill' => '#000000',
                        'fontSize' => 48,
                    ],
                ],
            ];

            $issues = $this->validator->validateLayers($layers, '#FFFFFF');

            expect($issues)->toBeEmpty();
        });

        it('uses textColor for textbox layers', function () {
            $layers = [
                [
                    'name' => 'cta',
                    'type' => 'textbox',
                    'properties' => [
                        'fill' => '#FFFFFF',
                        'textColor' => '#EEEEEE', // Light gray text on white button
                        'fontSize' => 16,
                    ],
                ],
            ];

            // textColor (light gray) on white background - poor contrast
            $issues = $this->validator->validateLayers($layers, '#FFFFFF');

            expect($issues)->not->toBeEmpty();
            expect($issues[0]['layer'])->toBe('cta');
        });

    });

    describe('fixContrastIssues', function () {

        it('fixes text color for poor contrast', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => [
                        'fill' => '#CCCCCC',
                        'fontSize' => 48,
                    ],
                ],
            ];

            $fixed = $this->validator->fixContrastIssues($layers, '#FFFFFF');

            expect($fixed[0]['properties']['fill'])->toBe('#000000');
        });

        it('fixes textColor for textbox layers', function () {
            $layers = [
                [
                    'name' => 'cta',
                    'type' => 'textbox',
                    'properties' => [
                        'fill' => '#FFFFFF',
                        'textColor' => '#EEEEEE',
                        'fontSize' => 16,
                    ],
                ],
            ];

            $fixed = $this->validator->fixContrastIssues($layers, '#FFFFFF');

            expect($fixed[0]['properties']['textColor'])->toBe('#000000');
        });

        it('preserves layers with good contrast', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => [
                        'fill' => '#000000',
                        'fontSize' => 48,
                    ],
                ],
            ];

            $fixed = $this->validator->fixContrastIssues($layers, '#FFFFFF');

            expect($fixed[0]['properties']['fill'])->toBe('#000000');
        });

    });

});
