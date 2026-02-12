<?php

use App\Models\Brand;
use App\Models\SmAutoReplyRule;
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

describe('SmAutoReplyRule API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules")
            ->assertUnauthorized();
    });

    it('can list rules', function () {
        SmAutoReplyRule::factory()->count(3)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a rule', function () {
        $data = [
            'trigger_type' => 'keyword',
            'trigger_value' => 'pricing',
            'response_template' => 'Thanks for asking! Check our website for pricing details.',
            'requires_approval' => true,
            'is_active' => true,
        ];

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules", $data)
            ->assertCreated();

        $this->assertDatabaseHas('sm_auto_reply_rules', [
            'brand_id' => $this->brand->id,
            'trigger_type' => 'keyword',
            'trigger_value' => 'pricing',
        ]);
    });

    it('validates rule creation requires trigger_type, trigger_value, and response_template', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trigger_type', 'trigger_value', 'response_template']);
    });

    it('can show a rule', function () {
        $rule = SmAutoReplyRule::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules/{$rule->public_id}")
            ->assertOk();
    });

    it('can update a rule', function () {
        $rule = SmAutoReplyRule::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules/{$rule->public_id}", [
            'trigger_value' => 'updated-keyword',
            'is_active' => false,
        ])
            ->assertOk();

        $rule->refresh();
        expect($rule->trigger_value)->toBe('updated-keyword');
        expect($rule->is_active)->toBeFalse();
    });

    it('can delete a rule', function () {
        $rule = SmAutoReplyRule::factory()->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/brands/{$this->brand->public_id}/sm-auto-reply-rules/{$rule->public_id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sm_auto_reply_rules', ['id' => $rule->id]);
    });

});
