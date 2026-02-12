<?php

use App\Models\Brand;
use App\Models\SmStrategy;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmStrategy', function () {

    describe('isActive', function () {

        it('returns true when status is active', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);

            expect($strategy->isActive())->toBeTrue();
        });

        it('returns false when status is not active', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'draft',
            ]);

            expect($strategy->isActive())->toBeFalse();
        });
    });

    describe('activate', function () {

        it('sets status to active and activated_at', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'draft',
                'activated_at' => null,
            ]);

            $strategy->activate();

            $strategy->refresh();
            expect($strategy->status)->toBe('active')
                ->and($strategy->activated_at)->not->toBeNull();
        });
    });

    describe('pause', function () {

        it('sets status to paused', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);

            $strategy->pause();

            $strategy->refresh();
            expect($strategy->status)->toBe('paused');
        });
    });

    describe('getTotalWeeklyPosts', function () {

        it('sums posting frequency values', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'posting_frequency' => ['instagram' => 5, 'facebook' => 3, 'linkedin' => 2],
            ]);

            expect($strategy->getTotalWeeklyPosts())->toBe(10);
        });

        it('returns 0 when posting_frequency is null', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'posting_frequency' => null,
            ]);

            expect($strategy->getTotalWeeklyPosts())->toBe(0);
        });

        it('returns 0 for empty array', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'posting_frequency' => [],
            ]);

            expect($strategy->getTotalWeeklyPosts())->toBe(0);
        });

        it('handles single platform', function () {
            $strategy = SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'posting_frequency' => ['tiktok' => 7],
            ]);

            expect($strategy->getTotalWeeklyPosts())->toBe(7);
        });
    });

    describe('scopes', function () {

        it('active scope returns only active strategies', function () {
            SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);
            SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'draft',
            ]);
            SmStrategy::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'paused',
            ]);

            $results = SmStrategy::active()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->status)->toBe('active');
        });
    });
});
