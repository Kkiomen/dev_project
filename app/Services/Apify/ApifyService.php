<?php

namespace App\Services\Apify;

use App\Enums\AiProvider;
use App\Enums\ApifyActorType;
use App\Enums\ScrapeStatus;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\CiCostTracking;
use App\Models\CiScrapeRun;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyService
{
    protected string $baseUrl = 'https://api.apify.com/v2';
    protected int $timeout = 30;

    public function startRun(Brand $brand, ApifyActorType $actorType, array $input = []): CiScrapeRun
    {
        $apiToken = $this->getApiToken($brand);

        $estimatedResults = $this->estimateResultCount($input);
        $estimatedCost = $estimatedResults * $actorType->estimatedCostPerResult();

        $scrapeRun = CiScrapeRun::create([
            'brand_id' => $brand->id,
            'actor_type' => $actorType,
            'status' => ScrapeStatus::Pending,
            'input_params' => $input,
            'estimated_cost' => $estimatedCost,
        ]);

        Log::info('[ApifyService] Starting run', [
            'brand_id' => $brand->id,
            'actor' => $actorType->actorId(),
            'estimated_cost' => $estimatedCost,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($apiToken)
                ->post("{$this->baseUrl}/acts/{$actorType->actorId()}/runs", $input);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();
                $scrapeRun->markAsFailed("Apify API error: {$error}");
                throw new Exception("Apify API error: {$error}");
            }

            $data = $response->json('data');
            $scrapeRun->markAsRunning($data['id']);

            Log::info('[ApifyService] Run started', [
                'run_id' => $data['id'],
                'scrape_run_id' => $scrapeRun->id,
            ]);

            return $scrapeRun;
        } catch (Exception $e) {
            if ($scrapeRun->status !== ScrapeStatus::Failed) {
                $scrapeRun->markAsFailed($e->getMessage());
            }
            throw $e;
        }
    }

    public function getRunStatus(Brand $brand, string $apifyRunId): array
    {
        $apiToken = $this->getApiToken($brand);

        $response = Http::timeout($this->timeout)
            ->withToken($apiToken)
            ->get("{$this->baseUrl}/actor-runs/{$apifyRunId}");

        if ($response->failed()) {
            throw new Exception("Failed to get run status: " . $response->body());
        }

        $data = $response->json('data');

        return [
            'status' => $data['status'],
            'started_at' => $data['startedAt'] ?? null,
            'finished_at' => $data['finishedAt'] ?? null,
            'usage_usd' => $data['usageTotalUsd'] ?? null,
            'dataset_id' => $data['defaultDatasetId'] ?? null,
        ];
    }

    public function getRunResults(Brand $brand, string $apifyRunId, int $limit = 1000): array
    {
        $apiToken = $this->getApiToken($brand);

        $status = $this->getRunStatus($brand, $apifyRunId);
        $datasetId = $status['dataset_id'] ?? null;

        if (!$datasetId) {
            throw new Exception("No dataset found for run: {$apifyRunId}");
        }

        $response = Http::timeout(60)
            ->withToken($apiToken)
            ->get("{$this->baseUrl}/datasets/{$datasetId}/items", [
                'limit' => $limit,
                'format' => 'json',
            ]);

        if ($response->failed()) {
            throw new Exception("Failed to get run results: " . $response->body());
        }

        return $response->json() ?? [];
    }

    public function isBudgetExceeded(Brand $brand): bool
    {
        $tracking = CiCostTracking::getOrCreateForBrand($brand->id);
        return $tracking->isBudgetExceeded();
    }

    public function getRemainingBudget(Brand $brand): float
    {
        $tracking = CiCostTracking::getOrCreateForBrand($brand->id);
        return $tracking->getRemainingBudget();
    }

    public function recordCost(Brand $brand, CiScrapeRun $scrapeRun): void
    {
        $tracking = CiCostTracking::getOrCreateForBrand($brand->id);
        $cost = $scrapeRun->actual_cost ?? $scrapeRun->estimated_cost;
        $tracking->addCost($cost, $scrapeRun->actor_type->value, $scrapeRun->results_count);
    }

    protected function getApiToken(Brand $brand): string
    {
        $token = BrandAiKey::getKeyForProvider($brand, AiProvider::Apify);

        if (!$token) {
            throw new Exception("No Apify API key configured for brand: {$brand->name}");
        }

        return $token;
    }

    protected function estimateResultCount(array $input): int
    {
        if (isset($input['maxItems'])) {
            return (int) $input['maxItems'];
        }

        $handles = $input['usernames'] ?? $input['profiles'] ?? $input['handles'] ?? [];
        if (!empty($handles)) {
            $resultsPerHandle = $input['resultsPerPage'] ?? $input['maxPosts'] ?? 12;
            return count($handles) * $resultsPerHandle;
        }

        return 10;
    }
}
