<?php

namespace App\Services\SmManager;

use App\Models\Brand;
use App\Models\SmAnalyticsSnapshot;
use App\Models\SmScheduledPost;
use App\Models\SmStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmSchedulerService
{
    /**
     * Determine the optimal posting time for a given brand, platform, and date.
     *
     * Priority:
     * 1. Strategy-defined optimal_times
     * 2. Historical analytics (best-performing hours)
     * 3. Default industry-standard times
     */
    public function getOptimalTime(Brand $brand, string $platform, Carbon $date): Carbon
    {
        // 1. Check active strategy for optimal_times
        $strategyTime = $this->getTimeFromStrategy($brand, $platform);

        if ($strategyTime !== null) {
            return $date->copy()->setTimeFromTimeString($strategyTime);
        }

        // 2. Check analytics snapshots for historical best-performing hours
        $analyticsTime = $this->getTimeFromAnalytics($brand, $platform);

        if ($analyticsTime !== null) {
            return $date->copy()->setTimeFromTimeString($analyticsTime);
        }

        // 3. Fall back to defaults
        $defaultTimes = $this->getDefaultTimes($platform);
        $firstDefault = $defaultTimes[0] ?? '09:00';

        return $date->copy()->setTimeFromTimeString($firstDefault);
    }

    /**
     * Create an SmScheduledPost record.
     * If no scheduledAt is provided, calculate the optimal time automatically.
     */
    public function schedulePost(
        Brand $brand,
        int $socialPostId,
        string $platform,
        ?Carbon $scheduledAt = null
    ): SmScheduledPost {
        if ($scheduledAt === null) {
            $scheduledAt = $this->getOptimalTime($brand, $platform, Carbon::tomorrow());
        }

        $scheduledPost = SmScheduledPost::create([
            'brand_id' => $brand->id,
            'social_post_id' => $socialPostId,
            'platform' => $platform,
            'scheduled_at' => $scheduledAt,
            'status' => 'draft',
            'approval_status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        Log::info('SmSchedulerService: Post scheduled', [
            'brand_id' => $brand->id,
            'social_post_id' => $socialPostId,
            'platform' => $platform,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
        ]);

        return $scheduledPost;
    }

    /**
     * Default optimal posting times by platform based on industry research.
     */
    public function getDefaultTimes(string $platform): array
    {
        return match ($platform) {
            'instagram' => ['09:00', '12:00', '17:00'],
            'facebook' => ['09:00', '13:00', '16:00'],
            'tiktok' => ['07:00', '12:00', '19:00'],
            'linkedin' => ['08:00', '10:00', '12:00'],
            'x' => ['08:00', '12:00', '17:00'],
            'youtube' => ['14:00', '16:00', '20:00'],
            default => ['09:00', '12:00', '17:00'],
        };
    }

    /**
     * Return available time slots for a given date that are not already occupied
     * by existing scheduled posts.
     */
    public function getAvailableSlots(Brand $brand, string $platform, Carbon $date, int $count = 3): array
    {
        $allTimes = $this->getCandidateTimes($brand, $platform);

        // Get already-scheduled times for this brand+platform on this date
        $occupiedTimes = SmScheduledPost::where('brand_id', $brand->id)
            ->where('platform', $platform)
            ->whereDate('scheduled_at', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->pluck('scheduled_at')
            ->map(fn (Carbon $dt) => $dt->format('H:i'))
            ->toArray();

        $available = [];

        foreach ($allTimes as $time) {
            if (in_array($time, $occupiedTimes, true)) {
                continue;
            }

            $available[] = $date->copy()->setTimeFromTimeString($time);

            if (count($available) >= $count) {
                break;
            }
        }

        // If we still need more slots, generate additional half-hour intervals
        if (count($available) < $count) {
            $extraSlots = $this->generateExtraSlots($date, $occupiedTimes, $allTimes, $count - count($available));
            $available = array_merge($available, $extraSlots);
        }

        return $available;
    }

    /**
     * Get the first matching optimal time from the brand's active strategy.
     */
    protected function getTimeFromStrategy(Brand $brand, string $platform): ?string
    {
        $strategy = SmStrategy::where('brand_id', $brand->id)
            ->active()
            ->latest()
            ->first();

        if (!$strategy || !is_array($strategy->optimal_times)) {
            return null;
        }

        // optimal_times structure: ['instagram' => ['09:00', '12:00'], ...]
        $platformTimes = $strategy->optimal_times[$platform] ?? [];

        if (empty($platformTimes)) {
            return null;
        }

        // Return the first configured time
        return $platformTimes[0];
    }

    /**
     * Analyze historical analytics snapshots to determine the best posting hour.
     * Uses engagement_rate from the last 30 days of snapshots.
     */
    protected function getTimeFromAnalytics(Brand $brand, string $platform): ?string
    {
        $snapshots = SmAnalyticsSnapshot::where('brand_id', $brand->id)
            ->forPlatform($platform)
            ->inPeriod(
                Carbon::now()->subDays(30)->toDateString(),
                Carbon::now()->toDateString()
            )
            ->orderByDesc('engagement_rate')
            ->limit(10)
            ->get();

        if ($snapshots->isEmpty()) {
            return null;
        }

        // Analyze extra_metrics for hourly engagement data if available
        $hourlyEngagement = [];

        foreach ($snapshots as $snapshot) {
            $extra = $snapshot->extra_metrics ?? [];
            $bestHour = $extra['best_posting_hour'] ?? null;

            if ($bestHour !== null) {
                $hourKey = str_pad((string) $bestHour, 2, '0', STR_PAD_LEFT) . ':00';
                $hourlyEngagement[$hourKey] = ($hourlyEngagement[$hourKey] ?? 0) + 1;
            }
        }

        if (empty($hourlyEngagement)) {
            return null;
        }

        // Return the hour that appeared most frequently as "best"
        arsort($hourlyEngagement);

        return array_key_first($hourlyEngagement);
    }

    /**
     * Build a combined list of candidate times from strategy, analytics, and defaults.
     */
    protected function getCandidateTimes(Brand $brand, string $platform): array
    {
        $times = [];

        // Strategy times first (highest priority)
        $strategy = SmStrategy::where('brand_id', $brand->id)
            ->active()
            ->latest()
            ->first();

        if ($strategy && is_array($strategy->optimal_times)) {
            $strategyTimes = $strategy->optimal_times[$platform] ?? [];
            $times = array_merge($times, $strategyTimes);
        }

        // Defaults as fallback
        $defaults = $this->getDefaultTimes($platform);
        $times = array_merge($times, $defaults);

        // Deduplicate and keep order
        return array_values(array_unique($times));
    }

    /**
     * Generate additional half-hour time slots when the standard ones are exhausted.
     */
    protected function generateExtraSlots(Carbon $date, array $occupiedTimes, array $usedTimes, int $needed): array
    {
        $slots = [];
        $allUsed = array_merge($occupiedTimes, $usedTimes);

        // Generate slots from 07:00 to 21:00 in 30-minute intervals
        for ($hour = 7; $hour <= 21 && count($slots) < $needed; $hour++) {
            foreach (['00', '30'] as $minute) {
                $time = str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':' . $minute;

                if (in_array($time, $allUsed, true)) {
                    continue;
                }

                $slots[] = $date->copy()->setTimeFromTimeString($time);

                if (count($slots) >= $needed) {
                    break 2;
                }
            }
        }

        return $slots;
    }
}
