<?php

use App\Models\Brand;
use App\Models\SmComment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmComment', function () {

    describe('markAsReplied', function () {

        it('sets replied fields', function () {
            $comment = SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_replied' => false,
            ]);

            $comment->markAsReplied('Thank you for your feedback!');

            $comment->refresh();
            expect($comment->is_replied)->toBeTrue()
                ->and($comment->reply_text)->toBe('Thank you for your feedback!')
                ->and($comment->replied_at)->not->toBeNull();
        });
    });

    describe('isNegative', function () {

        it('returns true for negative sentiment', function () {
            $comment = SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'negative',
            ]);

            expect($comment->isNegative())->toBeTrue();
        });

        it('returns true for crisis sentiment', function () {
            $comment = SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'crisis',
            ]);

            expect($comment->isNegative())->toBeTrue();
        });

        it('returns false for positive sentiment', function () {
            $comment = SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'positive',
            ]);

            expect($comment->isNegative())->toBeFalse();
        });

        it('returns false for neutral sentiment', function () {
            $comment = SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'neutral',
            ]);

            expect($comment->isNegative())->toBeFalse();
        });
    });

    describe('scopes', function () {

        it('unreplied scope returns only unreplied comments', function () {
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_replied' => false,
            ]);
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_replied' => false,
            ]);
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_replied' => true,
                'reply_text' => 'Thanks!',
                'replied_at' => now(),
            ]);

            $results = SmComment::unreplied()->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($comment) {
                expect($comment->is_replied)->toBeFalse();
            });
        });

        it('negative scope returns only negative sentiment comments', function () {
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'negative',
            ]);
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'positive',
            ]);
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'sentiment' => 'neutral',
            ]);

            $results = SmComment::negative()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->sentiment)->toBe('negative');
        });

        it('flagged scope returns only flagged comments', function () {
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_flagged' => true,
            ]);
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_flagged' => false,
            ]);
            SmComment::factory()->create([
                'brand_id' => $this->brand->id,
                'is_flagged' => true,
            ]);

            $results = SmComment::flagged()->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($comment) {
                expect($comment->is_flagged)->toBeTrue();
            });
        });
    });
});
