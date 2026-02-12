<?php

namespace App\Services\SmManager;

use App\Models\Brand;
use App\Models\SmComment;
use App\Models\SmCrisisAlert;
use App\Models\SmMention;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmCrisisDetectorService
{
    protected const NEGATIVE_SPIKE_THRESHOLD = 2.0;
    protected const NEGATIVE_SPIKE_MIN_COMMENTS = 5;
    protected const MENTION_SPIKE_THRESHOLD = 3.0;
    protected const FLAGGED_COMMENTS_THRESHOLD = 3;
    protected const FLAGGED_COMMENTS_HOURS = 2;

    /**
     * Check multiple crisis indicators for a brand and create alerts if detected.
     *
     * @return array{alerts_created: int, indicators: array}
     */
    public function detect(Brand $brand): array
    {
        $indicators = [];
        $alertsCreated = 0;

        try {
            $sentimentSpike = $this->checkNegativeSentimentSpike($brand);
            if ($sentimentSpike) {
                $indicators[] = $sentimentSpike;
                $severity = $sentimentSpike['negative_percent'] > 75 ? 'critical' : 'high';

                $this->createAlert(
                    $brand,
                    $severity,
                    'negative_sentiment_spike',
                    "Negative sentiment spike detected: {$sentimentSpike['negative_percent']}% negative in the last hour "
                        . "(vs {$sentimentSpike['average_negative_percent']}% 24h average). "
                        . "{$sentimentSpike['negative_count']} negative comments out of {$sentimentSpike['total_count']}.",
                    ['comment_ids' => $sentimentSpike['comment_ids'] ?? []]
                );
                $alertsCreated++;
            }

            $mentionSpike = $this->checkMentionVolumeSpike($brand);
            if ($mentionSpike) {
                $indicators[] = $mentionSpike;
                $severity = $mentionSpike['spike_ratio'] > 5.0 ? 'high' : 'medium';

                $this->createAlert(
                    $brand,
                    $severity,
                    'mention_volume_spike',
                    "Mention volume spike detected: {$mentionSpike['last_hour_count']} mentions in the last hour "
                        . "(vs {$mentionSpike['hourly_average']} hourly average). "
                        . "Spike ratio: {$mentionSpike['spike_ratio']}x.",
                    ['mention_ids' => $mentionSpike['mention_ids'] ?? []]
                );
                $alertsCreated++;
            }

            $flaggedComments = $this->checkFlaggedComments($brand);
            if ($flaggedComments) {
                $indicators[] = $flaggedComments;

                $this->createAlert(
                    $brand,
                    'medium',
                    'flagged_comments',
                    "High number of flagged comments: {$flaggedComments['flagged_count']} flagged comments "
                        . "in the last {$flaggedComments['hours']} hours.",
                    ['comment_ids' => $flaggedComments['comment_ids'] ?? []]
                );
                $alertsCreated++;
            }
        } catch (\Throwable $e) {
            Log::error('SmCrisisDetector: detect failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'alerts_created' => $alertsCreated,
            'indicators' => $indicators,
        ];
    }

    /**
     * Check for a negative sentiment spike in comments.
     *
     * Compares last hour negative percentage vs 24-hour average.
     * Triggers if >2x increase AND >5 comments in the last hour.
     */
    protected function checkNegativeSentimentSpike(Brand $brand): ?array
    {
        $oneHourAgo = Carbon::now()->subHour();
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        $lastHourComments = SmComment::where('brand_id', $brand->id)
            ->where('posted_at', '>=', $oneHourAgo)
            ->get();

        $lastHourTotal = $lastHourComments->count();

        if ($lastHourTotal < self::NEGATIVE_SPIKE_MIN_COMMENTS) {
            return null;
        }

        $lastHourNegative = $lastHourComments->filter(fn (SmComment $c) => $c->isNegative())->count();
        $lastHourNegativePercent = round(($lastHourNegative / $lastHourTotal) * 100, 1);

        $dayComments = SmComment::where('brand_id', $brand->id)
            ->where('posted_at', '>=', $twentyFourHoursAgo)
            ->where('posted_at', '<', $oneHourAgo)
            ->get();

        $dayTotal = $dayComments->count();

        if ($dayTotal === 0) {
            $averageNegativePercent = 0.0;
        } else {
            $dayNegative = $dayComments->filter(fn (SmComment $c) => $c->isNegative())->count();
            $averageNegativePercent = round(($dayNegative / $dayTotal) * 100, 1);
        }

        $isSpike = $averageNegativePercent > 0
            ? ($lastHourNegativePercent / $averageNegativePercent) >= self::NEGATIVE_SPIKE_THRESHOLD
            : $lastHourNegativePercent > 50;

        if (!$isSpike) {
            return null;
        }

        $negativeCommentIds = $lastHourComments
            ->filter(fn (SmComment $c) => $c->isNegative())
            ->pluck('id')
            ->toArray();

        return [
            'type' => 'negative_sentiment_spike',
            'negative_percent' => $lastHourNegativePercent,
            'average_negative_percent' => $averageNegativePercent,
            'negative_count' => $lastHourNegative,
            'total_count' => $lastHourTotal,
            'comment_ids' => $negativeCommentIds,
        ];
    }

    /**
     * Check for a mention volume spike.
     *
     * Compares last hour mentions vs daily hourly average.
     * Triggers if >3x increase.
     */
    protected function checkMentionVolumeSpike(Brand $brand): ?array
    {
        $oneHourAgo = Carbon::now()->subHour();
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        $lastHourMentions = SmMention::where('brand_id', $brand->id)
            ->where('mentioned_at', '>=', $oneHourAgo)
            ->get();

        $lastHourCount = $lastHourMentions->count();

        if ($lastHourCount === 0) {
            return null;
        }

        $dayCount = SmMention::where('brand_id', $brand->id)
            ->where('mentioned_at', '>=', $twentyFourHoursAgo)
            ->where('mentioned_at', '<', $oneHourAgo)
            ->count();

        $hoursInPeriod = max(1, Carbon::now()->diffInHours($twentyFourHoursAgo) - 1);
        $hourlyAverage = $dayCount / $hoursInPeriod;

        if ($hourlyAverage <= 0) {
            $isSpike = $lastHourCount >= 10;
            $spikeRatio = $lastHourCount;
        } else {
            $spikeRatio = round($lastHourCount / $hourlyAverage, 1);
            $isSpike = $spikeRatio >= self::MENTION_SPIKE_THRESHOLD;
        }

        if (!$isSpike) {
            return null;
        }

        return [
            'type' => 'mention_volume_spike',
            'last_hour_count' => $lastHourCount,
            'hourly_average' => round($hourlyAverage, 1),
            'spike_ratio' => $spikeRatio,
            'mention_ids' => $lastHourMentions->pluck('id')->toArray(),
        ];
    }

    /**
     * Check for excessive flagged comments.
     *
     * Triggers if >3 flagged comments in the last 2 hours.
     */
    protected function checkFlaggedComments(Brand $brand): ?array
    {
        $cutoff = Carbon::now()->subHours(self::FLAGGED_COMMENTS_HOURS);

        $flaggedComments = SmComment::where('brand_id', $brand->id)
            ->flagged()
            ->where('posted_at', '>=', $cutoff)
            ->get();

        $flaggedCount = $flaggedComments->count();

        if ($flaggedCount <= self::FLAGGED_COMMENTS_THRESHOLD) {
            return null;
        }

        return [
            'type' => 'flagged_comments',
            'flagged_count' => $flaggedCount,
            'hours' => self::FLAGGED_COMMENTS_HOURS,
            'comment_ids' => $flaggedComments->pluck('id')->toArray(),
        ];
    }

    /**
     * Create a crisis alert record.
     */
    protected function createAlert(
        Brand $brand,
        string $severity,
        string $triggerType,
        string $description,
        array $relatedItems = []
    ): SmCrisisAlert {
        Log::warning('SmCrisisDetector: crisis alert created', [
            'brand_id' => $brand->id,
            'severity' => $severity,
            'trigger_type' => $triggerType,
        ]);

        return SmCrisisAlert::create([
            'brand_id' => $brand->id,
            'severity' => $severity,
            'trigger_type' => $triggerType,
            'description' => $description,
            'related_items' => $relatedItems,
            'is_resolved' => false,
        ]);
    }
}
