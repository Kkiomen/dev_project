<?php

use App\Models\Brand;
use App\Models\SmContentPlan;
use App\Models\SmContentPlanSlot;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmContentPlan', function () {

    describe('isActive', function () {

        it('returns true when status is active', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);

            expect($plan->isActive())->toBeTrue();
        });

        it('returns false when status is not active', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'draft',
            ]);

            expect($plan->isActive())->toBeFalse();
        });
    });

    describe('getCompletionPercentage', function () {

        it('returns correct percentage', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'total_slots' => 20,
                'completed_slots' => 10,
            ]);

            expect($plan->getCompletionPercentage())->toBe(50);
        });

        it('returns 0 when total_slots is 0', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'total_slots' => 0,
                'completed_slots' => 0,
            ]);

            expect($plan->getCompletionPercentage())->toBe(0);
        });

        it('returns 100 when all slots completed', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'total_slots' => 15,
                'completed_slots' => 15,
            ]);

            expect($plan->getCompletionPercentage())->toBe(100);
        });

        it('rounds correctly', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'total_slots' => 3,
                'completed_slots' => 1,
            ]);

            // 1/3 = 33.33... rounds to 33
            expect($plan->getCompletionPercentage())->toBe(33);
        });
    });

    describe('getPeriodLabel', function () {

        it('returns formatted month and year', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'month' => 3,
                'year' => 2026,
            ]);

            expect($plan->getPeriodLabel())->toBe('March 2026');
        });

        it('returns correct label for December', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'month' => 12,
                'year' => 2025,
            ]);

            expect($plan->getPeriodLabel())->toBe('December 2025');
        });

        it('returns correct label for January', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'month' => 1,
                'year' => 2026,
            ]);

            expect($plan->getPeriodLabel())->toBe('January 2026');
        });
    });

    describe('recalculateSlotCounts', function () {

        it('counts total and published slots', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'total_slots' => 0,
                'completed_slots' => 0,
            ]);

            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $plan->id,
                'status' => 'planned',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $plan->id,
                'status' => 'published',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $plan->id,
                'status' => 'published',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $plan->id,
                'status' => 'content_ready',
            ]);

            $plan->recalculateSlotCounts();

            $plan->refresh();
            expect($plan->total_slots)->toBe(4)
                ->and($plan->completed_slots)->toBe(2);
        });

        it('handles no slots', function () {
            $plan = SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'total_slots' => 10,
                'completed_slots' => 5,
            ]);

            $plan->recalculateSlotCounts();

            $plan->refresh();
            expect($plan->total_slots)->toBe(0)
                ->and($plan->completed_slots)->toBe(0);
        });
    });

    describe('scopes', function () {

        it('active scope returns only active plans', function () {
            SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);
            SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'draft',
            ]);
            SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'completed',
            ]);

            $results = SmContentPlan::active()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->status)->toBe('active');
        });

        it('forMonth scope filters by month and year', function () {
            SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'month' => 3,
                'year' => 2026,
            ]);
            SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'month' => 4,
                'year' => 2026,
            ]);
            SmContentPlan::factory()->create([
                'brand_id' => $this->brand->id,
                'month' => 3,
                'year' => 2025,
            ]);

            $results = SmContentPlan::forMonth(3, 2026)->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->month)->toBe(3)
                ->and($results->first()->year)->toBe(2026);
        });
    });
});
