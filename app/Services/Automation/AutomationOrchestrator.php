<?php

namespace App\Services\Automation;

use App\Jobs\GenerateQueuedContentJob;
use App\Jobs\PublishPostJob;
use App\Models\Brand;
use App\Models\ContentQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutomationOrchestrator
{
    public function __construct(
        protected ContentQueueService $queueService,
        protected PillarDistributionService $pillarService,
        protected ScheduleSlotService $slotService
    ) {}

    /**
     * Main automation processing method.
     * Called hourly for each brand with automation enabled.
     */
    public function processAutomation(Brand $brand): array
    {
        $results = [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'slots_created' => 0,
            'content_jobs_dispatched' => 0,
            'posts_published' => 0,
            'errors' => [],
        ];

        try {
            // Step 1: Fill the queue with new slots
            $results['slots_created'] = $this->refillQueue($brand);

            // Step 2: Generate content for pending slots
            $results['content_jobs_dispatched'] = $this->generatePendingContent($brand);

            // Step 3: Publish posts that are ready and due
            $results['posts_published'] = $this->publishReadyPosts($brand);

            // Update last run timestamp
            $brand->updateLastAutomationRun();

            Log::info('Automation processed for brand', [
                'brand_id' => $brand->id,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();

            Log::error('Automation processing failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Fill the queue with new slots to maintain X days of content.
     */
    public function refillQueue(Brand $brand): int
    {
        return $this->queueService->fillQueue($brand);
    }

    /**
     * Generate content for pending slots.
     */
    public function generatePendingContent(Brand $brand, int $limit = 5): int
    {
        $pendingSlots = $this->queueService->getPendingSlots($brand, $limit);
        $dispatched = 0;

        foreach ($pendingSlots as $slot) {
            try {
                // Mark as generating before dispatching
                $slot->markAsGenerating();

                // Dispatch the job
                GenerateQueuedContentJob::dispatch($slot);

                $dispatched++;
            } catch (\Exception $e) {
                $slot->markAsFailed($e->getMessage());

                Log::error('Failed to dispatch content generation job', [
                    'slot_id' => $slot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * Publish posts that are ready and due.
     */
    public function publishReadyPosts(Brand $brand): int
    {
        $now = Carbon::now();
        $published = 0;

        // Get ready slots where scheduled time has passed
        $dueSlots = ContentQueue::forBrand($brand)
            ->ready()
            ->where('target_date', '<=', $now->format('Y-m-d'))
            ->whereNotNull('social_post_id')
            ->get()
            ->filter(function ($slot) use ($now) {
                $scheduledTime = $slot->getScheduledDateTime();
                return $scheduledTime && $scheduledTime <= $now;
            });

        foreach ($dueSlots as $slot) {
            if ($slot->socialPost) {
                try {
                    PublishPostJob::dispatch($slot->socialPost);
                    $slot->markAsPublished();

                    // Update pillar tracking
                    $this->pillarService->markAsPublished(
                        $brand,
                        $slot->pillar_name,
                        $slot->target_date
                    );

                    $published++;
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch publish job', [
                        'slot_id' => $slot->id,
                        'post_id' => $slot->social_post_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $published;
    }

    /**
     * Get automation status and statistics.
     */
    public function getAutomationStatus(Brand $brand): array
    {
        $queueStats = $this->queueService->getQueueStats($brand);
        $distributionStats = $this->pillarService->getDistributionStats($brand);
        $weeklySchedule = $this->slotService->calculateWeeklySchedule($brand);

        return [
            'enabled' => $brand->isAutomationEnabled(),
            'last_run' => $brand->last_automation_run?->toIso8601String(),
            'queue_days' => $brand->getContentQueueDays(),
            'queue' => $queueStats,
            'pillar_distribution' => $distributionStats,
            'weekly_schedule' => $weeklySchedule,
        ];
    }

    /**
     * Retry failed content generation.
     */
    public function retryFailedSlots(Brand $brand): int
    {
        $retriedCount = $this->queueService->retryFailedSlots($brand);

        if ($retriedCount > 0) {
            // Re-trigger content generation for retried slots
            $this->generatePendingContent($brand, $retriedCount);
        }

        return $retriedCount;
    }

    /**
     * Force regenerate content for a specific slot.
     */
    public function regenerateSlot(ContentQueue $slot): void
    {
        $slot->resetToPending();
        $slot->markAsGenerating();

        GenerateQueuedContentJob::dispatch($slot);
    }

    /**
     * Clear and rebuild the entire queue.
     */
    public function rebuildQueue(Brand $brand): int
    {
        // Delete all pending and failed slots
        ContentQueue::forBrand($brand)
            ->whereIn('status', ['pending', 'failed'])
            ->delete();

        // Refill the queue
        return $this->refillQueue($brand);
    }
}
