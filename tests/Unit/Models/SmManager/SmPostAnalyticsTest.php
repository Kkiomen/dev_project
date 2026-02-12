<?php

use App\Models\SmPostAnalytics;
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

describe('SmPostAnalytics', function () {

    describe('getTotalEngagement', function () {

        it('returns sum of likes, comments, shares, and saves', function () {
            $analytics = SmPostAnalytics::factory()->create([
                'likes' => 100,
                'comments' => 25,
                'shares' => 10,
                'saves' => 15,
            ]);

            expect($analytics->getTotalEngagement())->toBe(150);
        });

        it('returns 0 when all metrics are 0', function () {
            $analytics = SmPostAnalytics::factory()->create([
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'saves' => 0,
            ]);

            expect($analytics->getTotalEngagement())->toBe(0);
        });

        it('handles large numbers', function () {
            $analytics = SmPostAnalytics::factory()->create([
                'likes' => 50000,
                'comments' => 5000,
                'shares' => 2000,
                'saves' => 3000,
            ]);

            expect($analytics->getTotalEngagement())->toBe(60000);
        });
    });
});
