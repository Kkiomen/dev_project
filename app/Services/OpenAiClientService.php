<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class OpenAiClientService
{
    protected Client $client;

    protected string $model;

    protected int $maxTokens;

    public function __construct()
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured');
        }

        $this->client = OpenAI::client($apiKey);
        $this->model = config('services.openai.model', 'gpt-4o');
        $this->maxTokens = config('services.openai.max_tokens', 4096);
    }

    /**
     * Send a chat completion request with optional tools (function calling).
     */
    public function chatCompletion(array $messages, array $tools = []): CreateResponse
    {
        $params = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
        ];

        if (! empty($tools)) {
            $params['tools'] = $tools;
            $params['tool_choice'] = 'auto';
        }

        return $this->client->chat()->create($params);
    }

    /**
     * Get the configured model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
