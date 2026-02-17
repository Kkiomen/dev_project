<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmBrandKit;
use App\Models\SmStrategy;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI;

class SmCopywriterService
{
    use LogsApiUsage;

    /**
     * Generate a social media post for a given platform and topic.
     *
     * @param Brand $brand
     * @param array $config Keys: platform, content_type, topic, pillar, tone (optional)
     * @return array{success: bool, text?: string, hashtags?: array, cta?: string, hook?: string, error?: string, error_code?: string}
     */
    public function generatePost(Brand $brand, array $config): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildSystemPrompt($brand);
        $userPrompt = $this->buildPostPrompt($config);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_copywriter_generate_post', [
            'platform' => $config['platform'] ?? null,
            'content_type' => $config['content_type'] ?? null,
            'topic' => $config['topic'] ?? null,
            'pillar' => $config['pillar'] ?? null,
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 2048,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);
            $parsed = $this->sanitizeOutput($parsed, $brand);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'text' => $parsed['text'] ?? '',
                'hashtags' => $parsed['hashtags'] ?? [],
                'cta' => $parsed['cta'] ?? '',
                'hook' => $parsed['hook'] ?? '',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmCopywriter: generatePost failed', [
                'brand_id' => $brand->id,
                'platform' => $config['platform'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate A/B text variants from an original post.
     *
     * @param Brand $brand
     * @param string $originalText
     * @param string $platform
     * @param int $count
     * @return array{success: bool, variants?: array, error?: string, error_code?: string}
     */
    public function generateVariants(Brand $brand, string $originalText, string $platform, int $count = 3): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $language = $this->getLanguageName($brand->getLanguage());
        $tone = $brand->getTone() ?? 'professional';

        $systemPrompt = <<<PROMPT
You are an expert social media A/B testing copywriter.
Your task is to create {$count} distinct variants of a given social media post.
Each variant should maintain the core message but vary in: hook style, CTA approach, tone nuance, or structure.
Write all content in {$language}.
Brand voice tone: {$tone}.

RESPONSE FORMAT:
Respond with valid JSON only.
{
  "variants": [
    {
      "text": "Full variant text including hook, body and CTA",
      "hashtags": ["#tag1", "#tag2"],
      "variation_note": "Brief note on what was changed"
    }
  ]
}
PROMPT;

        $userPrompt = <<<PROMPT
Platform: {$platform}
Number of variants: {$count}

Original post:
"{$originalText}"

Create {$count} distinct A/B variants of this post. Each variant should test a different approach (e.g., question hook vs. bold statement, soft CTA vs. direct CTA, storytelling vs. data-driven).
PROMPT;

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_copywriter_generate_variants', [
            'platform' => $platform,
            'original_text_length' => mb_strlen($originalText),
            'count' => $count,
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 3072,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            $variants = $parsed['variants'] ?? [];

            return [
                'success' => true,
                'variants' => array_map(fn (array $v) => [
                    'text' => $v['text'] ?? '',
                    'hashtags' => $v['hashtags'] ?? [],
                    'variation_note' => $v['variation_note'] ?? '',
                ], $variants),
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmCopywriter: generateVariants failed', [
                'brand_id' => $brand->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt including brand voice, audience, and content structure instructions.
     */
    protected function buildSystemPrompt(Brand $brand): string
    {
        $language = $this->getLanguageName($brand->getLanguage());
        $tone = $brand->getTone() ?? 'professional';
        $personality = implode(', ', $brand->getPersonality());
        $emojiUsage = $brand->getEmojiUsage();
        $context = $brand->buildAiContext();
        $strategyContext = $this->loadStrategyContext($brand);

        $prompt = "You are a senior social media copywriter at a top marketing agency, managing the brand \"{$brand->name}\".";
        $prompt .= "\nYou write like a human creator who has real experience, strong opinions, and something specific to say.";
        $prompt .= "\nYou NEVER sound like AI, a press release, or a corporate brochure.";
        $prompt .= "\n\nOUTPUT LANGUAGE: {$language}. Write ALL post content (text, hook, CTA, hashtags) in {$language}. The prompt instructions are in English for efficiency, but your output MUST be in {$language}.";
        $prompt .= "\n\n### ABSOLUTE BAN - output will be automatically rejected if it contains any of these (in ANY language including {$language}):";
        $prompt .= "\n# \"Did you know\" / \"Do you know\" - BANNED. Use a bold statement instead.";
        $prompt .= "\n# \"Imagine\" / \"Picture this\" / \"Envision\" - BANNED. This includes ALL translations (e.g. Polish: \"Wyobraź sobie\", German: \"Stell dir vor\", Spanish: \"Imagina\"). ZERO tolerance.";
        $prompt .= "\n# Generic CTA verbs as standalone CTAs: \"Check it out\", \"Find out more\", \"See for yourself\", \"Start now\", \"Start today\", \"Visit\", \"Discover\" - BANNED.";
        $prompt .= "\n# 🚀 emoji - BANNED. The \"!\" character is COMPLETELY FORBIDDEN - use \".\" or \"?\" instead. Every single \"!\" will be automatically stripped.";
        $prompt .= "\n# \"STOP\" / \"STOP ✋\" / \"Stop scrolling\" as hooks - BANNED.";
        $prompt .= "\n# **bold** or markdown formatting - BANNED. Plain text only.";
        $prompt .= "\n# \"Link in bio\" - ONLY on Instagram/TikTok. NEVER on LinkedIn/X/Facebook.";

        // Add high-frequency banned phrases in the target language for non-English outputs
        $localBans = $this->getLocalizedBannedPhrases($brand->getLanguage());
        if ($localBans) {
            $prompt .= "\n# In {$language} specifically, these are BANNED: {$localBans}";
        }

        // Brand DNA
        $prompt .= "\n\n=== BRAND DNA ===";
        if ($brand->description) {
            $prompt .= "\nDescription: {$brand->description}";
        }
        if ($brand->industry) {
            $prompt .= "\nIndustry: {$brand->industry}";
        }

        // Brand Kit context (tone_of_voice, voice_attributes, guidelines)
        if ($strategyContext['brand_kit']) {
            $kit = $strategyContext['brand_kit'];
            if (!empty($kit['tone_of_voice'])) {
                $prompt .= "\nBrand tone of voice: {$kit['tone_of_voice']}";
            }
            if (!empty($kit['voice_attributes'])) {
                $prompt .= "\nVoice attributes: " . implode(', ', $kit['voice_attributes']);
            }
            if (!empty($kit['brand_guidelines_notes'])) {
                $prompt .= "\nBrand guidelines: {$kit['brand_guidelines_notes']}";
            }
        }

        // Brand voice from model
        $prompt .= "\n\n=== BRAND VOICE ===";
        $prompt .= "\n- Tone: {$tone}";
        if ($personality) {
            $prompt .= "\n- Personality traits: {$personality}";
        }
        $prompt .= "\n- Emoji usage: {$emojiUsage}";

        // Strategy goals
        if (!empty($strategyContext['goals'])) {
            $prompt .= "\n\n=== STRATEGY GOALS ===";
            foreach ($strategyContext['goals'] as $goal) {
                $prompt .= "\n- " . (is_string($goal) ? $goal : json_encode($goal));
            }
        }

        // Target audience - deep version from strategy, fallback to brand
        $prompt .= "\n\n=== TARGET AUDIENCE ===";
        if (!empty($strategyContext['target_audience'])) {
            $audience = $strategyContext['target_audience'];
            foreach ($audience as $key => $value) {
                if (is_array($value)) {
                    $prompt .= "\n- " . str_replace('_', ' ', ucfirst($key)) . ": " . implode(', ', $value);
                } elseif ($value) {
                    $prompt .= "\n- " . str_replace('_', ' ', ucfirst($key)) . ": {$value}";
                }
            }
        } else {
            $prompt .= "\n- Age range: " . ($context['target_audience']['age_range'] ?? 'Not specified');
            $prompt .= "\n- Gender: " . ($context['target_audience']['gender'] ?? 'all');
            if (!empty($context['target_audience']['interests'])) {
                $prompt .= "\n- Interests: " . implode(', ', $context['target_audience']['interests']);
            }
            if (!empty($context['target_audience']['pain_points'])) {
                $prompt .= "\n- Pain points: " . implode(', ', $context['target_audience']['pain_points']);
            }
        }

        // Content pillars
        $pillars = array_column($brand->content_pillars ?? [], 'name');
        if (!empty($pillars)) {
            $prompt .= "\n\nCONTENT PILLARS: " . implode(', ', $pillars);
        }

        // Content mix from strategy
        if (!empty($strategyContext['content_mix'])) {
            $mixParts = [];
            foreach ($strategyContext['content_mix'] as $type => $pct) {
                $mixParts[] = "{$type}: {$pct}%";
            }
            $prompt .= "\nCONTENT MIX: " . implode(', ', $mixParts);
        }

        // AI recommendations from strategy
        if (!empty($strategyContext['ai_recommendations'])) {
            $prompt .= "\n\nSTRATEGY NOTES: {$strategyContext['ai_recommendations']}";
        }

        $prompt .= <<<PROMPT


=== VOICE & PERSPECTIVE ===
- Write in FIRST PERSON ("I", "my") as the brand's voice - as if the brand founder or team member is speaking from personal experience. NEVER write in third person about fictional characters ("Karol, owner of a small company..."). That is an obvious AI pattern.
- If telling a story, use: "I used to...", "One of our users...", "A client told me..." - never invent named characters.
- Be opinionated and specific. Real copywriters take stances. Say "This is wrong" not "This might be worth considering."

=== COPYWRITING FRAMEWORKS ===
Choose ONE per post (do NOT label or announce it):
- PAS: Problem > Agitate > Solve
- AIDA: Attention > Interest > Desire > Action
- Before/After/Bridge: Old way > New way > How to get there
- Pattern interrupt: Open with something unexpected
- Storytelling: Mini-narrative with tension and resolution
- Hot take: Controversial opinion + reasoning
- Data drop: Lead with a striking number, then context

=== WRITING STRUCTURE ===
1. HOOK - First line. Must create curiosity, tension, or surprise. This determines if anyone reads the rest. Make it bold, specific, provocative. Never generic.
2. BODY - Deliver value. Short paragraphs. One idea per paragraph. Build tension before revealing the solution.
3. BRAND MENTION - Do NOT mention the brand name in the first 60% of the post. Lead with pure value/story/insight. The brand should appear naturally near the end, as the solution - not as a pitch.
4. CTA - Specific to the topic. Must feel like a natural next step, not a sales push.

=== RULES ===
1. Write ALL output in {$language}
2. Match the brand voice exactly
3. Platform-aware copy (character limits, formatting)
4. Plain text only - no markdown, no Unicode bold/italic
5. Deliver concrete value - every post must teach, inspire, or provoke thought
6. Be specific - numbers, examples, scenarios. Never vague claims.
7. VARY structure across posts - alternate between: storytelling, data-driven, hot take, behind-the-scenes, how-to, question-led. Never use the same structure twice in a row.
8. Every word must earn its place. No filler, no fluff.
9. NEVER fabricate statistics. Use numbers only from the brief, or frame as personal experience ("I saved X hours", "Our users report Y").
10. Use emojis SPARINGLY and STRATEGICALLY - max 2-4 per post, placed for visual rhythm not decoration. Never start paragraphs with emojis as bullet points.

=== RESPONSE FORMAT ===
Valid JSON only. No additional text, no markdown code blocks.
All text values MUST be in {$language}.
{
  "text": "Full post text (hook + body + CTA, with line breaks as \\n)",
  "hook": "Just the hook/first line extracted",
  "cta": "Just the CTA extracted",
  "hashtags": ["#relevant", "#hashtags"]
}

##############################
# HARD CONSTRAINTS - VIOLATIONS = REJECTED OUTPUT
# Apply in ANY language including {$language} translations.
##############################

BANNED PHRASES (even ONE = rejected):
"Imagine", "Did you know", "In today's world", "In today's digital age", "In the ever-evolving", "Let's dive in", "Let's explore", "Game-changer", "Unlock your potential", "Take it to the next level", "Don't miss out", "Here's the thing", "It's no secret", "Elevate", "Supercharge", "Revolutionize", "At the end of the day", "STOP SCROLLING", "Stop scrolling", "Wait for it", "Are you ready to", "Ready for a change", "Want to know more"
These bans apply to their {$language} translations too.

BANNED CTAs: "Check it out", "Find out more", "See for yourself", "Start now", "Start today", "Visit our", "Discover", "Learn more" and their {$language} equivalents - BANNED as standalone CTAs.
Good CTAs: "Drop a comment with how many tools you pay for", "Save this for your next planning session", "Link in bio - 14-day free trial" (link in bio = Instagram/TikTok only)

BANNED PATTERNS:
- 🚀 emoji - never
- Exclamation marks - ZERO allowed. Periods and questions only.
- Do NOT start sentences with "So," / "Well," / their {$language} equivalents
- "you"/"your" max 2x per post
- Do NOT use lists of exactly 3 or 5 items
- Do NOT invent named fictional characters (e.g., "Meet Sarah, a freelancer who...")
- Do NOT place emojis at the start of every paragraph as bullets

EXAMPLES OF GOOD HOOKS (adapt to {$language}, do not copy):
- "I dropped 6 tools last month. Revenue went up."
- "Yesterday I published 12 posts across 4 platforms. Took me 3 minutes."
- "Every solopreneur makes this same mistake with their stack."
- "I wasted 2 years doing this manually. Here is what changed."
- "Unpopular opinion: most automation tools create more work, not less."
- "The math is simple: 10 hours/week x 50 PLN/hour = 2000/month burned."

SELF-CHECK: Before outputting, verify: (1) ZERO banned phrases in any language, (2) ZERO 🚀, (3) ZERO exclamation marks, (4) brand name NOT in first 60% of text, (5) no fictional named characters, (6) CTA is topic-specific, (7) ALL text in {$language}. Rewrite if any violation.
PROMPT;

        return $prompt;
    }

    /**
     * Load strategy and brand kit context for the given brand.
     *
     * @return array{goals: array, target_audience: array, content_mix: array, ai_recommendations: string|null, brand_kit: array|null}
     */
    protected function loadStrategyContext(Brand $brand): array
    {
        $strategy = SmStrategy::where('brand_id', $brand->id)
            ->active()
            ->latest()
            ->first();

        $brandKit = SmBrandKit::where('brand_id', $brand->id)->first();

        return [
            'goals' => $strategy?->goals ?? [],
            'target_audience' => $strategy?->target_audience ?? [],
            'content_mix' => $strategy?->content_mix ?? [],
            'ai_recommendations' => $strategy?->ai_recommendations,
            'brand_kit' => $brandKit ? [
                'tone_of_voice' => $brandKit->tone_of_voice,
                'voice_attributes' => $brandKit->voice_attributes ?? [],
                'brand_guidelines_notes' => $brandKit->brand_guidelines_notes,
                'hashtag_groups' => $brandKit->hashtag_groups ?? [],
            ] : null,
        ];
    }

    /**
     * Build the user prompt with platform, content type, topic, and pillar details.
     */
    protected function buildPostPrompt(array $config): string
    {
        $platform = $config['platform'] ?? 'instagram';
        $contentType = $config['content_type'] ?? 'post';
        $topic = $config['topic'] ?? '';
        $pillar = $config['pillar'] ?? '';
        $tone = $config['tone'] ?? '';
        $description = $config['description'] ?? '';

        $prompt = "Create a {$contentType} for {$platform}.";
        $prompt .= "\n\nTOPIC: {$topic}";

        if ($description) {
            $prompt .= "\n\nDETAILED BRIEF:\n{$description}";
        }

        if ($pillar) {
            $prompt .= "\n\nContent pillar: {$pillar}";
        }

        // Tone direction based on content type
        $toneDirection = $this->getContentTypeToneDirection($contentType);
        if ($toneDirection) {
            $prompt .= "\n\nTone direction for this content type: {$toneDirection}";
        }

        if ($tone) {
            $prompt .= "\nTone override: {$tone}";
        }

        if (!empty($config['instructions'])) {
            $prompt .= "\n\nAdditional instructions: {$config['instructions']}";
        }

        $constraints = $this->getPlatformConstraints($platform);
        $prompt .= "\n\n=== PLATFORM GUIDELINES ({$platform}) ===";
        $prompt .= "\n- Max characters: {$constraints['max_chars']}";
        $prompt .= "\n- Max hashtags: {$constraints['max_hashtags']}";
        $prompt .= "\n- Style: {$constraints['style']}";
        $prompt .= "\n- Structure: {$constraints['structure']}";
        $prompt .= "\n- Formatting: {$constraints['formatting']}";
        $prompt .= "\n- Best practices: {$constraints['best_practices']}";

        return $prompt;
    }

    /**
     * Get tone direction based on content type.
     */
    protected function getContentTypeToneDirection(string $contentType): ?string
    {
        return match ($contentType) {
            'carousel' => 'Educational, structured, value-packed. Each slide should teach one clear point.',
            'reel', 'video' => 'Energetic, casual, fast-paced. Hook within the first 3 seconds. Conversational language.',
            'story' => 'Behind-the-scenes, personal, authentic. Create intimacy and FOMO.',
            'article', 'blog' => 'Thought leadership, in-depth, authoritative. Show expertise with data and insights.',
            'poll', 'question' => 'Engaging, curiosity-driven, community-building. Make people want to participate.',
            'quote' => 'Inspirational but authentic. Avoid cliche motivational poster language.',
            'infographic' => 'Data-driven, clear, scannable. Lead with the most surprising stat.',
            default => null,
        };
    }

    /**
     * Parse AI response, handling markdown code blocks and validating required fields.
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
            Log::warning('SmCopywriter: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 300),
            ]);

            return [
                'text' => $content,
                'hook' => '',
                'cta' => '',
                'hashtags' => [],
            ];
        }

        $convertNewlines = fn ($text) => is_string($text) ? str_replace('\n', "\n", $text) : ($text ?? '');

        return [
            'text' => $convertNewlines($decoded['text'] ?? ''),
            'hook' => $convertNewlines($decoded['hook'] ?? ''),
            'cta' => $convertNewlines($decoded['cta'] ?? ''),
            'hashtags' => $decoded['hashtags'] ?? [],
            'variants' => $decoded['variants'] ?? [],
        ];
    }

    /**
     * Sanitize AI output: strip markdown formatting and log banned phrase violations.
     */
    protected function sanitizeOutput(array $parsed, Brand $brand): array
    {
        $textFields = ['text', 'hook', 'cta'];

        foreach ($textFields as $field) {
            if (!empty($parsed[$field]) && is_string($parsed[$field])) {
                // Strip markdown bold/italic
                $parsed[$field] = preg_replace('/\*\*(.+?)\*\*/', '$1', $parsed[$field]);
                $parsed[$field] = preg_replace('/\*(.+?)\*/', '$1', $parsed[$field]);
                $parsed[$field] = preg_replace('/__(.+?)__/', '$1', $parsed[$field]);

                // Replace 🚀 with empty string
                $parsed[$field] = str_replace('🚀', '', $parsed[$field]);

                // Replace exclamation marks with periods
                $parsed[$field] = str_replace('!', '.', $parsed[$field]);

                // Clean up double spaces left by removals
                $parsed[$field] = preg_replace('/  +/', ' ', $parsed[$field]);
                $parsed[$field] = trim($parsed[$field]);
            }
        }

        // Log banned phrase violations for monitoring (check common patterns in multiple languages)
        $bannedPatterns = [
            'Did you know', 'Czy wiesz', 'Wusstest du',
            'Imagine', 'Wyobraź sobie', 'Stell dir vor',
            'In today\'s world', 'W dzisiejszym świecie',
            'STOP SCROLLING', 'STOP ✋',
            'Game-changer', 'Game changer',
        ];
        $text = $parsed['text'] ?? '';
        $violations = [];

        foreach ($bannedPatterns as $pattern) {
            if (mb_stripos($text, $pattern) !== false) {
                $violations[] = $pattern;
            }
        }

        if (!empty($violations)) {
            Log::warning('SmCopywriter: banned phrases detected in output', [
                'brand_id' => $brand->id,
                'violations' => $violations,
            ]);
        }

        return $parsed;
    }

    /**
     * Get platform-specific professional guidelines.
     */
    protected function getPlatformConstraints(string $platform): array
    {
        return match ($platform) {
            'instagram' => [
                'max_chars' => 2200,
                'max_hashtags' => 30,
                'style' => 'Visual storytelling, authentic, emoji-friendly',
                'structure' => 'Hook (1 compelling line) → empty line → Body (2-5 short paragraphs, use emojis as bullet points if brand allows) → empty line → CTA → empty line → Hashtags block',
                'formatting' => 'Use line breaks generously. One thought per line. Short paragraphs (1-2 sentences). Empty line between sections.',
                'best_practices' => 'First line is everything - it shows before "more". Carousel posts: write slide-by-slide with numbered points. Reels: short caption, hook-first. 8-15 hashtags is optimal.',
            ],
            'linkedin' => [
                'max_chars' => 3000,
                'max_hashtags' => 5,
                'style' => 'Professional thought leadership, data-driven, insightful',
                'structure' => 'Hook line → line break → Storytelling or data-backed insight (1 sentence per line for readability) → Professional CTA',
                'formatting' => 'One sentence per line for scanability. No emojis in the first line. Use sparingly if at all. Short paragraphs.',
                'best_practices' => 'LinkedIn rewards long-form content that generates comments. Ask questions. Share lessons learned. Use "I" not "we" for personal brands. Contrarian takes perform well.',
            ],
            'x' => [
                'max_chars' => 280,
                'max_hashtags' => 3,
                'style' => 'Sharp, concise, high-impact, conversational',
                'structure' => 'Single punchy statement OR hook + value. Every character counts.',
                'formatting' => 'No line breaks unless for emphasis. Hashtags count toward character limit. Keep to 1-2 hashtags max in practice.',
                'best_practices' => 'Contrarian takes and conversation starters get engagement. Questions perform well. Threads: first tweet must stand alone as a hook. Avoid thread-bait.',
            ],
            'tiktok' => [
                'max_chars' => 2200,
                'max_hashtags' => 10,
                'style' => 'Casual, trendy, authentic, community-driven. Write like you are talking to a friend, not presenting a pitch.',
                'structure' => 'Hook in first 3 words → 2-3 short casual sentences → question or challenge as CTA. Keep it SHORT - max 4-6 sentences total.',
                'formatting' => 'Short sentences. No bullet lists, no numbered lists. Write as flowing casual text. No formal structure.',
                'best_practices' => 'Write like a creator, not a brand. Use first person ("I") not "we". Sound like spoken language, not written. Use slang/casual grammar if natural. Good TikTok hook patterns: "Nobody talks about this but...", "POV: you automated your entire marketing", "This one change saves me 2h a day".',
            ],
            'facebook' => [
                'max_chars' => 63206,
                'max_hashtags' => 5,
                'style' => 'Conversational, community-building, warm',
                'structure' => 'Question or relatable statement → Story or value → Community-focused CTA (comment, share, tag)',
                'formatting' => 'Medium-length paragraphs. Conversational tone. Emojis OK but not overdone.',
                'best_practices' => 'Questions drive comments. "Tag someone who..." drives shares. Personal stories outperform promotional content. Facebook rewards engagement - write for comments.',
            ],
            'youtube' => [
                'max_chars' => 5000,
                'max_hashtags' => 15,
                'style' => 'SEO-optimized, descriptive, structured',
                'structure' => 'SEO-rich opening paragraph (include keywords) → Video summary → Timestamps section → Links/resources → Hashtags',
                'formatting' => 'Use line breaks between sections. Timestamps format: 0:00 - Topic. Clear sections with headers.',
                'best_practices' => 'First 2-3 lines show in search results - front-load keywords. First 3 hashtags appear above the title. Include clear subscribe CTA.',
            ],
            default => [
                'max_chars' => 2000,
                'max_hashtags' => 10,
                'style' => 'Engaging, clear, value-driven',
                'structure' => 'Hook → Body → CTA',
                'formatting' => 'Use line breaks between sections. Keep paragraphs short.',
                'best_practices' => 'Lead with value. Be specific. End with a clear action.',
            ],
        };
    }

    /**
     * Get banned phrases translated to the target language for explicit enforcement.
     * Returns null for English (already covered by the main ban list).
     */
    protected function getLocalizedBannedPhrases(string $langCode): ?string
    {
        return match ($langCode) {
            'pl' => '"Czy wiesz", "Wyobraź sobie", "W dzisiejszym świecie", "Dowiedz się", "Sprawdź", "Przekonaj się", "Zajrzyj", "Poznaj", "Zacznij już", "Nie przegap", "Gotowy na zmianę"',
            'de' => '"Wusstest du", "Stell dir vor", "In der heutigen Welt", "Erfahre mehr", "Schau dir an", "Entdecke", "Starte jetzt", "Verpasse nicht"',
            'es' => '"Sabías que", "Imagina", "En el mundo actual", "Descubre", "No te pierdas", "Empieza ahora", "Echa un vistazo"',
            'fr' => '"Saviez-vous", "Imaginez", "Dans le monde d\'aujourd\'hui", "Découvrez", "Ne manquez pas", "Commencez maintenant"',
            default => null,
        };
    }

    /**
     * Get full language name from ISO code.
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
        ];

        return $languages[$code] ?? 'English';
    }
}
