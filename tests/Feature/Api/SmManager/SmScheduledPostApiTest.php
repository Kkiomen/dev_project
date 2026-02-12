<?php

use App\Models\Brand;
use App\Models\SmScheduledPost;
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

describe('SmScheduledPost API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts")
            ->assertUnauthorized();
    });

    it('cannot access another user\'s brand', function () {
        $otherUser = User::factory()->create();
        $otherBrand = Brand::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Brand',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$otherBrand->public_id}/sm-scheduled-posts")
            ->assertForbidden();
    });

    it('can list scheduled posts', function () {
        SmScheduledPost::factory()->count(3)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter by status', function () {
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'status' => 'scheduled']);
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'status' => 'draft']);
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'status' => 'published']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts?status=scheduled")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by approval_status', function () {
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'approval_status' => 'pending']);
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'approval_status' => 'approved']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts?approval_status=pending")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by platform', function () {
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'instagram']);
        SmScheduledPost::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'facebook']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts?platform=instagram")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can show a single scheduled post', function () {
        $post = SmScheduledPost::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts/{$post->public_id}")
            ->assertOk();
    });

    it('can approve a post', function () {
        $post = SmScheduledPost::factory()->create([
            'brand_id' => $this->brand->id,
            'approval_status' => 'pending',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts/{$post->public_id}/approve", [
            'approval_notes' => 'Looks good!',
        ])
            ->assertOk();

        $post->refresh();
        expect($post->approval_status)->toBe('approved');
        expect($post->approved_by)->toBe($this->user->id);
        expect($post->status)->toBe('scheduled');
        expect($post->approved_at)->not->toBeNull();
    });

    it('reject requires approval_notes', function () {
        $post = SmScheduledPost::factory()->create([
            'brand_id' => $this->brand->id,
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts/{$post->public_id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['approval_notes']);
    });

    it('can reject a post', function () {
        $post = SmScheduledPost::factory()->create([
            'brand_id' => $this->brand->id,
            'approval_status' => 'pending',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts/{$post->public_id}/reject", [
            'approval_notes' => 'Needs changes to the copy.',
        ])
            ->assertOk();

        $post->refresh();
        expect($post->approval_status)->toBe('rejected');
        expect($post->approved_by)->toBe($this->user->id);
        expect($post->status)->toBe('cancelled');
        expect($post->approval_notes)->toBe('Needs changes to the copy.');
    });

    it('can delete a scheduled post', function () {
        $post = SmScheduledPost::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/brands/{$this->brand->public_id}/sm-scheduled-posts/{$post->public_id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sm_scheduled_posts', ['id' => $post->id]);
    });

});
