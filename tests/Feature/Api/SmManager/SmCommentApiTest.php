<?php

use App\Models\Brand;
use App\Models\SmComment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmComment API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments")
            ->assertUnauthorized();
    });

    it('can list comments', function () {
        SmComment::factory()->count(3)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter by platform', function () {
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'instagram']);
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'facebook']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments?platform=instagram")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by sentiment', function () {
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'sentiment' => 'positive']);
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'sentiment' => 'negative']);
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'sentiment' => 'negative']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments?sentiment=negative")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter by is_replied', function () {
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'is_replied' => true]);
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'is_replied' => false]);
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'is_replied' => false]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments?is_replied=true")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by is_flagged', function () {
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'is_flagged' => true]);
        SmComment::factory()->create(['brand_id' => $this->brand->id, 'is_flagged' => false]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments?is_flagged=true")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can show a comment', function () {
        $comment = SmComment::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-comments/{$comment->public_id}")
            ->assertOk();
    });

    it('can reply to a comment', function () {
        $comment = SmComment::factory()->create([
            'brand_id' => $this->brand->id,
            'is_replied' => false,
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-comments/{$comment->public_id}/reply", [
            'reply_text' => 'Thank you for your feedback!',
        ])
            ->assertOk();

        $comment->refresh();
        expect($comment->is_replied)->toBeTrue();
        expect($comment->reply_text)->toBe('Thank you for your feedback!');
        expect($comment->replied_at)->not->toBeNull();
    });

    it('reply requires reply_text', function () {
        $comment = SmComment::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-comments/{$comment->public_id}/reply", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reply_text']);
    });

    it('can hide a comment', function () {
        $comment = SmComment::factory()->create([
            'brand_id' => $this->brand->id,
            'is_hidden' => false,
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-comments/{$comment->public_id}/hide")
            ->assertOk();

        $comment->refresh();
        expect($comment->is_hidden)->toBeTrue();
    });

    it('can flag a comment', function () {
        $comment = SmComment::factory()->create([
            'brand_id' => $this->brand->id,
            'is_flagged' => false,
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-comments/{$comment->public_id}/flag")
            ->assertOk();

        $comment->refresh();
        expect($comment->is_flagged)->toBeTrue();
    });

});
