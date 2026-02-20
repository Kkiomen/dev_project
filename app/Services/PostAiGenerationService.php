<?php

namespace App\Services;

use App\Models\Brand;
use App\Services\Apify\ContentInsightsService;
use App\Services\Concerns\LogsApiUsage;
use OpenAI\Responses\Chat\CreateResponse;

class PostAiGenerationService
{
    use LogsApiUsage;

    public function __construct(
        protected OpenAiClientService $openAiClient
    ) {}

    /**
     * Generate social media post content for a single platform using AI.
     */
    public function generate(array $config, ?Brand $brand = null): array
    {
        $startTime = microtime(true);

        // Start API usage logging
        $log = $this->logAiStart($brand, 'post_content_generation', [
            'platform' => $config['platform'],
            'topic' => $config['topic'] ?? null,
            'tone' => $config['tone'] ?? null,
            'length' => $config['length'] ?? null,
        ]);

        try {
            $systemPrompt = $this->buildSystemPrompt($config);
            $userPrompt = $this->buildUserPrompt($config);

            $response = $this->openAiClient->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ]);

            $result = $this->parseResponse($response, $config['platform']);

            // Complete logging with token usage
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->completeAiLog(
                $log,
                $result,
                $response->usage->promptTokens ?? 0,
                $response->usage->completionTokens ?? 0,
                $durationMs
            );

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            throw $e;
        }
    }

    /**
     * Build the system prompt with guidelines for content generation.
     */
    protected function buildSystemPrompt(array $config): string
    {
        $language = $config['language'] === 'pl' ? 'Polish' : 'English';
        $tone = $this->getToneDescription($config['tone'], $config['language']);
        $length = $this->getLengthDescription($config['length'], $config['language']);
        $platform = $config['platform'];
        $platformGuidelines = $this->getPlatformGuidelines($platform, $config['language']);

        return <<<PROMPT
You are an expert social media marketer. You create engaging, high-quality content for {$platform}.

RULES:
1. Write in {$language}
2. Tone: {$tone}
3. Length: {$length}
4. Create content that drives engagement and interaction

PLATFORM-SPECIFIC GUIDELINES:
{$platformGuidelines}

IMPORTANT FORMATTING RULES:
- DO NOT use Unicode bold/italic formatting (like 𝗕𝗼𝗹𝗱 or 𝘐𝘵𝘢𝘭𝘪𝘤) - use plain text only
- Use emojis where appropriate to make content more engaging
- Keep paragraphs short and scannable
- Use line breaks for readability

{$this->getCompetitiveContext($config)}
RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text before or after the JSON.
{$this->getResponseFormat($platform)}
PROMPT;
    }

    /**
     * Get platform-specific guidelines.
     */
    protected function getPlatformGuidelines(string $platform, string $language): string
    {
        $guidelines = [
            'facebook' => [
                'en' => 'Create engaging posts with questions for the community, clear call-to-action (CTA). Focus on starting conversations and building community engagement. Posts can include links and longer text.',
                'pl' => 'Twórz angażujące posty z pytaniami do społeczności, wyraźne wezwanie do działania (CTA). Skup się na rozpoczynaniu rozmów i budowaniu zaangażowania społeczności. Posty mogą zawierać linki i dłuższy tekst.',
            ],
            'instagram' => [
                'en' => 'Create visually-focused captions, max 2200 characters. Add relevant hashtags (5-10) at the end. Focus on storytelling and emotional connection. Use line breaks for readability.',
                'pl' => 'Twórz opisy skupione na wizualności, max 2200 znaków. Dodaj odpowiednie hashtagi (5-10) na końcu. Skup się na storytellingu i emocjonalnym połączeniu. Używaj łamania linii dla czytelności.',
            ],
            'youtube' => [
                'en' => 'Create a catchy, SEO-friendly video title (max 100 chars) and informative description. Description should include: hook in first 2 lines, main content summary, timestamps if applicable, and call-to-action.',
                'pl' => 'Stwórz chwytliwy, przyjazny SEO tytuł wideo (max 100 znaków) i informacyjny opis. Opis powinien zawierać: haczyk w pierwszych 2 liniach, podsumowanie treści, timestampy jeśli dotyczy, i wezwanie do działania.',
            ],
        ];

        return $guidelines[$platform][$language] ?? $guidelines[$platform]['en'];
    }

    /**
     * Get the expected response format for a platform.
     */
    protected function getResponseFormat(string $platform): string
    {
        // Always include hashtags for Instagram use
        $formats = [
            'facebook' => '{
  "caption": "Facebook post caption with engagement hooks and CTA",
  "hashtags": ["#hashtag1", "#hashtag2", "#hashtag3", "#hashtag4", "#hashtag5"]
}',
            'instagram' => '{
  "caption": "Instagram caption without hashtags",
  "hashtags": ["#hashtag1", "#hashtag2", "#hashtag3", "#hashtag4", "#hashtag5"]
}',
            'youtube' => '{
  "title": "Video title (max 100 chars)",
  "description": "YouTube video description with hook, content summary, and CTA",
  "hashtags": ["#hashtag1", "#hashtag2", "#hashtag3", "#hashtag4", "#hashtag5"]
}',
        ];

        return $formats[$platform];
    }

    /**
     * Build the user prompt with the specific request.
     */
    protected function buildUserPrompt(array $config): string
    {
        $prompt = "Create {$config['platform']} content about: {$config['topic']}\n";

        if (! empty($config['customPrompt'])) {
            $prompt .= "Additional instructions: {$config['customPrompt']}\n";
        }

        return $prompt;
    }

    /**
     * Parse the AI response and extract content.
     */
    protected function parseResponse(CreateResponse $response, string $platform): array
    {
        $content = $response->choices[0]->message->content;

        // Try to extract JSON from the response
        $content = trim($content);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse AI response as JSON: '.json_last_error_msg());
        }

        // Convert literal \n to actual newlines in text fields
        $convertNewlines = fn ($text) => is_string($text) ? str_replace('\n', "\n", $text) : $text;

        // Always include hashtags in response (for Instagram use)
        $hashtags = $decoded['hashtags'] ?? [];

        // Return platform-specific structure
        return match ($platform) {
            'facebook' => [
                'caption' => $convertNewlines($decoded['caption'] ?? ''),
                'hashtags' => $hashtags,
            ],
            'instagram' => [
                'caption' => $convertNewlines($decoded['caption'] ?? ''),
                'hashtags' => $hashtags,
            ],
            'youtube' => [
                'title' => $convertNewlines($decoded['title'] ?? ''),
                'description' => $convertNewlines($decoded['description'] ?? ''),
                'hashtags' => $hashtags,
            ],
            default => $decoded,
        };
    }

    /**
     * Get tone description based on the selected tone.
     */
    protected function getToneDescription(string $tone, string $language): string
    {
        $descriptions = [
            'professional' => [
                'en' => 'Professional and authoritative - use formal language, industry expertise, and credibility',
                'pl' => 'Profesjonalny i autorytatywny - używaj formalnego języka, ekspertyzy branżowej i buduj wiarygodność',
            ],
            'casual' => [
                'en' => 'Casual and friendly - conversational tone, approachable, like talking to a friend',
                'pl' => 'Casualowy i przyjazny - ton konwersacyjny, przystępny, jak rozmowa z przyjacielem',
            ],
            'playful' => [
                'en' => 'Playful and fun - use humor, wit, emojis, and creative expressions',
                'pl' => 'Zabawny i wesoły - używaj humoru, dowcipu, emoji i kreatywnych wyrażeń',
            ],
            'inspirational' => [
                'en' => 'Inspirational and motivating - uplifting language, encourage action, share wisdom',
                'pl' => 'Inspirujący i motywujący - podnoszący na duchu język, zachęcaj do działania, dziel się mądrością',
            ],
        ];

        return $descriptions[$tone][$language] ?? $descriptions[$tone]['en'];
    }

    /**
     * Get length description based on the selected length.
     */
    protected function getLengthDescription(string $length, string $language): string
    {
        $descriptions = [
            'short' => [
                'en' => 'Short (1-2 sentences) - concise, punchy, gets straight to the point',
                'pl' => 'Krótki (1-2 zdania) - zwięzły, dynamiczny, od razu do rzeczy',
            ],
            'medium' => [
                'en' => 'Medium (3-4 sentences) - balanced, provides context while staying focused',
                'pl' => 'Średni (3-4 zdania) - zbalansowany, dostarcza kontekst pozostając skupionym',
            ],
            'long' => [
                'en' => 'Long (5+ sentences) - detailed, storytelling, comprehensive coverage',
                'pl' => 'Długi (5+ zdań) - szczegółowy, narracyjny, wyczerpujące omówienie',
            ],
        ];

        return $descriptions[$length][$language] ?? $descriptions[$length]['en'];
    }

    /**
     * Modify existing post content based on user instruction.
     */
    public function modify(string $currentCaption, ?string $currentTitle, string $instruction, string $language = 'pl'): array
    {
        $langName = $language === 'pl' ? 'Polish' : 'English';

        $systemPrompt = <<<PROMPT
You are an expert social media content editor. You modify existing post content based on user instructions.

RULES:
1. Write in {$langName}
2. Maintain the original meaning and key information unless asked to change it
3. Apply the user's requested changes precisely
4. Keep the content engaging and suitable for social media
5. DO NOT use Unicode bold/italic formatting - use plain text only
6. Use emojis where appropriate

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text before or after the JSON.
{
  "modified": true,
  "caption": "The modified caption text",
  "title": "The modified title (if applicable, otherwise null)",
  "message": "Brief description of what was changed"
}

If you cannot make the requested change, respond with:
{
  "modified": false,
  "caption": null,
  "title": null,
  "message": "Explanation of why the change cannot be made"
}
PROMPT;

        $userPrompt = "Current caption:\n{$currentCaption}\n\n";
        if ($currentTitle) {
            $userPrompt .= "Current title: {$currentTitle}\n\n";
        }
        $userPrompt .= "User instruction: {$instruction}";

        $response = $this->openAiClient->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ]);

        $content = trim($response->choices[0]->message->content);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse AI response as JSON: ' . json_last_error_msg());
        }

        // Convert literal \n to actual newlines
        if (isset($decoded['caption']) && is_string($decoded['caption'])) {
            $decoded['caption'] = str_replace('\n', "\n", $decoded['caption']);
        }

        return $decoded;
    }

    protected function getCompetitiveContext(array $config): string
    {
        try {
            $brand = $config['brand'] ?? null;
            if (!$brand instanceof Brand) {
                return '';
            }

            $insightsService = app(ContentInsightsService::class);
            $context = $insightsService->getContentGenerationContext($brand, $config['platform'] ?? null);

            $lines = [];

            if (!empty($context['effective_hooks'])) {
                $lines[] = "COMPETITIVE INSIGHTS:";
                $lines[] = "- Most effective hook types: " . implode(', ', $context['effective_hooks']);
            }

            if (!empty($context['effective_ctas'])) {
                $lines[] = "- Most effective CTA types: " . implode(', ', $context['effective_ctas']);
            }

            if (!empty($context['style_tips'])) {
                foreach ($context['style_tips'] as $tip) {
                    $lines[] = "- {$tip}";
                }
            }

            return !empty($lines) ? implode("\n", $lines) . "\n" : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
