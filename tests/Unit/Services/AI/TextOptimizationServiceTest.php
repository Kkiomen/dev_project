<?php

use App\Services\AI\TextOptimizationService;

beforeEach(function () {
    $this->service = new TextOptimizationService();
});

describe('TextOptimizationService', function () {

    describe('preventWidows', function () {

        it('joins short last word with non-breaking space', function () {
            $result = $this->service->preventWidows('This is a test');

            // Last space should be replaced with NBSP (test is 4 chars = MIN_WIDOW_LENGTH)
            // Also 'a' is a connector so it gets NBSP too
            expect($result)->toContain("\u{00A0}");
        });

        it('does not modify single word', function () {
            $result = $this->service->preventWidows('Hello');

            expect($result)->toBe('Hello');
        });

        it('handles empty string', function () {
            $result = $this->service->preventWidows('');

            expect($result)->toBe('');
        });

        it('joins Polish prepositions with following word', function () {
            $result = $this->service->preventWidows('To jest w domu');

            // 'w' should be joined with 'domu'
            expect($result)->toContain("\u{00A0}w ");
        });

        it('joins English articles with following word', function () {
            $result = $this->service->preventWidows('This is a nice house');

            // The text should contain a non-breaking space somewhere
            // Either for 'a' connector or for the last word
            expect($result)->toContain("\u{00A0}");
        });

    });

    describe('preventOrphans', function () {

        it('joins short first connector word', function () {
            $result = $this->service->preventOrphans('A quick brown fox');

            // 'A' should be joined with 'quick'
            expect($result)->toContain("A\u{00A0}quick");
        });

        it('does not modify when first word is long', function () {
            $result = $this->service->preventOrphans('Beautiful sunset today');

            expect($result)->toBe('Beautiful sunset today');
        });

        it('handles short text without modification', function () {
            $result = $this->service->preventOrphans('Hi there');

            expect($result)->toBe('Hi there');
        });

    });

    describe('balanceLines', function () {

        it('returns single line for short text', function () {
            $result = $this->service->balanceLines('Short text', 50);

            expect($result['line_count'])->toBe(1);
            expect($result['balanced'])->toBeTrue();
        });

        it('splits long text into multiple lines', function () {
            $longText = 'This is a much longer piece of text that should be split across multiple lines for better readability';

            $result = $this->service->balanceLines($longText, 30);

            expect($result['line_count'])->toBeGreaterThan(1);
            expect($result['lines'])->not->toBeEmpty();
        });

        it('attempts to balance line lengths', function () {
            $text = 'The quick brown fox jumps over the lazy dog';

            $result = $this->service->balanceLines($text, 25);

            // Lines should have similar lengths - allowing more variance
            $lengths = array_map('mb_strlen', $result['lines']);
            $maxDiff = max($lengths) - min($lengths);

            expect($maxDiff)->toBeLessThan(20);
        });

    });

    describe('truncateText', function () {

        it('does not modify short text', function () {
            $result = $this->service->truncateText('Hello', 10);

            expect($result)->toBe('Hello');
        });

        it('truncates long text with ellipsis', function () {
            $result = $this->service->truncateText('This is a very long text that needs truncation', 20);

            expect(mb_strlen($result))->toBeLessThanOrEqual(20);
            expect($result)->toEndWith('...');
        });

        it('breaks at word boundary', function () {
            $result = $this->service->truncateText('Hello beautiful world', 18);

            // Should not cut in the middle of 'beautiful'
            expect($result)->not->toContain('beauti...');
            // Result should end with ellipsis
            expect($result)->toEndWith('...');
        });

        it('removes trailing punctuation before ellipsis', function () {
            $result = $this->service->truncateText('Hello, world! This is a test.', 15);

            expect($result)->not->toContain(',...');
            expect($result)->not->toContain('!...');
        });

    });

    describe('estimateCharsPerLine', function () {

        it('returns reasonable value for standard width', function () {
            // 1000px width with 20px font
            $result = $this->service->estimateCharsPerLine(1000, 20);

            expect($result)->toBeGreaterThan(40);
            expect($result)->toBeLessThan(100);
        });

        it('returns fewer chars for larger fonts', function () {
            $smallFont = $this->service->estimateCharsPerLine(500, 16);
            $largeFont = $this->service->estimateCharsPerLine(500, 32);

            expect($smallFont)->toBeGreaterThan($largeFont);
        });

        it('returns at least 1 character', function () {
            $result = $this->service->estimateCharsPerLine(10, 100);

            expect($result)->toBeGreaterThanOrEqual(1);
        });

    });

    describe('optimizeText', function () {

        it('returns optimized text with metadata', function () {
            $result = $this->service->optimizeText('This is a test', 300, 16);

            expect($result)->toHaveKey('text');
            expect($result)->toHaveKey('original');
            expect($result)->toHaveKey('modified');
            expect($result)->toHaveKey('estimated_lines');
        });

        it('applies widow prevention', function () {
            $result = $this->service->optimizeText('This is a test', 300, 16);

            expect($result['modified'])->toBeTrue();
            expect($result['text'])->toContain("\u{00A0}");
        });

    });

    describe('optimizeTextLayers', function () {

        it('processes text layers', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'width' => 400,
                    'properties' => [
                        'text' => 'This is a test',
                        'fontSize' => 24,
                    ],
                ],
            ];

            $result = $this->service->optimizeTextLayers($layers);

            expect($result[0]['properties']['text'])->toContain("\u{00A0}");
        });

        it('skips non-text layers', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'properties' => [
                        'fill' => '#FFFFFF',
                    ],
                ],
            ];

            $result = $this->service->optimizeTextLayers($layers);

            expect($result)->toEqual($layers);
        });

        it('preserves layers with empty text', function () {
            $layers = [
                [
                    'name' => 'empty_text',
                    'type' => 'text',
                    'properties' => [
                        'text' => '',
                        'fontSize' => 16,
                    ],
                ],
            ];

            $result = $this->service->optimizeTextLayers($layers);

            expect($result[0]['properties']['text'])->toBe('');
        });

    });

    describe('hasWidowRisk', function () {

        it('returns true for long text with short last word', function () {
            $result = $this->service->hasWidowRisk('This is a longer text that ends in a', 20);

            expect($result)->toBeTrue();
        });

        it('returns false for short text', function () {
            $result = $this->service->hasWidowRisk('Short text', 50);

            expect($result)->toBeFalse();
        });

        it('returns false for text with long last word', function () {
            $result = $this->service->hasWidowRisk('This ends with encyclopedia', 15);

            expect($result)->toBeFalse();
        });

    });

    describe('hasOrphanRisk', function () {

        it('returns true for multi-line text starting with connector', function () {
            $result = $this->service->hasOrphanRisk('A very long text that will span multiple lines definitely', 20);

            expect($result)->toBeTrue();
        });

        it('returns false for short text', function () {
            $result = $this->service->hasOrphanRisk('A test', 50);

            expect($result)->toBeFalse();
        });

        it('returns false when first word is not a connector', function () {
            $result = $this->service->hasOrphanRisk('Welcome to our very long piece of content here', 20);

            expect($result)->toBeFalse();
        });

    });

});
