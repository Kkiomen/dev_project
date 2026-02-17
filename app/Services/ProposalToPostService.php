<?php

namespace App\Services;

use App\Enums\AiProvider;
use App\Enums\PostStatus;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\PostProposal;
use App\Models\SocialPost;
use Carbon\Carbon;
use OpenAI;

class ProposalToPostService
{
    public function generate(PostProposal $proposal): SocialPost
    {
        $brand = $proposal->brand;

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (! $apiKey) {
            throw new \RuntimeException('No OpenAI API key configured for this brand.');
        }

        $client = OpenAI::client($apiKey);

        $response = $client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt($brand)],
                ['role' => 'user', 'content' => $this->buildUserPrompt($proposal)],
            ],
            'max_tokens' => 2048,
        ]);

        $parsed = $this->parseResponse($response->choices[0]->message->content);

        $scheduledAt = null;
        if ($proposal->scheduled_date) {
            $scheduledAt = Carbon::parse($proposal->scheduled_date);
            if ($proposal->scheduled_time) {
                $scheduledAt->setTimeFromTimeString($proposal->scheduled_time);
            }
        }

        $post = $proposal->user->socialPosts()->create([
            'brand_id' => $brand->id,
            'title' => $parsed['title'],
            'text_prompt' => $parsed['text_prompt'],
            'status' => PostStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);

        $post->createPlatformPosts();

        $proposal->markAsUsed($post);

        return $post;
    }

    protected function buildSystemPrompt(Brand $brand): string
    {
        $language = $brand->getLanguage();
        $tone = $brand->getTone();
        $personality = $brand->getPersonality();
        $emojiUsage = $brand->getEmojiUsage();

        $languageLabel = match ($language) {
            'pl' => 'Polish',
            'en' => 'English',
            default => $language,
        };

        $prompt = "You are a social media content strategist. Based on the given proposal data, create a detailed brief/description of what a social media post should contain.\n";
        $prompt .= "IMPORTANT: Write ALL content in {$languageLabel}.\n";
        $prompt .= "DO NOT write the final post text. Instead, describe what the post should cover, what key points to include, what angle to take, and what call-to-action to use.\n";

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

Return ONLY valid JSON with exactly two fields:
- "title": A short, catchy title for the post (max 100 characters)
- "text_prompt": A detailed description/brief of what the post should contain - key points, angle, structure, and call-to-action. This will be used as instructions for generating the final post text. Do NOT write the actual post caption here.

Do not wrap the JSON in markdown code blocks. Return raw JSON only.
PROMPT;

        return $prompt;
    }

    protected function buildUserPrompt(PostProposal $proposal): string
    {
        $parts = [];

        if ($proposal->title) {
            $parts[] = "Topic: {$proposal->title}";
        }

        if ($proposal->keywords && count($proposal->keywords) > 0) {
            $parts[] = 'Keywords: ' . implode(', ', $proposal->keywords);
        }

        if ($proposal->notes) {
            $parts[] = "Additional notes: {$proposal->notes}";
        }

        return implode("\n", $parts);
    }

    protected function parseResponse(string $content): array
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

        // Convert literal \n to actual newlines
        $convertNewlines = fn ($text) => is_string($text) ? str_replace('\n', "\n", $text) : $text;

        return [
            'title' => $convertNewlines($decoded['title'] ?? ''),
            'text_prompt' => $convertNewlines($decoded['text_prompt'] ?? ''),
        ];
    }
}
