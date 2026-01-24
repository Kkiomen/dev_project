<?php

namespace App\Services\Automation;

use App\Enums\Platform;
use App\Models\Brand;
use App\Models\ContentQueue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleSlotService
{
    /**
     * Get the next available slot for a platform.
     */
    public function getNextAvailableSlot(Brand $brand, Platform $platform, ?Carbon $fromDate = null): ?array
    {
        $fromDate = $fromDate ?? Carbon::tomorrow();
        $maxDays = $brand->getContentQueueDays();

        for ($day = 0; $day < $maxDays; $day++) {
            $date = $fromDate->copy()->addDays($day);
            $times = $this->getBestTimesForPlatform($brand, $platform);

            foreach ($times as $time) {
                if (!$this->isSlotTaken($brand, $platform, $date, $time)) {
                    return [
                        'date' => $date,
                        'time' => $time,
                        'datetime' => $date->copy()->setTimeFromTimeString($time),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check if a specific slot is already taken.
     */
    public function isSlotTaken(Brand $brand, Platform $platform, Carbon $date, string $time): bool
    {
        return ContentQueue::forBrand($brand)
            ->where('platform', $platform->value)
            ->where('target_date', $date->format('Y-m-d'))
            ->where('target_time', $time)
            ->whereIn('status', ['pending', 'generating', 'ready'])
            ->exists();
    }

    /**
     * Generate slots for X days for a platform.
     */
    public function generateSlots(Brand $brand, Platform $platform, int $days): array
    {
        $slots = [];
        $startDate = Carbon::tomorrow();
        $frequency = $brand->getPostingFrequency($platform);
        $bestTimes = $this->getBestTimesForPlatform($brand, $platform);

        if ($frequency === 0 || empty($bestTimes)) {
            return $slots;
        }

        // Calculate posts per day (can be fractional)
        $postsPerDay = $frequency / 7;

        // Distribute posts across days
        $accumulatedPosts = 0;
        $postsScheduled = 0;

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);
            $accumulatedPosts += $postsPerDay;

            // Schedule posts when accumulated reaches 1 or more
            while ($accumulatedPosts >= 1 && $postsScheduled < $frequency) {
                $timeIndex = $postsScheduled % count($bestTimes);
                $slots[] = [
                    'date' => $date->copy(),
                    'time' => $bestTimes[$timeIndex],
                ];
                $accumulatedPosts -= 1;
                $postsScheduled++;
            }
        }

        return $slots;
    }

    /**
     * Get all slots for a date range.
     */
    public function getSlotsForDateRange(Brand $brand, Carbon $startDate, Carbon $endDate): Collection
    {
        return ContentQueue::forBrand($brand)
            ->whereBetween('target_date', [$startDate, $endDate])
            ->orderBy('target_date')
            ->orderBy('target_time')
            ->get();
    }

    /**
     * Get available slots for a specific date.
     */
    public function getAvailableSlotsForDate(Brand $brand, Carbon $date): array
    {
        $availableSlots = [];

        foreach ($brand->getEnabledPlatforms() as $platform) {
            $times = $this->getBestTimesForPlatform($brand, $platform);

            foreach ($times as $time) {
                if (!$this->isSlotTaken($brand, $platform, $date, $time)) {
                    $availableSlots[] = [
                        'platform' => $platform->value,
                        'date' => $date->format('Y-m-d'),
                        'time' => $time,
                    ];
                }
            }
        }

        return $availableSlots;
    }

    /**
     * Get the best posting times for a platform.
     */
    protected function getBestTimesForPlatform(Brand $brand, Platform $platform): array
    {
        $times = $brand->getBestTimes($platform);

        if (empty($times)) {
            // Default times if none configured
            return match ($platform) {
                Platform::Facebook => ['09:00', '18:00'],
                Platform::Instagram => ['12:00', '20:00'],
                Platform::YouTube => ['17:00'],
            };
        }

        // Sort times
        sort($times);

        return $times;
    }

    /**
     * Calculate optimal posting schedule for a week.
     */
    public function calculateWeeklySchedule(Brand $brand): array
    {
        $schedule = [];
        $startOfWeek = Carbon::now()->startOfWeek();

        for ($day = 0; $day < 7; $day++) {
            $date = $startOfWeek->copy()->addDays($day);
            $dayName = $date->format('l');
            $schedule[$dayName] = [];

            foreach ($brand->getEnabledPlatforms() as $platform) {
                $times = $this->getBestTimesForPlatform($brand, $platform);
                $frequency = $brand->getPostingFrequency($platform);

                // Distribute weekly frequency across days
                $postsForDay = (int) floor($frequency / 7);
                if ($day < ($frequency % 7)) {
                    $postsForDay++;
                }

                for ($i = 0; $i < $postsForDay && $i < count($times); $i++) {
                    $schedule[$dayName][] = [
                        'platform' => $platform->value,
                        'time' => $times[$i],
                    ];
                }
            }

            // Sort by time
            usort($schedule[$dayName], fn($a, $b) => strcmp($a['time'], $b['time']));
        }

        return $schedule;
    }
}
