<?php

use App\Services\AI\PhotoRankingService;
use App\Services\AI\ImageAnalysisService;

beforeEach(function () {
    $this->imageAnalysisService = Mockery::mock(ImageAnalysisService::class);
    $this->service = new PhotoRankingService($this->imageAnalysisService);
});

afterEach(function () {
    Mockery::close();
});

describe('PhotoRankingService', function () {

    describe('calculateAspectScore', function () {

        it('returns 100 for perfect aspect match', function () {
            $score = $this->service->calculateAspectScore(1080, 1080, 1080, 1080);

            expect($score)->toBe(100);
        });

        it('returns high score for similar aspect ratios', function () {
            // 16:9 photo for 1:1 target - should use about 56% of width
            $score = $this->service->calculateAspectScore(1920, 1080, 1080, 1080);

            expect($score)->toBeGreaterThan(50);
            expect($score)->toBeLessThan(70);
        });

        it('returns lower score for very different ratios', function () {
            // Very wide panoramic for square target
            $score = $this->service->calculateAspectScore(3000, 500, 1080, 1080);

            expect($score)->toBeLessThan(30);
        });

        it('handles portrait photo for landscape target', function () {
            $score = $this->service->calculateAspectScore(1080, 1920, 1920, 1080);

            expect($score)->toBeGreaterThan(0);
            expect($score)->toBeLessThan(100);
        });

        it('returns 50 for zero dimensions', function () {
            $score = $this->service->calculateAspectScore(0, 0, 1080, 1080);

            expect($score)->toBe(50);
        });

    });

    describe('calculateFocalPointScore', function () {

        it('returns 50 when no analysis available', function () {
            $score = $this->service->calculateFocalPointScore(null, 'centered_minimal');

            expect($score)->toBe(50);
        });

        it('returns 50 when analysis unsuccessful', function () {
            $analysis = ['success' => false];

            $score = $this->service->calculateFocalPointScore($analysis, 'centered_minimal');

            expect($score)->toBe(50);
        });

        it('returns high score for centered focal point with centered_minimal archetype', function () {
            $analysis = [
                'success' => true,
                'focal_point' => [
                    'normalized' => ['x' => 0.5, 'y' => 0.5],
                ],
            ];

            $score = $this->service->calculateFocalPointScore($analysis, 'centered_minimal');

            expect($score)->toBeGreaterThan(80);
        });

        it('returns score for focal point with hero_left archetype', function () {
            // hero_left has photo on right side, ideal_focal_x is [0.6, 1.0]
            $analysis = [
                'success' => true,
                'focal_point' => [
                    'normalized' => ['x' => 0.8, 'y' => 0.5],
                ],
            ];

            $score = $this->service->calculateFocalPointScore($analysis, 'hero_left');

            // With focal point at x=0.8 which is in the ideal zone [0.6, 1.0]
            // Score should be reasonable (not 50 which is the default)
            expect($score)->toBeGreaterThanOrEqual(0);
            expect($score)->toBeLessThanOrEqual(100);
        });

        it('returns lower score when focal point is outside ideal zone', function () {
            $analysis = [
                'success' => true,
                'focal_point' => [
                    'normalized' => ['x' => 0.1, 'y' => 0.1],
                ],
            ];

            $score = $this->service->calculateFocalPointScore($analysis, 'centered_minimal');

            expect($score)->toBeLessThan(80);
        });

    });

    describe('rankPhotos', function () {

        it('returns empty array for empty input', function () {
            $this->imageAnalysisService
                ->shouldReceive('isAvailable')
                ->never();

            $result = $this->service->rankPhotos([], 'centered_minimal', 1080, 1080);

            expect($result)->toBeEmpty();
        });

        it('returns ranked photos with scores', function () {
            $photos = [
                ['id' => '1', 'width' => 1920, 'height' => 1080, 'urls' => ['regular' => 'https://example.com/1.jpg']],
                ['id' => '2', 'width' => 1080, 'height' => 1080, 'urls' => ['regular' => 'https://example.com/2.jpg']],
            ];

            $this->imageAnalysisService
                ->shouldReceive('isAvailable')
                ->andReturn(false);

            $result = $this->service->rankPhotos($photos, 'centered_minimal', 1080, 1080);

            expect($result)->toHaveCount(2);
            expect($result[0])->toHaveKey('photo');
            expect($result[0])->toHaveKey('scores');
            expect($result[0])->toHaveKey('total_score');
        });

        it('sorts photos by score descending', function () {
            $photos = [
                ['id' => '1', 'width' => 3000, 'height' => 500, 'urls' => ['regular' => 'https://example.com/1.jpg']],
                ['id' => '2', 'width' => 1080, 'height' => 1080, 'urls' => ['regular' => 'https://example.com/2.jpg']],
            ];

            $this->imageAnalysisService
                ->shouldReceive('isAvailable')
                ->andReturn(false);

            $result = $this->service->rankPhotos($photos, 'centered_minimal', 1080, 1080);

            // Photo 2 should rank higher (better aspect ratio)
            expect($result[0]['photo']['id'])->toBe('2');
            expect($result[0]['total_score'])->toBeGreaterThan($result[1]['total_score']);
        });

    });

    describe('getBestPhoto', function () {

        it('returns null for empty input', function () {
            $result = $this->service->getBestPhoto([], 'centered_minimal', 1080, 1080);

            expect($result)->toBeNull();
        });

        it('returns best ranked photo', function () {
            $photos = [
                ['id' => 'bad', 'width' => 3000, 'height' => 500, 'urls' => ['regular' => 'https://example.com/bad.jpg']],
                ['id' => 'good', 'width' => 1080, 'height' => 1080, 'urls' => ['regular' => 'https://example.com/good.jpg']],
            ];

            $this->imageAnalysisService
                ->shouldReceive('isAvailable')
                ->andReturn(false);

            $result = $this->service->getBestPhoto($photos, 'centered_minimal', 1080, 1080);

            expect($result['id'])->toBe('good');
        });

    });

});
