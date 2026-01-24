<?php

namespace App\Services\Automation;

use App\Models\Brand;
use App\Models\PillarTracking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PillarDistributionService
{
    /**
     * Select the best pillar to use for a given date.
     * Prioritizes underrepresented pillars to maintain target distribution.
     */
    public function selectPillar(Brand $brand, Carbon $targetDate): string
    {
        $pillars = $brand->getContentPillars();

        if (empty($pillars)) {
            return 'General';
        }

        // Get tracking data for this week
        $weekNumber = $targetDate->weekOfYear;
        $year = $targetDate->year;

        // Calculate total planned for this week
        $totalPlanned = PillarTracking::forBrand($brand)
            ->forWeek($weekNumber, $year)
            ->sum('planned_count');

        // If no posts planned yet, just pick the first pillar
        if ($totalPlanned === 0) {
            $selected = $pillars[0];
            $this->incrementPillarTracking($brand, $selected['name'], $targetDate);
            return $selected['name'];
        }

        // Find the most underrepresented pillar
        $pillarScores = [];

        foreach ($pillars as $pillar) {
            $tracking = PillarTracking::getOrCreateForWeek($brand, $pillar['name'], $targetDate);
            $actualPercentage = $tracking->getActualPercentage($totalPlanned);
            $targetPercentage = $pillar['percentage'] ?? 0;

            // Score = how much this pillar needs more content
            // Higher score = more underrepresented = should be chosen
            $pillarScores[$pillar['name']] = $targetPercentage - $actualPercentage;
        }

        // Sort by score (highest = most underrepresented)
        arsort($pillarScores);

        // Get the most underrepresented pillar
        $selectedPillarName = array_key_first($pillarScores);

        // Increment tracking
        $this->incrementPillarTracking($brand, $selectedPillarName, $targetDate);

        return $selectedPillarName;
    }

    /**
     * Get distribution statistics for a brand.
     */
    public function getDistributionStats(Brand $brand, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::now();
        $weekNumber = $date->weekOfYear;
        $year = $date->year;

        $pillars = $brand->getContentPillars();
        $trackings = PillarTracking::forBrand($brand)
            ->forWeek($weekNumber, $year)
            ->get()
            ->keyBy('pillar_name');

        $totalPlanned = $trackings->sum('planned_count');
        $totalPublished = $trackings->sum('published_count');

        $distribution = [];

        foreach ($pillars as $pillar) {
            $tracking = $trackings->get($pillar['name']);

            $distribution[] = [
                'pillar_name' => $pillar['name'],
                'description' => $pillar['description'] ?? null,
                'target_percentage' => $pillar['percentage'] ?? 0,
                'planned_count' => $tracking?->planned_count ?? 0,
                'published_count' => $tracking?->published_count ?? 0,
                'actual_percentage' => $totalPlanned > 0
                    ? round((($tracking?->planned_count ?? 0) / $totalPlanned) * 100, 1)
                    : 0,
                'delta' => $totalPlanned > 0
                    ? round((($tracking?->planned_count ?? 0) / $totalPlanned) * 100 - ($pillar['percentage'] ?? 0), 1)
                    : 0,
            ];
        }

        return [
            'week_number' => $weekNumber,
            'year' => $year,
            'total_planned' => $totalPlanned,
            'total_published' => $totalPublished,
            'pillars' => $distribution,
        ];
    }

    /**
     * Initialize tracking for a new week.
     */
    public function initializeWeekTracking(Brand $brand, Carbon $weekStart): void
    {
        $weekNumber = $weekStart->weekOfYear;
        $year = $weekStart->year;

        foreach ($brand->getContentPillars() as $pillar) {
            PillarTracking::firstOrCreate(
                [
                    'brand_id' => $brand->id,
                    'pillar_name' => $pillar['name'],
                    'week_number' => $weekNumber,
                    'year' => $year,
                ],
                [
                    'target_percentage' => $pillar['percentage'] ?? 0,
                    'planned_count' => 0,
                    'published_count' => 0,
                ]
            );
        }
    }

    /**
     * Get historical distribution data.
     */
    public function getHistoricalDistribution(Brand $brand, int $weeksBack = 4): Collection
    {
        $results = collect();
        $currentWeek = Carbon::now();

        for ($i = 0; $i < $weeksBack; $i++) {
            $date = $currentWeek->copy()->subWeeks($i);
            $stats = $this->getDistributionStats($brand, $date);
            $results->push([
                'week_start' => $date->startOfWeek()->format('Y-m-d'),
                'week_end' => $date->endOfWeek()->format('Y-m-d'),
                ...$stats,
            ]);
        }

        return $results;
    }

    /**
     * Increment planned count for a pillar.
     */
    protected function incrementPillarTracking(Brand $brand, string $pillarName, Carbon $date): void
    {
        $tracking = PillarTracking::getOrCreateForWeek($brand, $pillarName, $date);
        $tracking->incrementPlanned();
    }

    /**
     * Mark a pillar post as published.
     */
    public function markAsPublished(Brand $brand, string $pillarName, Carbon $date): void
    {
        $tracking = PillarTracking::getOrCreateForWeek($brand, $pillarName, $date);
        $tracking->incrementPublished();
    }
}
