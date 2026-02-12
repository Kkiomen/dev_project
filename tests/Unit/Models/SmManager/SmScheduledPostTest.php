<?php

use App\Models\Brand;
use App\Models\SmScheduledPost;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmScheduledPost', function () {

    describe('approve', function () {

        it('sets approval fields and status to scheduled', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
                'status' => 'draft',
            ]);

            $approver = User::factory()->create();
            $post->approve($approver->id, 'Looks good');

            $post->refresh();
            expect($post->approval_status)->toBe('approved')
                ->and($post->approved_by)->toBe($approver->id)
                ->and($post->approved_at)->not->toBeNull()
                ->and($post->approval_notes)->toBe('Looks good')
                ->and($post->status)->toBe('scheduled');
        });

        it('works without notes', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
                'status' => 'draft',
            ]);

            $approver = User::factory()->create();
            $post->approve($approver->id);

            $post->refresh();
            expect($post->approval_status)->toBe('approved')
                ->and($post->approval_notes)->toBeNull()
                ->and($post->status)->toBe('scheduled');
        });
    });

    describe('reject', function () {

        it('sets rejection fields and status to cancelled', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
                'status' => 'draft',
            ]);

            $rejector = User::factory()->create();
            $post->reject($rejector->id, 'Needs rework');

            $post->refresh();
            expect($post->approval_status)->toBe('rejected')
                ->and($post->approved_by)->toBe($rejector->id)
                ->and($post->approved_at)->not->toBeNull()
                ->and($post->approval_notes)->toBe('Needs rework')
                ->and($post->status)->toBe('cancelled');
        });

        it('works without notes', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
                'status' => 'draft',
            ]);

            $rejector = User::factory()->create();
            $post->reject($rejector->id);

            $post->refresh();
            expect($post->approval_status)->toBe('rejected')
                ->and($post->approval_notes)->toBeNull()
                ->and($post->status)->toBe('cancelled');
        });
    });

    describe('markAsPublished', function () {

        it('sets published status and external data', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
            ]);

            $post->markAsPublished('ext-123', ['url' => 'https://example.com/post']);

            $post->refresh();
            expect($post->status)->toBe('published')
                ->and($post->published_at)->not->toBeNull()
                ->and($post->external_post_id)->toBe('ext-123')
                ->and($post->platform_response)->toBe(['url' => 'https://example.com/post']);
        });

        it('works without external id and response', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
            ]);

            $post->markAsPublished();

            $post->refresh();
            expect($post->status)->toBe('published')
                ->and($post->published_at)->not->toBeNull()
                ->and($post->external_post_id)->toBeNull()
                ->and($post->platform_response)->toBeNull();
        });
    });

    describe('markAsFailed', function () {

        it('sets failed status and increments retry count', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
                'retry_count' => 0,
            ]);

            $post->markAsFailed('Connection timeout');

            $post->refresh();
            expect($post->status)->toBe('failed')
                ->and($post->error_message)->toBe('Connection timeout')
                ->and($post->retry_count)->toBe(1);
        });

        it('increments retry count each time', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
                'retry_count' => 2,
            ]);

            $post->markAsFailed('API error');

            $post->refresh();
            expect($post->retry_count)->toBe(3);
        });
    });

    describe('isPending', function () {

        it('returns true when approval_status is pending', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
            ]);

            expect($post->isPending())->toBeTrue();
        });

        it('returns false when approval_status is not pending', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'approved',
            ]);

            expect($post->isPending())->toBeFalse();
        });
    });

    describe('isApproved', function () {

        it('returns true when approval_status is approved', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'approved',
            ]);

            expect($post->isApproved())->toBeTrue();
        });

        it('returns false when approval_status is not approved', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
            ]);

            expect($post->isApproved())->toBeFalse();
        });
    });

    describe('isPublished', function () {

        it('returns true when status is published', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'published',
            ]);

            expect($post->isPublished())->toBeTrue();
        });

        it('returns false when status is not published', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
            ]);

            expect($post->isPublished())->toBeFalse();
        });
    });

    describe('canRetry', function () {

        it('returns true when failed and retry count below max', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'failed',
                'retry_count' => 1,
                'max_retries' => 3,
            ]);

            expect($post->canRetry())->toBeTrue();
        });

        it('returns false when retry count equals max retries', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'failed',
                'retry_count' => 3,
                'max_retries' => 3,
            ]);

            expect($post->canRetry())->toBeFalse();
        });

        it('returns false when status is not failed', function () {
            $post = SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
                'retry_count' => 0,
                'max_retries' => 3,
            ]);

            expect($post->canRetry())->toBeFalse();
        });
    });

    describe('scopes', function () {

        it('pendingApproval scope returns only pending posts', function () {
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
            ]);
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'approved',
            ]);
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'rejected',
            ]);

            $results = SmScheduledPost::pendingApproval()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->approval_status)->toBe('pending');
        });

        it('approved scope returns only approved posts', function () {
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'approved',
            ]);
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'approval_status' => 'pending',
            ]);

            $results = SmScheduledPost::approved()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->approval_status)->toBe('approved');
        });

        it('readyToPublish scope returns approved scheduled posts with past scheduled_at', function () {
            // Ready to publish: approved + scheduled + past scheduled_at
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
                'approval_status' => 'approved',
                'scheduled_at' => Carbon::now()->subHour(),
            ]);

            // Not ready: future scheduled_at
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
                'approval_status' => 'approved',
                'scheduled_at' => Carbon::now()->addDay(),
            ]);

            // Not ready: not approved
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'scheduled',
                'approval_status' => 'pending',
                'scheduled_at' => Carbon::now()->subHour(),
            ]);

            // Not ready: wrong status
            SmScheduledPost::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'draft',
                'approval_status' => 'approved',
                'scheduled_at' => Carbon::now()->subHour(),
            ]);

            $results = SmScheduledPost::readyToPublish()->get();

            expect($results)->toHaveCount(1);
        });
    });
});
