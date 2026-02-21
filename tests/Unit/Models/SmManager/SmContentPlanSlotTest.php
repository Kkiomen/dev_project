<?php

use App\Models\Brand;
use App\Models\SmContentPlan;
use App\Models\SmContentPlanSlot;
use App\Models\SocialPost;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
    $this->contentPlan = SmContentPlan::factory()->create([
        'brand_id' => $this->brand->id,
    ]);
});

describe('SmContentPlanSlot', function () {

    describe('isPlanned', function () {

        it('returns true when status is planned', function () {
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'status' => 'planned',
            ]);

            expect($slot->isPlanned())->toBeTrue();
        });

        it('returns false when status is not planned', function () {
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'status' => 'content_ready',
            ]);

            expect($slot->isPlanned())->toBeFalse();
        });
    });

    describe('hasContent', function () {

        it('returns true when social_post_id is set', function () {
            $socialPost = SocialPost::factory()->create(['user_id' => $this->user->id]);
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'social_post_id' => $socialPost->id,
            ]);

            expect($slot->hasContent())->toBeTrue();
        });

        it('returns false when social_post_id is null', function () {
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'social_post_id' => null,
            ]);

            expect($slot->hasContent())->toBeFalse();
        });
    });

    describe('markContentReady', function () {

        it('sets status to content_ready', function () {
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'status' => 'planned',
            ]);

            $slot->markContentReady();

            $slot->refresh();
            expect($slot->status)->toBe('content_ready');
        });
    });

    describe('getScheduledDateTime', function () {

        it('returns date and time when scheduled_time is set', function () {
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'scheduled_date' => '2026-03-15',
                'scheduled_time' => '14:30',
            ]);

            expect($slot->getScheduledDateTime())->toBe('2026-03-15 14:30');
        });

        it('returns only date when scheduled_time is null', function () {
            $slot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'scheduled_date' => '2026-03-15',
                'scheduled_time' => null,
            ]);

            expect($slot->getScheduledDateTime())->toBe('2026-03-15');
        });
    });

    describe('scopes', function () {

        it('planned scope returns only planned slots', function () {
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'status' => 'planned',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'status' => 'content_ready',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'status' => 'published',
            ]);

            $results = SmContentPlanSlot::planned()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->status)->toBe('planned');
        });

        it('forPlatform scope filters by platform', function () {
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'platform' => 'instagram',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'platform' => 'instagram',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'platform' => 'facebook',
            ]);

            $results = SmContentPlanSlot::forPlatform('instagram')->get();

            expect($results)->toHaveCount(2);
            $results->each(function ($slot) {
                expect($slot->platform)->toBe('instagram');
            });
        });

        it('upcoming scope returns only future slots ordered correctly', function () {
            $futureSlot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'scheduled_date' => Carbon::now()->addDays(3)->toDateString(),
                'scheduled_time' => '10:00',
            ]);
            $todaySlot = SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'scheduled_date' => Carbon::now()->toDateString(),
                'scheduled_time' => '09:00',
            ]);
            SmContentPlanSlot::factory()->create([
                'sm_content_plan_id' => $this->contentPlan->id,
                'scheduled_date' => Carbon::now()->subDays(2)->toDateString(),
                'scheduled_time' => '10:00',
            ]);

            $results = SmContentPlanSlot::upcoming()->get();

            expect($results)->toHaveCount(2)
                ->and($results->first()->id)->toBe($todaySlot->id)
                ->and($results->last()->id)->toBe($futureSlot->id);
        });
    });
});
