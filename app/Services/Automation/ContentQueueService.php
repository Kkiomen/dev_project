<?php

namespace App\Services\Automation;

use App\Enums\Platform;
use App\Models\Brand;
use App\Models\ContentQueue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ContentQueueService
{
    public function __construct(
        protected PillarDistributionService $pillarService,
        protected ScheduleSlotService $slotService
    ) {}

    /**
     * Fill the content queue for the next X days.
     * Returns the number of slots created.
     */
    public function fillQueue(Brand $brand): int
    {
        $queueDays = $brand->getContentQueueDays();
        $startDate = Carbon::tomorrow();
        $endDate = Carbon::today()->addDays($queueDays);
        $slotsCreated = 0;

        // Generate slots for each enabled platform
        foreach ($brand->getEnabledPlatforms() as $platform) {
            $slots = $this->slotService->generateSlots($brand, $platform, $queueDays);

            foreach ($slots as $slot) {
                // Skip if slot already exists
                if ($this->isSlotTaken($brand, $platform->value, $slot['date'], $slot['time'])) {
                    continue;
                }

                // Select pillar based on distribution
                $pillarName = $this->pillarService->selectPillar($brand, $slot['date']);

                // Create queue item
                ContentQueue::create([
                    'brand_id' => $brand->id,
                    'pillar_name' => $pillarName,
                    'platform' => $platform->value,
                    'target_date' => $slot['date'],
                    'target_time' => $slot['time'],
                    'content_type' => $this->determineContentType($platform),
                    'status' => 'pending',
                ]);

                $slotsCreated++;
            }
        }

        return $slotsCreated;
    }

    /**
     * Get the next pending slot for content generation.
     */
    public function getNextPendingSlot(Brand $brand): ?ContentQueue
    {
        return ContentQueue::forBrand($brand)
            ->pending()
            ->ordered()
            ->first();
    }

    /**
     * Get all pending slots for a brand.
     */
    public function getPendingSlots(Brand $brand, int $limit = 10): Collection
    {
        return ContentQueue::forBrand($brand)
            ->pending()
            ->ordered()
            ->limit($limit)
            ->get();
    }

    /**
     * Get all ready slots (content generated, waiting to be published).
     */
    public function getReadySlots(Brand $brand): Collection
    {
        return ContentQueue::forBrand($brand)
            ->ready()
            ->ordered()
            ->get();
    }

    /**
     * Get slots per day for a platform based on brand settings.
     */
    public function getSlotsPerDay(Brand $brand, Platform $platform): int
    {
        $frequency = $brand->getPostingFrequency($platform);
        // Frequency is posts per week, convert to daily
        return max(1, (int) ceil($frequency / 7));
    }

    /**
     * Get queue statistics for a brand.
     */
    public function getQueueStats(Brand $brand): array
    {
        $queue = ContentQueue::forBrand($brand)->get();

        $byStatus = $queue->groupBy('status')->map->count();
        $byPlatform = $queue->groupBy('platform')->map->count();
        $byPillar = $queue->groupBy('pillar_name')->map->count();

        // Get upcoming slots by date
        $upcomingByDate = ContentQueue::forBrand($brand)
            ->whereIn('status', ['pending', 'generating', 'ready'])
            ->where('target_date', '>=', Carbon::today())
            ->orderBy('target_date')
            ->get()
            ->groupBy(fn($item) => $item->target_date->format('Y-m-d'))
            ->map->count();

        return [
            'total' => $queue->count(),
            'by_status' => [
                'pending' => $byStatus->get('pending', 0),
                'generating' => $byStatus->get('generating', 0),
                'ready' => $byStatus->get('ready', 0),
                'published' => $byStatus->get('published', 0),
                'failed' => $byStatus->get('failed', 0),
            ],
            'by_platform' => $byPlatform->toArray(),
            'by_pillar' => $byPillar->toArray(),
            'upcoming_by_date' => $upcomingByDate->toArray(),
            'next_empty_day' => $this->findNextEmptyDay($brand),
            'queue_coverage_days' => $this->calculateQueueCoverage($brand),
        ];
    }

    /**
     * Check if a specific slot is already taken.
     */
    public function isSlotTaken(Brand $brand, string $platform, Carbon $date, string $time): bool
    {
        return ContentQueue::forBrand($brand)
            ->where('platform', $platform)
            ->where('target_date', $date->format('Y-m-d'))
            ->where('target_time', $time)
            ->exists();
    }

    /**
     * Clear failed slots and retry them.
     */
    public function retryFailedSlots(Brand $brand): int
    {
        $failedSlots = ContentQueue::forBrand($brand)
            ->failed()
            ->where('generation_attempts', '<', 3)
            ->get();

        foreach ($failedSlots as $slot) {
            $slot->resetToPending();
        }

        return $failedSlots->count();
    }

    /**
     * Clean up old published/failed slots.
     */
    public function cleanupOldSlots(Brand $brand, int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::today()->subDays($daysToKeep);

        return ContentQueue::forBrand($brand)
            ->whereIn('status', ['published', 'failed'])
            ->where('target_date', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Determine content type for a platform.
     */
    protected function determineContentType(Platform $platform): string
    {
        return match ($platform) {
            Platform::Instagram => 'carousel',
            Platform::YouTube => 'video',
            default => 'text',
        };
    }

    /**
     * Find the next day without scheduled content.
     */
    protected function findNextEmptyDay(Brand $brand): ?string
    {
        $queueDays = $brand->getContentQueueDays();

        for ($i = 1; $i <= $queueDays; $i++) {
            $date = Carbon::today()->addDays($i);

            $hasContent = ContentQueue::forBrand($brand)
                ->where('target_date', $date->format('Y-m-d'))
                ->whereIn('status', ['pending', 'generating', 'ready'])
                ->exists();

            if (!$hasContent) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Calculate how many days are covered by the queue.
     */
    protected function calculateQueueCoverage(Brand $brand): int
    {
        $latestDate = ContentQueue::forBrand($brand)
            ->whereIn('status', ['pending', 'generating', 'ready'])
            ->max('target_date');

        if (!$latestDate) {
            return 0;
        }

        return Carbon::today()->diffInDays(Carbon::parse($latestDate));
    }
}
