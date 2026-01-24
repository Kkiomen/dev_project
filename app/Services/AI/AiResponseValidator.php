<?php

namespace App\Services\AI;

use App\Exceptions\InvalidAiResponseException;

class AiResponseValidator
{
    /**
     * Validate a content plan response from AI.
     */
    public function validateContentPlan(string $response): array
    {
        $data = $this->parseJson($response);

        if (!isset($data['posts']) || !is_array($data['posts'])) {
            throw new InvalidAiResponseException('Content plan must contain a "posts" array');
        }

        foreach ($data['posts'] as $index => $post) {
            $this->validatePlannedPost($post, $index);
        }

        return $data;
    }

    /**
     * Validate a single planned post entry.
     */
    protected function validatePlannedPost(array $post, int $index): void
    {
        $required = ['date', 'time', 'platform', 'pillar', 'topic', 'type'];

        foreach ($required as $field) {
            if (!isset($post[$field]) || empty($post[$field])) {
                throw new InvalidAiResponseException(
                    "Post at index {$index} is missing required field: {$field}"
                );
            }
        }

        // Validate platform
        $validPlatforms = ['facebook', 'instagram', 'youtube'];
        if (!in_array($post['platform'], $validPlatforms)) {
            throw new InvalidAiResponseException(
                "Post at index {$index} has invalid platform: {$post['platform']}"
            );
        }

        // Validate type
        $validTypes = ['text', 'carousel', 'video', 'story', 'reel', 'short'];
        if (!in_array($post['type'], $validTypes)) {
            throw new InvalidAiResponseException(
                "Post at index {$index} has invalid type: {$post['type']}"
            );
        }

        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['date'])) {
            throw new InvalidAiResponseException(
                "Post at index {$index} has invalid date format: {$post['date']}"
            );
        }

        // Validate time format (HH:MM)
        if (!preg_match('/^\d{2}:\d{2}$/', $post['time'])) {
            throw new InvalidAiResponseException(
                "Post at index {$index} has invalid time format: {$post['time']}"
            );
        }
    }

    /**
     * Validate generated content response from AI.
     */
    public function validateGeneratedContent(string $response): array
    {
        $data = $this->parseJson($response);

        if (!isset($data['title']) || empty($data['title'])) {
            throw new InvalidAiResponseException('Generated content must contain a "title"');
        }

        if (!isset($data['main_caption']) || empty($data['main_caption'])) {
            throw new InvalidAiResponseException('Generated content must contain a "main_caption"');
        }

        // Validate platforms object if present
        if (isset($data['platforms']) && is_array($data['platforms'])) {
            foreach ($data['platforms'] as $platform => $content) {
                if (!is_array($content)) {
                    throw new InvalidAiResponseException(
                        "Platform content for {$platform} must be an object"
                    );
                }
            }
        }

        // Validate image keywords if present
        if (isset($data['image_keywords']) && !is_array($data['image_keywords'])) {
            throw new InvalidAiResponseException('image_keywords must be an array');
        }

        return $data;
    }

    /**
     * Parse JSON from AI response, handling markdown code blocks.
     */
    protected function parseJson(string $content): array
    {
        $content = trim($content);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidAiResponseException(
                'Failed to parse AI response as JSON: ' . json_last_error_msg()
            );
        }

        return $decoded;
    }
}
