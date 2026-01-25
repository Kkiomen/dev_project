<?php

use App\Services\AI\PremiumQueryBuilder;

beforeEach(function () {
    $this->service = new PremiumQueryBuilder();
});

describe('PremiumQueryBuilder', function () {

    describe('buildQuery', function () {

        it('adds industry modifiers to basic query', function () {
            // Pass usePremiumAesthetics: false to ensure deterministic behavior
            $result = $this->service->buildQuery('woman portrait', 'beauty', null, true, false);

            expect($result)->toContain('woman portrait');
            // Should contain at least one beauty industry modifier
            $hasModifier = false;
            foreach (PremiumQueryBuilder::INDUSTRY_MODIFIERS['beauty'] as $modifier) {
                if (str_contains($result, $modifier)) {
                    $hasModifier = true;
                    break;
                }
            }
            expect($hasModifier)->toBeTrue();
        });

        it('ignores lighting keywords (too complex for stock APIs)', function () {
            $result = $this->service->buildQuery('food', null, 'golden_hour');

            expect($result)->toContain('food');
            // Lighting keywords are removed - stock APIs don't understand them
            expect($result)->not->toContain('golden hour');
        });

        it('ignores authenticity keywords (too vague for stock APIs)', function () {
            $result = $this->service->buildQuery('business', null, null, true);

            // Authenticity keywords are removed - they don't help with stock APIs
            expect($result)->not->toContain('authentic');
            expect($result)->not->toContain('candid');
        });

        it('adds fallback modifier when no industry provided or detected', function () {
            // Disable premium aesthetics to test fallback behavior
            $result = $this->service->buildQuery('business', null, null, false, false);

            // Should add a fallback modifier when no industry is detected
            expect($result)->toContain('business');
            $hasFallbackModifier = false;
            foreach (PremiumQueryBuilder::FALLBACK_MODIFIERS as $modifier) {
                if (str_contains($result, $modifier)) {
                    $hasFallbackModifier = true;
                    break;
                }
            }
            expect($hasFallbackModifier)->toBeTrue();
        });

        it('auto-detects industry from query keywords', function () {
            // Disable premium aesthetics to test pure industry detection
            $result = $this->service->buildQuery('gym equipment', null, null, false, false);

            // Should detect 'fitness' from 'gym' keyword and add fitness modifier
            expect($result)->toContain('gym equipment');
            $hasFitnessModifier = false;
            foreach (PremiumQueryBuilder::INDUSTRY_MODIFIERS['fitness'] as $modifier) {
                if (str_contains($result, $modifier)) {
                    $hasFitnessModifier = true;
                    break;
                }
            }
            expect($hasFitnessModifier)->toBeTrue();
        });

    });

    describe('buildQueryWithComposition', function () {

        it('ignores composition hints (stock APIs dont understand them)', function () {
            $result = $this->service->buildQueryWithComposition('portrait', 'hero_left');

            expect($result)->toContain('portrait');
            // Composition hints removed - stock APIs are keyword-based, not composition-aware
            expect($result)->not->toContain('subject on right side');
        });

        it('adds fallback modifier regardless of archetype', function () {
            $result = $this->service->buildQueryWithComposition('portrait', 'hero_right');

            // Base query + fallback modifier, no composition hints
            expect($result)->toContain('portrait');
            // Should have at least 2 words (query + modifier)
            expect(str_word_count($result))->toBeGreaterThanOrEqual(2);
        });

        it('adds industry modifier but not composition hints', function () {
            $result = $this->service->buildQueryWithComposition(
                'woman',
                'hero_left',
                'beauty',
                'soft_diffused'
            );

            expect($result)->toContain('woman');
            // Industry modifier OR aesthetic keyword added (50% chance each)
            $hasModifier = false;
            // Check industry modifiers
            foreach (PremiumQueryBuilder::INDUSTRY_MODIFIERS['beauty'] as $modifier) {
                if (str_contains($result, $modifier)) {
                    $hasModifier = true;
                    break;
                }
            }
            // Also check aesthetic keywords
            foreach (PremiumQueryBuilder::AESTHETIC_KEYWORDS as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($result, $keyword)) {
                        $hasModifier = true;
                        break 2;
                    }
                }
            }
            expect($hasModifier)->toBeTrue();
            // But no composition hints or lighting
            expect($result)->not->toContain('subject on right side');
            expect($result)->not->toContain('soft diffused');
        });

    });

    describe('getSuggestedLighting', function () {

        it('returns soft_diffused for beauty industry', function () {
            expect($this->service->getSuggestedLighting('beauty'))->toBe('soft_diffused');
        });

        it('returns dramatic for fitness industry', function () {
            expect($this->service->getSuggestedLighting('fitness'))->toBe('dramatic');
        });

        it('returns golden_hour for gastro industry', function () {
            expect($this->service->getSuggestedLighting('gastro'))->toBe('golden_hour');
        });

        it('returns neutral for unknown industry', function () {
            expect($this->service->getSuggestedLighting('unknown'))->toBe('neutral');
            expect($this->service->getSuggestedLighting(null))->toBe('neutral');
        });

    });

    describe('buildQueryVariations', function () {

        it('returns multiple query variations', function () {
            $result = $this->service->buildQueryVariations('portrait', 'beauty', 3);

            expect($result)->toHaveCount(3);
            expect($result[0])->not->toBe($result[1]);
        });

        it('returns requested number of variations', function () {
            $result = $this->service->buildQueryVariations('food', 'gastro', 5);

            expect($result)->toHaveCount(5);
        });

    });

    describe('detectIndustryFromQuery', function () {

        it('detects fitness industry from gym keywords', function () {
            expect($this->service->detectIndustryFromQuery('new gym machine'))->toBe('fitness');
            expect($this->service->detectIndustryFromQuery('workout routine'))->toBe('fitness');
            expect($this->service->detectIndustryFromQuery('training session'))->toBe('fitness');
        });

        it('detects beauty industry from cosmetic keywords', function () {
            expect($this->service->detectIndustryFromQuery('spa treatment'))->toBe('beauty');
            expect($this->service->detectIndustryFromQuery('skincare routine'))->toBe('beauty');
        });

        it('detects gastro industry from food keywords', function () {
            expect($this->service->detectIndustryFromQuery('restaurant interior'))->toBe('gastro');
            expect($this->service->detectIndustryFromQuery('coffee shop'))->toBe('gastro');
        });

        it('returns null for unknown queries', function () {
            expect($this->service->detectIndustryFromQuery('random abstract concept'))->toBeNull();
        });

    });

    describe('enhanceQuery', function () {

        it('enhances short queries with industry modifier', function () {
            $result = $this->service->enhanceQuery('woman', 'beauty');

            expect($result)->toContain('woman');
            // Simplified query: base + one modifier = at least 2 words
            expect(str_word_count($result))->toBeGreaterThanOrEqual(2);
        });

        it('adds only industry modifiers for detailed queries', function () {
            // Note: "smiling" is removed as a cheesy adjective
            $result = $this->service->enhanceQuery('woman smiling in office environment', 'technology');

            // Core keywords preserved, "smiling" removed, "candid" added
            expect($result)->toContain('woman');
            expect($result)->toContain('office');
            expect($result)->toContain('environment');
            expect($result)->toContain('candid'); // Added to replace emotional adjective
            expect($result)->not->toContain('smiling'); // Removed cheesy adjective
        });

    });

});
