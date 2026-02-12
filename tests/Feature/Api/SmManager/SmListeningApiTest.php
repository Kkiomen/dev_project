<?php

use App\Models\Brand;
use App\Models\SmAlertRule;
use App\Models\SmListeningReport;
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

describe('SmListening API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/mentions")
            ->assertUnauthorized();
    });

    it('can list mentions', function () {
        SmMention::factory()->count(3)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/mentions")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter mentions by platform', function () {
        SmMention::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'instagram']);
        SmMention::factory()->create(['brand_id' => $this->brand->id, 'platform' => 'facebook']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/mentions?platform=instagram")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter mentions by sentiment', function () {
        SmMention::factory()->create(['brand_id' => $this->brand->id, 'sentiment' => 'positive']);
        SmMention::factory()->create(['brand_id' => $this->brand->id, 'sentiment' => 'negative']);
        SmMention::factory()->create(['brand_id' => $this->brand->id, 'sentiment' => 'negative']);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/mentions?sentiment=negative")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can list alert rules', function () {
        SmAlertRule::factory()->count(2)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/alert-rules")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create an alert rule', function () {
        $data = [
            'alert_type' => 'mention_spike',
            'threshold' => 10,
            'timeframe' => '1_hour',
            'notify_via' => ['email', 'slack'],
            'is_active' => true,
        ];

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/alert-rules", $data)
            ->assertCreated();

        $this->assertDatabaseHas('sm_alert_rules', [
            'brand_id' => $this->brand->id,
            'alert_type' => 'mention_spike',
            'threshold' => 10,
            'timeframe' => '1_hour',
        ]);
    });

    it('validates alert rule creation requires alert_type, threshold, timeframe, and notify_via', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/alert-rules", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alert_type', 'threshold', 'timeframe', 'notify_via']);
    });

    it('can update an alert rule', function () {
        $rule = SmAlertRule::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/alert-rules/{$rule->public_id}", [
            'threshold' => 25,
            'is_active' => false,
        ])
            ->assertOk();

        $rule->refresh();
        expect($rule->threshold)->toBe(25);
        expect($rule->is_active)->toBeFalse();
    });

    it('can delete an alert rule', function () {
        $rule = SmAlertRule::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/alert-rules/{$rule->public_id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sm_alert_rules', ['id' => $rule->id]);
    });

    it('can list listening reports', function () {
        SmListeningReport::factory()->count(2)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/reports")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can show a single listening report', function () {
        $report = SmListeningReport::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-listening/reports/{$report->public_id}")
            ->assertOk();
    });

});
