<?php

use App\Models\Brand;
use App\Models\SmMention;
use App\Models\SmMonitoredKeyword;
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

describe('SmMonitoredKeyword API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords")
            ->assertUnauthorized();
    });

    it('can list keywords', function () {
        SmMonitoredKeyword::factory()->count(3)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter by platform', function () {
        SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'instagram']);
        SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'facebook']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords?platform=instagram")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by category', function () {
        SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id, 'category' => 'brand']);
        SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id, 'category' => 'competitor']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords?category=brand")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by is_active', function () {
        SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id, 'is_active' => true]);
        SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id, 'is_active' => false]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords?is_active=true")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a keyword', function () {
        $data = [
            'keyword' => 'my brand name',
            'platform' => 'instagram',
            'category' => 'brand',
        ];

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords", $data)
            ->assertCreated();

        $this->assertDatabaseHas('sm_monitored_keywords', [
            'brand_id' => $this->brand->id,
            'keyword' => 'my brand name',
            'platform' => 'instagram',
            'category' => 'brand',
        ]);
    });

    it('validates keyword creation requires keyword', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['keyword']);
    });

    it('can show a keyword and loads mentions', function () {
        $keyword = SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id]);
        SmMention::factory()->count(2)->create([
            'brand_id' => $this->brand->id,
            'sm_monitored_keyword_id' => $keyword->id,
        ]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords/{$keyword->public_id}")
            ->assertOk();
    });

    it('can update a keyword', function () {
        $keyword = SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords/{$keyword->public_id}", [
            'keyword' => 'updated keyword',
            'is_active' => false,
        ])
            ->assertOk();

        $keyword->refresh();
        expect($keyword->keyword)->toBe('updated keyword');
        expect($keyword->is_active)->toBeFalse();
    });

    it('can delete a keyword', function () {
        $keyword = SmMonitoredKeyword::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/brands/{$this->brand->public_id}/sm-keywords/{$keyword->public_id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sm_monitored_keywords', ['id' => $keyword->id]);
    });

});
