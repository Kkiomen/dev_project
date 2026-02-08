<?php

namespace App\Services;

use App\Enums\AiProvider;
use App\Enums\Platform;
use App\Enums\ProposalStatus;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\PostProposal;
use App\Models\SocialPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use OpenAI;

class ProposalBatchGeneratorService
{
    private const MAX_SLOTS_PER_BATCH = 30;

    private const DEFAULT_BEST_TIMES = [
        'facebook' => ['09:00', '18:00'],
        'instagram' => ['12:00', '20:00'],
        'youtube' => ['17:00'],
    ];

    public function generate(Brand $brand, User $user, int $days, ?string $language = null): Collection
    {
        $language = $language ?? $brand->getLanguage();

        $slots = $this->findFreeSlots($brand, $user, $days);

        if ($slots->isEmpty()) {
            return collect();
        }

        $slots = $this->assignPillars($brand, $slots);

        $aiResults = $this->generateWithAi($brand, $slots, $language);

        return $this->createProposals($brand, $user, $slots, $aiResults);
    }

    protected function findFreeSlots(Brand $brand, User $user, int $days): Collection
    {
        $allTimes = $this->getMergedBestTimes($brand);
        $maxPerDay = $this->getMaxSlotsPerDay($brand);

        if ($maxPerDay === 0 || empty($allTimes)) {
            return collect();
        }

        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays($days - 1);

        $existingProposals = PostProposal::forUser($user)
            ->where('brand_id', $brand->id)
            ->whereBetween('scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->map(fn ($p) => $p->scheduled_date->format('Y-m-d') . '_' . $p->scheduled_time)
            ->toArray();

        $existingPosts = SocialPost::where('user_id', $user->id)
            ->where('brand_id', $brand->id)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->get()
            ->map(fn ($p) => $p->scheduled_at->format('Y-m-d') . '_' . $p->scheduled_at->format('H:i'))
            ->toArray();

        $occupied = array_merge($existingProposals, $existingPosts);

        $slots = collect();

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);
            $dateStr = $date->format('Y-m-d');
            $daySlots = 0;

            foreach ($allTimes as $time) {
                if ($daySlots >= $maxPerDay) {
                    break;
                }

                $key = $dateStr . '_' . $time;
                if (! in_array($key, $occupied)) {
                    $slots->push([
                        'date' => $dateStr,
                        'time' => $time,
                    ]);
                    $daySlots++;
                }
            }
        }

        return $slots;
    }

    protected function getMergedBestTimes(Brand $brand): array
    {
        $allTimes = [];

        foreach (Platform::cases() as $platform) {
            if (! $brand->isPlatformEnabled($platform)) {
                continue;
            }

            $times = $brand->getBestTimes($platform);
            if (empty($times)) {
                $times = self::DEFAULT_BEST_TIMES[$platform->value] ?? [];
            }

            $allTimes = array_merge($allTimes, $times);
        }

        $allTimes = array_unique($allTimes);
        sort($allTimes);

        return array_values($allTimes);
    }

    protected function getMaxSlotsPerDay(Brand $brand): int
    {
        $totalWeekly = 0;

        foreach (Platform::cases() as $platform) {
            if ($brand->isPlatformEnabled($platform)) {
                $totalWeekly += $brand->getPostingFrequency($platform);
            }
        }

        if ($totalWeekly === 0) {
            return 0;
        }

        return (int) ceil($totalWeekly / 7);
    }

    protected function assignPillars(Brand $brand, Collection $slots): Collection
    {
        $pillars = $brand->getContentPillars();

        if (empty($pillars)) {
            return $slots->map(fn ($slot) => array_merge($slot, ['pillar' => null]));
        }

        $totalSlots = $slots->count();
        $assignments = [];

        foreach ($pillars as $pillar) {
            $percentage = $pillar['percentage'] ?? 0;
            $count = max(1, (int) round($totalSlots * $percentage / 100));
            for ($i = 0; $i < $count; $i++) {
                $assignments[] = $pillar;
            }
        }

        // Trim or pad to match slot count
        $assignments = array_slice($assignments, 0, $totalSlots);
        while (count($assignments) < $totalSlots) {
            $assignments[] = $pillars[count($assignments) % count($pillars)];
        }

        shuffle($assignments);

        return $slots->values()->map(function ($slot, $index) use ($assignments) {
            return array_merge($slot, ['pillar' => $assignments[$index]]);
        });
    }

    protected function generateWithAi(Brand $brand, Collection $slots, string $language): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (! $apiKey) {
            throw new \RuntimeException('No OpenAI API key configured for this brand.');
        }

        $client = OpenAI::client($apiKey);

        // Split into batches if needed
        $batches = $slots->chunk(self::MAX_SLOTS_PER_BATCH);
        $allResults = [];

        foreach ($batches as $batch) {
            $systemPrompt = $this->buildSystemPrompt($brand, $language);
            $userPrompt = $this->buildUserPrompt($batch);

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 4096,
            ]);

            $parsed = $this->parseAiResponse($response->choices[0]->message->content, $batch->count());
            $allResults = array_merge($allResults, $parsed);
        }

        return $allResults;
    }

    protected function buildSystemPrompt(Brand $brand, string $language): string
    {
        $languageLabel = match ($language) {
            'pl' => 'Polish',
            'en' => 'English',
            default => $language,
        };

        $tone = $brand->getTone();
        $personality = $brand->getPersonality();
        $emojiUsage = $brand->getEmojiUsage();

        $prompt = "You are a social media content strategist. Generate content proposals for social media posts.\n";
        $prompt .= "IMPORTANT: Write ALL content in {$languageLabel}.\n";

        if ($tone) {
            $prompt .= "Tone of voice: {$tone}.\n";
        }

        if (! empty($personality)) {
            $prompt .= 'Brand personality: ' . implode(', ', $personality) . ".\n";
        }

        if ($emojiUsage) {
            $prompt .= "Emoji usage: {$emojiUsage}.\n";
        }

        $prompt .= <<<'PROMPT'

For each slot provided, generate a content proposal with:
- "title": A short, catchy topic title (max 100 characters)
- "keywords": Array of 3-5 relevant keywords/hashtags
- "notes": Brief content direction or angle (1-2 sentences)

Return ONLY valid JSON array. Each element must have "title", "keywords", and "notes" fields.
Do not wrap the JSON in markdown code blocks. Return raw JSON only.
PROMPT;

        return $prompt;
    }

    protected function buildUserPrompt(Collection $slots): string
    {
        $lines = ["Generate proposals for these time slots:\n"];

        foreach ($slots->values() as $index => $slot) {
            $line = ($index + 1) . ". Date: {$slot['date']}, Time: {$slot['time']}";

            if (! empty($slot['pillar'])) {
                $pillarName = $slot['pillar']['name'] ?? '';
                $topics = $slot['pillar']['topics'] ?? [];

                if ($pillarName) {
                    $line .= " | Content pillar: {$pillarName}";
                }
                if (! empty($topics)) {
                    $line .= ' | Topics: ' . implode(', ', $topics);
                }
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    protected function parseAiResponse(string $content, int $expectedCount): array
    {
        $content = trim($content);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse AI response as JSON: ' . json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('AI response is not an array.');
        }

        $convertNewlines = fn ($text) => is_string($text) ? str_replace('\n', "\n", $text) : $text;

        $results = [];
        foreach ($decoded as $item) {
            $results[] = [
                'title' => $convertNewlines($item['title'] ?? ''),
                'keywords' => $item['keywords'] ?? [],
                'notes' => $convertNewlines($item['notes'] ?? ''),
            ];
        }

        // Pad if AI returned fewer than expected
        while (count($results) < $expectedCount) {
            $results[] = ['title' => '', 'keywords' => [], 'notes' => ''];
        }

        return array_slice($results, 0, $expectedCount);
    }

    protected function createProposals(Brand $brand, User $user, Collection $slots, array $aiResults): Collection
    {
        $proposals = collect();

        foreach ($slots->values() as $index => $slot) {
            $aiData = $aiResults[$index] ?? ['title' => '', 'keywords' => [], 'notes' => ''];

            $proposal = $user->postProposals()->create([
                'brand_id' => $brand->id,
                'scheduled_date' => $slot['date'],
                'scheduled_time' => $slot['time'],
                'title' => $aiData['title'],
                'keywords' => $aiData['keywords'],
                'notes' => $aiData['notes'],
                'status' => ProposalStatus::Pending,
            ]);

            $proposals->push($proposal);
        }

        return $proposals;
    }
}
