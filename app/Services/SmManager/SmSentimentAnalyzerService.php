<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmSentimentAnalyzerService
{
    use LogsApiUsage;

    protected const MODEL = 'gpt-4o';

    /**
     * Analyze a single text for sentiment.
     *
     * @return array{success: bool, sentiment?: string, confidence?: float, is_crisis?: bool, error?: string, error_code?: string}
     */
    public function analyze(Brand $brand, string $text): array
    {
        $result = $this->analyzeBatch($brand, [
            ['id' => 'single', 'text' => $text],
        ]);

        if (!$result['success']) {
            return $result;
        }

        $item = $result['results'][0] ?? null;

        if (!$item) {
            return ['success' => false, 'error' => 'No result returned from analysis'];
        }

        return [
            'success' => true,
            'sentiment' => $item['sentiment'],
            'confidence' => $item['confidence'],
            'is_crisis' => $item['is_crisis'],
        ];
    }

    /**
     * Batch analysis for multiple texts (more efficient, single API call).
     *
     * @param Brand $brand
     * @param array $texts Array of ['id' => '...', 'text' => '...']
     * @return array{success: bool, results?: array, error?: string, error_code?: string}
     */
    public function analyzeBatch(Brand $brand, array $texts): array
    {
        if (empty($texts)) {
            return ['success' => true, 'results' => []];
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildPrompt($texts);
        $userPrompt = $this->buildUserMessage($texts);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_sentiment_analyze_batch', [
            'text_count' => count($texts),
            'text_ids' => array_column($texts, 'id'),
        ], self::MODEL);

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => self::MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 2048,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            $results = $parsed['results'] ?? [];

            $normalized = array_map(fn (array $item) => [
                'id' => $item['id'] ?? null,
                'sentiment' => $this->normalizeSentiment($item['sentiment'] ?? 'neutral'),
                'confidence' => (float) ($item['confidence'] ?? 0.5),
                'is_crisis' => (bool) ($item['is_crisis'] ?? false),
            ], $results);

            return [
                'success' => true,
                'results' => $normalized,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmSentimentAnalyzer: analyzeBatch failed', [
                'brand_id' => $brand->id,
                'text_count' => count($texts),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt for sentiment analysis.
     */
    protected function buildPrompt(array $texts): string
    {
        $count = count($texts);

        return <<<PROMPT
You are a sentiment analysis expert specializing in social media content. Your task is to analyze {$count} text(s) and determine their sentiment.

For each text, provide:
- id: The original ID of the text
- sentiment: One of "positive", "neutral", or "negative"
- confidence: A float between 0.0 and 1.0 indicating how confident you are
- is_crisis: A boolean indicating whether this text represents a crisis situation

CRISIS CRITERIA:
A text should be flagged as crisis (is_crisis: true) if it contains ANY of:
- Direct threats (legal, physical, or reputational)
- Severe complaints indicating systemic product/service failures
- Viral negativity potential (call to boycott, mass outrage indicators)
- Urgent safety or health-related issues
- Accusations of fraud, scam, or illegal activity
- Mentions of media/press involvement in a negative context

RESPONSE FORMAT:
Respond with valid JSON only. No additional text.
{
  "results": [
    {
      "id": "original_id",
      "sentiment": "positive|neutral|negative",
      "confidence": 0.95,
      "is_crisis": false
    }
  ]
}
PROMPT;
    }

    /**
     * Build the user message containing the texts to analyze.
     */
    protected function buildUserMessage(array $texts): string
    {
        $lines = ["Analyze the following texts for sentiment:\n"];

        foreach ($texts as $item) {
            $id = $item['id'];
            $text = mb_substr($item['text'], 0, 1000);
            $lines[] = "--- ID: {$id} ---";
            $lines[] = $text;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Parse the AI JSON response, handling markdown code blocks.
     */
    protected function parseResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        if (!str_starts_with($content, '{') && !str_starts_with($content, '[')) {
            if (preg_match('/[\{\[][\s\S]*[\}\]]/', $content, $matches)) {
                $content = $matches[0];
            }
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('SmSentimentAnalyzer: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 300),
            ]);

            return ['results' => []];
        }

        return $decoded;
    }

    /**
     * Normalize sentiment value to one of the allowed values.
     */
    protected function normalizeSentiment(string $sentiment): string
    {
        $sentiment = strtolower(trim($sentiment));

        return match (true) {
            in_array($sentiment, ['positive', 'pos']) => 'positive',
            in_array($sentiment, ['negative', 'neg']) => 'negative',
            default => 'neutral',
        };
    }
}
