<?php

use App\Models\Brand;
use App\Models\SmMessage;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmMessage', function () {

    describe('markAsRead', function () {

        it('sets is_read to true', function () {
            $message = SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'is_read' => false,
            ]);

            $message->markAsRead();

            $message->refresh();
            expect($message->is_read)->toBeTrue();
        });
    });

    describe('isInbound', function () {

        it('returns true when direction is inbound', function () {
            $message = SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'direction' => 'inbound',
            ]);

            expect($message->isInbound())->toBeTrue();
        });

        it('returns false when direction is outbound', function () {
            $message = SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'direction' => 'outbound',
            ]);

            expect($message->isInbound())->toBeFalse();
        });
    });

    describe('scopes', function () {

        it('unread scope returns only unread messages', function () {
            SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'is_read' => false,
            ]);
            SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'is_read' => false,
            ]);
            SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'is_read' => true,
            ]);

            $results = SmMessage::unread()->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($message) {
                expect($message->is_read)->toBeFalse();
            });
        });

        it('inbound scope returns only inbound messages', function () {
            SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'direction' => 'inbound',
            ]);
            SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'direction' => 'outbound',
            ]);
            SmMessage::factory()->create([
                'brand_id' => $this->brand->id,
                'direction' => 'inbound',
            ]);

            $results = SmMessage::inbound()->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($message) {
                expect($message->direction)->toBe('inbound');
            });
        });
    });
});
