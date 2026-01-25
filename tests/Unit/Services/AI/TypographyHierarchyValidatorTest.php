<?php

use App\Services\AI\TypographyHierarchyValidator;

beforeEach(function () {
    $this->validator = new TypographyHierarchyValidator();
});

describe('TypographyHierarchyValidator', function () {

    describe('validateHierarchy', function () {

        it('returns no issues for correct hierarchy', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => ['fontSize' => 48],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'properties' => ['fontSize' => 20],
                ],
                [
                    'name' => 'cta_button',
                    'type' => 'textbox',
                    'properties' => ['fontSize' => 16],
                ],
            ];

            $issues = $this->validator->validateHierarchy($layers);

            expect($issues)->toBeEmpty();
        });

        it('detects headline smaller than subtext', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => ['fontSize' => 18],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'properties' => ['fontSize' => 24],
                ],
            ];

            $issues = $this->validator->validateHierarchy($layers);

            expect($issues)->not->toBeEmpty();
            expect($issues[0]['type'])->toBe('hierarchy_violation');
            expect($issues[0]['message'])->toContain('Headline');
            expect($issues[0]['message'])->toContain('subtext');
        });

        it('detects headline equal to subtext', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => ['fontSize' => 24],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'properties' => ['fontSize' => 24],
                ],
            ];

            $issues = $this->validator->validateHierarchy($layers);

            expect($issues)->not->toBeEmpty();
        });

        it('finds layers by name variations', function () {
            $layers = [
                [
                    'name' => 'main_title',
                    'type' => 'text',
                    'properties' => ['fontSize' => 20],
                ],
                [
                    'name' => 'subtitle_text',
                    'type' => 'text',
                    'properties' => ['fontSize' => 24],
                ],
            ];

            $issues = $this->validator->validateHierarchy($layers);

            // Should detect title < subtitle
            expect($issues)->not->toBeEmpty();
        });

        it('handles missing layers gracefully', function () {
            $layers = [
                [
                    'name' => 'background',
                    'type' => 'rectangle',
                    'properties' => ['fill' => '#000000'],
                ],
            ];

            $issues = $this->validator->validateHierarchy($layers);

            expect($issues)->toBeEmpty();
        });

    });

    describe('fixHierarchy', function () {

        it('fixes headline to be larger than subtext', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => ['fontSize' => 18],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'properties' => ['fontSize' => 24],
                ],
                [
                    'name' => 'cta',
                    'type' => 'textbox',
                    'properties' => ['fontSize' => 16],
                ],
            ];

            $fixed = $this->validator->fixHierarchy($layers);

            $headline = collect($fixed)->firstWhere('name', 'headline');
            $subtext = collect($fixed)->firstWhere('name', 'subtext');

            expect($headline['properties']['fontSize'])->toBeGreaterThan($subtext['properties']['fontSize']);
        });

        it('preserves other properties when fixing', function () {
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'x' => 100,
                    'y' => 200,
                    'properties' => [
                        'fontSize' => 18,
                        'fill' => '#FFFFFF',
                        'fontFamily' => 'Montserrat',
                    ],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'properties' => ['fontSize' => 24],
                ],
            ];

            $fixed = $this->validator->fixHierarchy($layers);

            $headline = collect($fixed)->firstWhere('name', 'headline');

            expect($headline['x'])->toBe(100);
            expect($headline['y'])->toBe(200);
            expect($headline['properties']['fill'])->toBe('#FFFFFF');
            expect($headline['properties']['fontFamily'])->toBe('Montserrat');
        });

        it('handles already correct hierarchy', function () {
            // With maxHeadlineSize=61, headlines are capped to prevent visual dominance
            // 61 / 20 = 3.05x - still maintains strong hierarchy
            $layers = [
                [
                    'name' => 'headline',
                    'type' => 'text',
                    'properties' => ['fontSize' => 70],
                ],
                [
                    'name' => 'subtext',
                    'type' => 'text',
                    'properties' => ['fontSize' => 20],
                ],
            ];

            $fixed = $this->validator->fixHierarchy($layers);

            $headline = collect($fixed)->firstWhere('name', 'headline');
            $subtext = collect($fixed)->firstWhere('name', 'subtext');

            // 70 gets capped to maxHeadlineSize=61 (snaps to 61 on modular scale)
            // 20 stays 20 (already on scale)
            expect($headline['properties']['fontSize'])->toBe(61);
            expect($subtext['properties']['fontSize'])->toBe(20);
        });

    });

    describe('getRecommendedSizes', function () {

        it('returns sizes based on CTA size', function () {
            $sizes = $this->validator->getRecommendedSizes(16);

            expect($sizes['cta'])->toBe(16);
            expect($sizes['subtext'])->toBe(20);
            // With new 3.5x ratio: 20 * 3.5 = 70
            expect($sizes['headline'])->toBe(70);
        });

        it('maintains hierarchy ratios', function () {
            $sizes = $this->validator->getRecommendedSizes(20);

            expect($sizes['headline'])->toBeGreaterThan($sizes['subtext']);
            expect($sizes['subtext'])->toBeGreaterThanOrEqual($sizes['cta']);
        });

    });

});
