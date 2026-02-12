<?php

namespace App\Services\SmManager;

use App\Models\Brand;
use App\Models\SmAlertRule;
use App\Models\SmMention;
use App\Models\SmMonitoredKeyword;
use Illuminate\Support\Facades\Log;

class SmMentionTrackerService
{
    public function __construct(
        protected SmSentimentAnalyzerService $sentimentAnalyzer
    ) {}

    /**
     * Process raw mentions from external sources.
     *
     * Steps:
     * 1. Deduplicate by source_url
     * 2. Analyze sentiment via SmSentimentAnalyzerService
     * 3. Create SmMention records
     * 4. Update keyword mention_count
     * 5. Check alert rules
     *
     * @param Brand $brand
     * @param array $rawMentions Array of raw mention data from external sources
     * @return array{created: int, duplicates: int, alerts_triggered: int}
     */
    public function processMentions(Brand $brand, array $rawMentions): array
    {
        $created = 0;
        $duplicates = 0;
        $alertsTriggered = 0;

        if (empty($rawMentions)) {
            return ['created' => 0, 'duplicates' => 0, 'alerts_triggered' => 0];
        }

        $existingUrls = $this->getExistingSourceUrls($brand, $rawMentions);

        $textsForAnalysis = [];
        $mentionsToCreate = [];

        foreach ($rawMentions as $index => $raw) {
            $sourceUrl = $raw['source_url'] ?? null;

            if ($sourceUrl && in_array($sourceUrl, $existingUrls)) {
                $duplicates++;
                continue;
            }

            $mentionId = "mention_{$index}";

            $textsForAnalysis[] = [
                'id' => $mentionId,
                'text' => $raw['text'] ?? '',
            ];

            $mentionsToCreate[$mentionId] = $raw;
        }

        $sentimentMap = $this->analyzeSentiments($brand, $textsForAnalysis);

        $keywordsToUpdate = [];

        foreach ($mentionsToCreate as $mentionId => $raw) {
            try {
                $sentiment = $sentimentMap[$mentionId] ?? 'neutral';
                $keywordId = $this->findMatchingKeywordId($brand, $raw);

                $mention = SmMention::create([
                    'brand_id' => $brand->id,
                    'sm_monitored_keyword_id' => $keywordId,
                    'platform' => $raw['platform'] ?? null,
                    'source_url' => $raw['source_url'] ?? null,
                    'author_handle' => $raw['author_handle'] ?? null,
                    'author_name' => $raw['author_name'] ?? null,
                    'text' => $raw['text'] ?? '',
                    'sentiment' => $sentiment,
                    'reach' => $raw['reach'] ?? 0,
                    'engagement' => $raw['engagement'] ?? 0,
                    'mentioned_at' => $raw['mentioned_at'] ?? now(),
                ]);

                $created++;

                if ($keywordId) {
                    $keywordsToUpdate[$keywordId] = true;
                }

                $alertsTriggered += $this->checkAlertRules($brand, $mention);
            } catch (\Throwable $e) {
                Log::error('SmMentionTracker: failed to create mention', [
                    'brand_id' => $brand->id,
                    'source_url' => $raw['source_url'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach (array_keys($keywordsToUpdate) as $keywordId) {
            $keyword = SmMonitoredKeyword::find($keywordId);
            if ($keyword) {
                $this->updateKeywordStats($keyword);
            }
        }

        Log::info('SmMentionTracker: processMentions completed', [
            'brand_id' => $brand->id,
            'created' => $created,
            'duplicates' => $duplicates,
            'alerts_triggered' => $alertsTriggered,
        ]);

        return [
            'created' => $created,
            'duplicates' => $duplicates,
            'alerts_triggered' => $alertsTriggered,
        ];
    }

    /**
     * Check all active alert rules for this brand against a new mention.
     *
     * @return int Number of alerts triggered
     */
    public function checkAlertRules(Brand $brand, SmMention $mention): int
    {
        $rules = SmAlertRule::where('brand_id', $brand->id)
            ->active()
            ->get();

        $triggered = 0;

        foreach ($rules as $rule) {
            if ($this->ruleTriggered($rule, $mention)) {
                $triggered++;

                Log::info('SmMentionTracker: alert rule triggered', [
                    'brand_id' => $brand->id,
                    'rule_id' => $rule->id,
                    'alert_type' => $rule->alert_type,
                    'mention_id' => $mention->id,
                ]);
            }
        }

        return $triggered;
    }

    /**
     * Recalculate mention_count and last_mention_at for a keyword.
     */
    public function updateKeywordStats(SmMonitoredKeyword $keyword): void
    {
        $keyword->update([
            'mention_count' => $keyword->mentions()->count(),
            'last_mention_at' => $keyword->mentions()->latest('mentioned_at')->value('mentioned_at'),
        ]);
    }

    /**
     * Get existing source URLs to detect duplicates.
     */
    protected function getExistingSourceUrls(Brand $brand, array $rawMentions): array
    {
        $sourceUrls = array_filter(array_column($rawMentions, 'source_url'));

        if (empty($sourceUrls)) {
            return [];
        }

        return SmMention::where('brand_id', $brand->id)
            ->whereIn('source_url', $sourceUrls)
            ->pluck('source_url')
            ->toArray();
    }

    /**
     * Analyze sentiments for a batch of texts using the sentiment analyzer.
     *
     * @return array<string, string> Map of mention ID to sentiment
     */
    protected function analyzeSentiments(Brand $brand, array $textsForAnalysis): array
    {
        if (empty($textsForAnalysis)) {
            return [];
        }

        $result = $this->sentimentAnalyzer->analyzeBatch($brand, $textsForAnalysis);

        if (!$result['success']) {
            Log::warning('SmMentionTracker: sentiment analysis failed, defaulting to neutral', [
                'brand_id' => $brand->id,
                'error' => $result['error'] ?? 'unknown',
            ]);

            return [];
        }

        $map = [];
        foreach ($result['results'] as $item) {
            $map[$item['id']] = $item['sentiment'];
        }

        return $map;
    }

    /**
     * Find a matching monitored keyword ID for the raw mention.
     */
    protected function findMatchingKeywordId(Brand $brand, array $raw): ?int
    {
        if (!empty($raw['keyword_id'])) {
            return (int) $raw['keyword_id'];
        }

        if (empty($raw['text'])) {
            return null;
        }

        $keywords = SmMonitoredKeyword::where('brand_id', $brand->id)
            ->active()
            ->get();

        $textLower = mb_strtolower($raw['text']);

        foreach ($keywords as $keyword) {
            if (mb_strpos($textLower, mb_strtolower($keyword->keyword)) !== false) {
                return $keyword->id;
            }
        }

        return null;
    }

    /**
     * Check if an alert rule is triggered by a mention.
     */
    protected function ruleTriggered(SmAlertRule $rule, SmMention $mention): bool
    {
        return match ($rule->alert_type) {
            'negative_mention' => $mention->sentiment === 'negative',
            'high_reach' => $mention->reach >= ($rule->threshold ?? 1000),
            'high_engagement' => $mention->engagement >= ($rule->threshold ?? 100),
            'any_mention' => true,
            default => false,
        };
    }
}
