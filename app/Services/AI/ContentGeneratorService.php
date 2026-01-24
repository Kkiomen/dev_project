<?php

namespace App\Services\AI;

use App\Models\AiOperationLog;
use App\Models\Brand;
use App\Services\Concerns\LogsApiUsage;
use App\Services\OpenAiClientService;
use Illuminate\Support\Facades\Cache;

class ContentGeneratorService
{
    use LogsApiUsage;

    public function __construct(
        protected OpenAiClientService $openAiClient,
        protected AiResponseValidator $validator
    ) {}

    /**
     * Generate full post content from a planned topic.
     */
    public function generate(Brand $brand, array $config, ?array $userSettings = null): array
    {
        $startTime = microtime(true);

        // Start logging with detailed tracking
        $log = $this->logAiStart($brand, 'content_generation', $config);

        try {
            $context = $this->getBrandContext($brand);

            // Merge user's custom instructions from settings
            if ($userSettings && !empty($userSettings['ai']['customInstructions'])) {
                $config['custom_instructions'] = trim(
                    ($config['custom_instructions'] ?? '') . "\n" . $userSettings['ai']['customInstructions']
                );
            }

            $systemPrompt = $this->buildSystemPrompt($brand, $context, $userSettings);
            $userPrompt = $this->buildUserPrompt($config, $context);

            $response = retry(3, function () use ($systemPrompt, $userPrompt) {
                return $this->openAiClient->chatCompletion([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ]);
            }, 1000);

            $content = $response->choices[0]->message->content;
            $generatedContent = $this->validator->validateGeneratedContent($content);

            // Post-process the content
            $generatedContent = $this->postProcessContent($generatedContent);

            // Complete logging with detailed token usage
            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog(
                $log,
                $generatedContent,
                $promptTokens,
                $completionTokens,
                $durationMs
            );

            return $generatedContent;
        } catch (\Exception $e) {
            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            throw $e;
        }
    }

    /**
     * Regenerate content with feedback.
     */
    public function regenerate(Brand $brand, array $config, string $feedback, ?array $userSettings = null): array
    {
        $config['feedback'] = $feedback;

        return $this->generate($brand, $config, $userSettings);
    }

    /**
     * Get cached brand context.
     */
    protected function getBrandContext(Brand $brand): array
    {
        return Cache::remember(
            "brand:{$brand->id}:ai_context",
            3600,
            fn() => $brand->buildAiContext()
        );
    }

    /**
     * Get full language name from code.
     */
    protected function getLanguageName(string $code): string
    {
        $languages = [
            'pl' => 'Polish',
            'en' => 'English',
            'de' => 'German',
            'es' => 'Spanish',
            'fr' => 'French',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            'cs' => 'Czech',
            'sk' => 'Slovak',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'bg' => 'Bulgarian',
            'hr' => 'Croatian',
            'sl' => 'Slovenian',
            'sr' => 'Serbian',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'el' => 'Greek',
            'tr' => 'Turkish',
            'ar' => 'Arabic',
            'he' => 'Hebrew',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'hi' => 'Hindi',
        ];

        return $languages[$code] ?? 'English';
    }

    /**
     * Build the system prompt for content generation.
     */
    protected function buildSystemPrompt(Brand $brand, array $context, ?array $userSettings = null): string
    {
        $language = $this->getLanguageName($context['voice']['language'] ?? 'en');
        $tone = $context['voice']['tone'];
        $personality = implode(', ', $context['voice']['personality'] ?? []);
        $emojiUsage = $this->getEmojiGuideline($context['voice']['emoji_usage']);

        // Get creativity and length preferences from user settings
        $creativityLevel = $userSettings['ai']['creativityLevel'] ?? 'balanced';
        $defaultLength = $userSettings['ai']['defaultLength'] ?? 'medium';

        $creativityInstruction = $this->getCreativityInstruction($creativityLevel);
        $lengthInstruction = $this->getLengthInstruction($defaultLength);

        return <<<PROMPT
You are an expert social media content creator. Your task is to create engaging, high-quality posts.

BRAND VOICE:
- Tone: {$tone}
- Personality: {$personality}
- Language: {$language}
- Emoji usage: {$emojiUsage}

TARGET AUDIENCE:
- Age range: {$context['target_audience']['age_range']}
- Interests: {$this->formatArray($context['target_audience']['interests'])}
- Pain points: {$this->formatArray($context['target_audience']['pain_points'])}

STYLE PREFERENCES:
- Creativity: {$creativityInstruction}
- Length: {$lengthInstruction}

RULES:
1. Write all content in {$language}
2. Match the brand's voice exactly
3. Create content that resonates with the target audience
4. Include a strong hook (first sentence that grabs attention)
5. Provide value to the reader
6. Include a call-to-action
7. DO NOT use Unicode bold/italic formatting - use plain text only

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text.
{
  "title": "Post title for internal reference",
  "main_caption": "The main content/caption for the post",
  "platforms": {
    "facebook": {
      "caption": "Facebook-optimized caption with engagement hooks"
    },
    "instagram": {
      "caption": "Instagram caption without hashtags",
      "hashtags": ["#hashtag1", "#hashtag2", "..."]
    },
    "youtube": {
      "title": "YouTube video title (max 100 chars)",
      "description": "Full YouTube description with timestamps if applicable"
    }
  },
  "image_keywords": ["keyword1", "keyword2", "keyword3"]
}
PROMPT;
    }

    /**
     * Get creativity instruction based on level.
     */
    protected function getCreativityInstruction(string $level): string
    {
        return match ($level) {
            'conservative' => 'Be straightforward and professional. Stick to proven formats and conventional approaches.',
            'balanced' => 'Balance creativity with reliability. Use creative elements while maintaining clarity.',
            'creative' => 'Be bold and innovative. Use unique angles, metaphors, and unexpected approaches to stand out.',
            default => 'Balance creativity with reliability.',
        };
    }

    /**
     * Get length instruction based on preference.
     */
    protected function getLengthInstruction(string $length): string
    {
        return match ($length) {
            'short' => 'Keep content concise and punchy. Aim for brevity - every word should count.',
            'medium' => 'Use moderate length. Provide enough detail while staying focused.',
            'long' => 'Create detailed, comprehensive content. Include thorough explanations and context.',
            default => 'Use moderate length.',
        };
    }

    /**
     * Build the user prompt with the specific content request.
     */
    protected function buildUserPrompt(array $config, array $context): string
    {
        $pillar = $config['pillar'] ?? 'General content';
        $topic = $config['topic'] ?? 'Create engaging social media content';
        $platforms = $config['platforms'] ?? $context['enabled_platforms'];
        $type = $config['type'] ?? 'text';

        $prompt = <<<PROMPT
Create content for the following:

CONTENT PILLAR: {$pillar}
TOPIC/HOOK: {$topic}
CONTENT TYPE: {$type}
PLATFORMS: {$this->formatArray($platforms)}

Please generate:
1. A compelling title for internal reference
2. Main caption that can be used across platforms
3. Platform-specific versions optimized for each platform
4. 3 keywords for finding relevant stock photos
PROMPT;

        // Add feedback if regenerating
        if (!empty($config['feedback'])) {
            $prompt .= "\n\nPREVIOUS FEEDBACK TO ADDRESS:\n{$config['feedback']}";
        }

        // Add custom instructions if provided
        if (!empty($config['custom_instructions'])) {
            $prompt .= "\n\nADDITIONAL INSTRUCTIONS:\n{$config['custom_instructions']}";
        }

        return $prompt;
    }

    /**
     * Post-process the generated content.
     */
    protected function postProcessContent(array $content): array
    {
        // Convert literal \n to actual newlines
        $convertNewlines = function ($value) use (&$convertNewlines) {
            if (is_string($value)) {
                return str_replace('\n', "\n", $value);
            }
            if (is_array($value)) {
                return array_map($convertNewlines, $value);
            }
            return $value;
        };

        return $convertNewlines($content);
    }

    /**
     * Get emoji usage guideline text.
     */
    protected function getEmojiGuideline(string $usage): string
    {
        return match ($usage) {
            'often' => 'Use emojis frequently throughout the content to make it engaging and visually appealing',
            'sometimes' => 'Use emojis occasionally for emphasis and to highlight key points',
            'rarely' => 'Use emojis sparingly, only when they add significant value',
            'never' => 'Do not use any emojis in the content',
            default => 'Use emojis occasionally for emphasis',
        };
    }

    /**
     * Format array for prompt.
     */
    protected function formatArray(array $items): string
    {
        if (empty($items)) {
            return 'Not specified';
        }
        return implode(', ', $items);
    }
}
