<?php

namespace App\Services\AI;

use App\Services\OpenAiClientService;

class BrandSuggestionService
{
    public function __construct(
        protected OpenAiClientService $openAiClient
    ) {}

    /**
     * Generate suggestions based on brand description.
     */
    public function generateSuggestions(string $type, array $brandData): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($type, $brandData);

        $response = retry(3, function () use ($systemPrompt, $userPrompt) {
            return $this->openAiClient->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ]);
        }, 1000);

        $content = $response->choices[0]->message->content;

        return $this->parseResponse($content, $type);
    }

    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert marketing strategist and brand consultant. Your task is to analyze brand information and generate highly relevant suggestions.

RULES:
1. Generate practical, actionable suggestions
2. Be specific to the brand's industry and description
3. Consider the target audience when generating suggestions
4. Respond ONLY with valid JSON - no additional text
5. Use the same language as the brand description (if Polish, respond in Polish; if English, respond in English)
PROMPT;
    }

    protected function buildUserPrompt(string $type, array $brandData): string
    {
        $name = $brandData['name'] ?? '';
        $description = $brandData['description'] ?? '';
        $industry = $brandData['industry'] ?? '';
        $ageRange = $brandData['ageRange'] ?? $brandData['age_range'] ?? '';
        $gender = $brandData['gender'] ?? '';

        $context = <<<CONTEXT
BRAND INFORMATION:
- Name: {$name}
- Industry: {$industry}
- Description: {$description}
- Target age range: {$ageRange}
- Target gender: {$gender}
CONTEXT;

        return match ($type) {
            'interests' => $this->buildInterestsPrompt($context),
            'painPoints' => $this->buildPainPointsPrompt($context),
            'contentPillars' => $this->buildContentPillarsPrompt($context),
            default => throw new \InvalidArgumentException("Unknown suggestion type: {$type}"),
        };
    }

    protected function buildInterestsPrompt(string $context): string
    {
        return <<<PROMPT
{$context}

Generate 6-8 specific interests that the target audience of this brand would have.
These should be topics, hobbies, or areas of interest that would help create relevant content.

Respond with JSON:
{
  "interests": ["interest1", "interest2", "interest3", ...]
}
PROMPT;
    }

    protected function buildPainPointsPrompt(string $context): string
    {
        return <<<PROMPT
{$context}

Generate 5-7 specific pain points, challenges, or problems that the target audience of this brand faces.
These should be real issues that the brand can address through its products/services or content.

Respond with JSON:
{
  "painPoints": ["pain point 1", "pain point 2", "pain point 3", ...]
}
PROMPT;
    }

    protected function buildContentPillarsPrompt(string $context): string
    {
        return <<<PROMPT
{$context}

Generate 4-5 content pillars (main content themes/categories) for this brand's social media strategy.
Each pillar should include a name, brief description, and suggested percentage of content.
The percentages must sum to exactly 100.

Respond with JSON:
{
  "contentPillars": [
    {
      "name": "Pillar Name",
      "description": "Brief description of this content category",
      "percentage": 25
    },
    ...
  ]
}
PROMPT;
    }

    protected function parseResponse(string $content, string $type): array
    {
        // Clean up response
        $content = trim($content);

        // Remove markdown code blocks if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse AI response: ' . json_last_error_msg());
        }

        return match ($type) {
            'interests' => ['interests' => $data['interests'] ?? []],
            'painPoints' => ['painPoints' => $data['painPoints'] ?? []],
            'contentPillars' => ['contentPillars' => $data['contentPillars'] ?? []],
            default => $data,
        };
    }
}
