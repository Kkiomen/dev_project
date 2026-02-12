<?php

use App\Models\Brand;
use App\Models\SmCrisisAlert;
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

describe('SmCrisisAlert API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-crisis-alerts")
            ->assertUnauthorized();
    });

    it('can list crisis alerts', function () {
        SmCrisisAlert::factory()->count(3)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-crisis-alerts")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter by severity', function () {
        SmCrisisAlert::factory()->create(['brand_id' => $this->brand->id, 'severity' => 'critical']);
        SmCrisisAlert::factory()->create(['brand_id' => $this->brand->id, 'severity' => 'low']);
        SmCrisisAlert::factory()->create(['brand_id' => $this->brand->id, 'severity' => 'low']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-crisis-alerts?severity=critical")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter by is_resolved', function () {
        SmCrisisAlert::factory()->create(['brand_id' => $this->brand->id, 'is_resolved' => false]);
        SmCrisisAlert::factory()->create(['brand_id' => $this->brand->id, 'is_resolved' => true, 'resolved_at' => now()]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-crisis-alerts?is_resolved=false")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can show a crisis alert', function () {
        $alert = SmCrisisAlert::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-crisis-alerts/{$alert->public_id}")
            ->assertOk();
    });

    it('can resolve a crisis alert', function () {
        $alert = SmCrisisAlert::factory()->create([
            'brand_id' => $this->brand->id,
            'is_resolved' => false,
        ]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-crisis-alerts/{$alert->public_id}/resolve", [
            'resolution_notes' => 'Issue addressed and resolved.',
        ])
            ->assertOk();

        $alert->refresh();
        expect($alert->is_resolved)->toBeTrue();
        expect($alert->resolved_at)->not->toBeNull();
        expect($alert->resolution_notes)->toBe('Issue addressed and resolved.');
    });

});
