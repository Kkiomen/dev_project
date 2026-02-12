<?php

use App\Models\SmPerformanceScore;
use App\Models\SocialPost;
use App\Models\Brand;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmPerformanceScore', function () {

    describe('getScoreLabel', function () {

        it('returns excellent for score >= 80', function () {
            $score = SmPerformanceScore::factory()->create(['score' => 80]);
            expect($score->getScoreLabel())->toBe('excellent');

            $score2 = SmPerformanceScore::factory()->create(['score' => 100]);
            expect($score2->getScoreLabel())->toBe('excellent');

            $score3 = SmPerformanceScore::factory()->create(['score' => 95]);
            expect($score3->getScoreLabel())->toBe('excellent');
        });

        it('returns good for score >= 60 and < 80', function () {
            $score = SmPerformanceScore::factory()->create(['score' => 60]);
            expect($score->getScoreLabel())->toBe('good');

            $score2 = SmPerformanceScore::factory()->create(['score' => 79]);
            expect($score2->getScoreLabel())->toBe('good');
        });

        it('returns average for score >= 40 and < 60', function () {
            $score = SmPerformanceScore::factory()->create(['score' => 40]);
            expect($score->getScoreLabel())->toBe('average');

            $score2 = SmPerformanceScore::factory()->create(['score' => 59]);
            expect($score2->getScoreLabel())->toBe('average');
        });

        it('returns below_average for score >= 20 and < 40', function () {
            $score = SmPerformanceScore::factory()->create(['score' => 20]);
            expect($score->getScoreLabel())->toBe('below_average');

            $score2 = SmPerformanceScore::factory()->create(['score' => 39]);
            expect($score2->getScoreLabel())->toBe('below_average');
        });

        it('returns poor for score < 20', function () {
            $score = SmPerformanceScore::factory()->create(['score' => 19]);
            expect($score->getScoreLabel())->toBe('poor');

            $score2 = SmPerformanceScore::factory()->create(['score' => 0]);
            expect($score2->getScoreLabel())->toBe('poor');

            $score3 = SmPerformanceScore::factory()->create(['score' => 10]);
            expect($score3->getScoreLabel())->toBe('poor');
        });
    });
});
