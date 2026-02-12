<?php

use App\Models\Brand;
use App\Models\SmCrisisAlert;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmCrisisAlert', function () {

    describe('resolve', function () {

        it('sets resolved fields', function () {
            $alert = SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'is_resolved' => false,
            ]);

            $alert->resolve('Issue addressed with public statement');

            $alert->refresh();
            expect($alert->is_resolved)->toBeTrue()
                ->and($alert->resolved_at)->not->toBeNull()
                ->and($alert->resolution_notes)->toBe('Issue addressed with public statement');
        });

        it('works without notes', function () {
            $alert = SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'is_resolved' => false,
            ]);

            $alert->resolve();

            $alert->refresh();
            expect($alert->is_resolved)->toBeTrue()
                ->and($alert->resolved_at)->not->toBeNull()
                ->and($alert->resolution_notes)->toBeNull();
        });
    });

    describe('scopes', function () {

        it('unresolved scope returns only unresolved alerts', function () {
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'is_resolved' => false,
            ]);
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'is_resolved' => false,
            ]);
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);

            $results = SmCrisisAlert::unresolved()->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($alert) {
                expect($alert->is_resolved)->toBeFalse();
            });
        });

        it('critical scope returns only high and critical severity alerts', function () {
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'severity' => 'low',
            ]);
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'severity' => 'medium',
            ]);
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'severity' => 'high',
            ]);
            SmCrisisAlert::factory()->create([
                'brand_id' => $this->brand->id,
                'severity' => 'critical',
            ]);

            $results = SmCrisisAlert::critical()->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($alert) {
                expect(in_array($alert->severity, ['high', 'critical']))->toBeTrue();
            });
        });
    });
});
