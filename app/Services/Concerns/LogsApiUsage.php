<?php

namespace App\Services\Concerns;

use App\Enums\ApiProvider;
use App\Models\AiOperationLog;
use App\Models\Brand;
use Illuminate\Support\Str;

trait LogsApiUsage
{
    protected ?string $currentRequestId = null;

    /**
     * Generate or get a request ID for grouping related API calls.
     */
    protected function getRequestId(): string
    {
        if ($this->currentRequestId === null) {
            $this->currentRequestId = Str::uuid()->toString();
        }

        return $this->currentRequestId;
    }

    /**
     * Reset the request ID (call at the start of a new logical operation).
     */
    protected function resetRequestId(): void
    {
        $this->currentRequestId = null;
    }

    /**
     * Start logging an AI API request.
     */
    protected function logAiStart(
        ?Brand $brand,
        string $operation,
        array $input,
        ?string $model = null
    ): AiOperationLog {
        return AiOperationLog::startAiRequest(
            $brand,
            $operation,
            $input,
            ApiProvider::OPENAI,
            $model ?? config('services.openai.model', 'gpt-4o'),
            $this->getRequestId()
        );
    }

    /**
     * Start logging an external API request.
     */
    protected function logExternalStart(
        ?Brand $brand,
        string $operation,
        ApiProvider $provider,
        string $endpoint,
        array $input = []
    ): AiOperationLog {
        return AiOperationLog::startExternalRequest(
            $brand,
            $operation,
            $provider,
            $endpoint,
            $input,
            $this->getRequestId()
        );
    }

    /**
     * Complete an AI log with token usage and cost calculation.
     */
    protected function completeAiLog(
        AiOperationLog $log,
        array $output,
        int $promptTokens,
        int $completionTokens,
        int $durationMs
    ): AiOperationLog {
        $cost = $this->calculateOpenAiCost(
            $promptTokens,
            $completionTokens,
            $log->model ?? config('services.openai.model', 'gpt-4o')
        );

        return $log->completeAi($output, $promptTokens, $completionTokens, $cost, $durationMs);
    }

    /**
     * Complete an external API log.
     */
    protected function completeExternalLog(
        AiOperationLog $log,
        array $output,
        int $httpStatus,
        int $durationMs
    ): AiOperationLog {
        return $log->completeExternal($output, $httpStatus, $durationMs);
    }

    /**
     * Mark a log as failed.
     */
    protected function failLog(
        AiOperationLog $log,
        string $errorMessage,
        int $durationMs = 0,
        ?int $httpStatus = null
    ): AiOperationLog {
        return $log->fail($errorMessage, $durationMs, $httpStatus);
    }

    /**
     * Calculate OpenAI API cost based on token usage.
     */
    protected function calculateOpenAiCost(int $promptTokens, int $completionTokens, string $model): float
    {
        $pricing = config("api_pricing.openai.{$model}", config('api_pricing.openai.default'));

        $inputCost = ($promptTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($completionTokens / 1_000_000) * $pricing['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Helper to measure execution time and log an AI operation.
     *
     * @param callable $operation The operation to execute
     * @param Brand|null $brand The brand context
     * @param string $operationName Name of the operation
     * @param array $input Input data to log
     * @param string|null $model AI model used
     * @return array{result: mixed, log: AiOperationLog}
     */
    protected function executeAndLogAi(
        callable $operation,
        ?Brand $brand,
        string $operationName,
        array $input,
        ?string $model = null
    ): array {
        $startTime = microtime(true);
        $log = $this->logAiStart($brand, $operationName, $input, $model);

        try {
            $result = $operation();
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Extract token usage from result if available
            $promptTokens = $result['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $result['usage']['completion_tokens'] ?? 0;
            $output = $result['output'] ?? $result;

            $this->completeAiLog($log, $output, $promptTokens, $completionTokens, $durationMs);

            return ['result' => $result, 'log' => $log];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            throw $e;
        }
    }

    /**
     * Helper to measure execution time and log an external API operation.
     *
     * @param callable $operation The operation to execute
     * @param Brand|null $brand The brand context
     * @param string $operationName Name of the operation
     * @param ApiProvider $provider API provider
     * @param string $endpoint API endpoint
     * @param array $input Input data to log
     * @return array{result: mixed, log: AiOperationLog}
     */
    protected function executeAndLogExternal(
        callable $operation,
        ?Brand $brand,
        string $operationName,
        ApiProvider $provider,
        string $endpoint,
        array $input = []
    ): array {
        $startTime = microtime(true);
        $log = $this->logExternalStart($brand, $operationName, $provider, $endpoint, $input);

        try {
            $result = $operation();
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $httpStatus = $result['http_status'] ?? 200;
            $output = $result['output'] ?? $result;

            $this->completeExternalLog($log, $output, $httpStatus, $durationMs);

            return ['result' => $result, 'log' => $log];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $httpStatus = $e->getCode() ?: null;
            $this->failLog($log, $e->getMessage(), $durationMs, $httpStatus);

            throw $e;
        }
    }
}
