<?php

namespace App\Services\SmManager;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmAutoReplyRule;
use App\Models\SmComment;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI;

class SmAutoReplyService
{
    use LogsApiUsage;

    protected const MODEL = 'gpt-4o';

    /**
     * Generate a reply for a comment in brand voice.
     *
     * Checks auto-reply rules first for template matches, then falls back to AI generation.
     *
     * @return array{success: bool, reply?: string, requires_approval?: bool, matched_rule_id?: int|null, tone?: string, error?: string, error_code?: string}
     */
    public function generateReply(Brand $brand, SmComment $comment): array
    {
        $matchedRule = $this->checkRules($brand, $comment);

        if ($matchedRule) {
            $reply = $this->applyRuleTemplate($matchedRule, $comment);
            $matchedRule->incrementUsage();

            return [
                'success' => true,
                'reply' => $reply,
                'requires_approval' => $matchedRule->requires_approval,
                'matched_rule_id' => $matchedRule->id,
                'tone' => 'template',
            ];
        }

        return $this->generateAiReply($brand, $comment);
    }

    /**
     * Check if any auto-reply rules match this comment.
     *
     * Matching by: keyword trigger, sentiment trigger.
     */
    public function checkRules(Brand $brand, SmComment $comment): ?SmAutoReplyRule
    {
        $rules = SmAutoReplyRule::where('brand_id', $brand->id)
            ->active()
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $comment)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Check if a single rule matches the comment.
     */
    protected function ruleMatches(SmAutoReplyRule $rule, SmComment $comment): bool
    {
        return match ($rule->trigger_type) {
            'keyword' => $this->matchesKeyword($rule->trigger_value, $comment->text),
            'sentiment' => $this->matchesSentiment($rule->trigger_value, $comment->sentiment),
            'contains' => Str::contains(Str::lower($comment->text), Str::lower($rule->trigger_value)),
            default => false,
        };
    }

    /**
     * Check if comment text contains the keyword (case-insensitive).
     */
    protected function matchesKeyword(string $keyword, string $text): bool
    {
        $keywords = array_map('trim', explode(',', $keyword));

        foreach ($keywords as $kw) {
            if ($kw !== '' && Str::contains(Str::lower($text), Str::lower($kw))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if comment sentiment matches the trigger value.
     */
    protected function matchesSentiment(string $triggerSentiment, ?string $commentSentiment): bool
    {
        if (!$commentSentiment) {
            return false;
        }

        return Str::lower($triggerSentiment) === Str::lower($commentSentiment);
    }

    /**
     * Apply rule template with basic variable substitution.
     */
    protected function applyRuleTemplate(SmAutoReplyRule $rule, SmComment $comment): string
    {
        $template = $rule->response_template;

        $replacements = [
            '{author}' => $comment->author_name ?? $comment->author_handle ?? '',
            '{brand}' => $rule->brand->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate a reply using AI when no rule matches.
     *
     * @return array{success: bool, reply?: string, requires_approval?: bool, matched_rule_id?: null, tone?: string, error?: string, error_code?: string}
     */
    protected function generateAiReply(Brand $brand, SmComment $comment): array
    {
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $systemPrompt = $this->buildReplySystemPrompt($brand);
        $userPrompt = $this->buildReplyPrompt($brand, $comment);

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'sm_auto_reply_generate', [
            'comment_id' => $comment->id,
            'platform' => $comment->platform,
            'sentiment' => $comment->sentiment,
        ], self::MODEL);

        try {
            $client = OpenAI::client($apiKey);

            $response = retry(3, fn () => $client->chat()->create([
                'model' => self::MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]), 1000);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            $tone = $parsed['tone'] ?? 'friendly';
            $requiresApproval = $this->shouldRequireApproval($comment, $tone);

            return [
                'success' => true,
                'reply' => $parsed['reply'] ?? '',
                'requires_approval' => $requiresApproval,
                'matched_rule_id' => null,
                'tone' => $tone,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('SmAutoReply: generateAiReply failed', [
                'brand_id' => $brand->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the system prompt establishing brand voice for replies.
     */
    protected function buildReplySystemPrompt(Brand $brand): string
    {
        $brandName = $brand->name;
        $tone = $brand->getTone() ?? 'professional';
        $personality = implode(', ', $brand->getPersonality());
        $language = $brand->getLanguage();

        $prompt = <<<PROMPT
You are a social media community manager for the brand "{$brandName}".
Your task is to generate appropriate replies to social media comments.

BRAND VOICE:
- Tone: {$tone}
- Language: {$language}
PROMPT;

        if ($personality) {
            $prompt .= "\n- Personality: {$personality}";
        }

        if ($brand->description) {
            $prompt .= "\n- Brand description: {$brand->description}";
        }

        $prompt .= <<<PROMPT


REPLY RULES:
1. Always match the brand's tone and voice
2. Be helpful, empathetic, and authentic
3. For negative comments: acknowledge the concern, do NOT be defensive, offer to help
4. For positive comments: show genuine appreciation, encourage further engagement
5. For questions: provide a clear, helpful answer or direct them to the right resource
6. Keep replies concise and natural (1-3 sentences typically)
7. Do NOT use Unicode bold/italic formatting - use plain text only
8. Do NOT include hashtags in replies unless contextually appropriate
9. Write ALL reply text in {$language}

RESPONSE FORMAT:
Respond with valid JSON only. No additional text.
{
  "reply": "The reply text to post (in {$language})",
  "tone": "friendly|professional|empathetic|grateful|apologetic"
}
PROMPT;

        return $prompt;
    }

    /**
     * Build the user prompt with comment context.
     */
    protected function buildReplyPrompt(Brand $brand, SmComment $comment): string
    {
        $platform = $comment->platform ?? 'social media';
        $authorName = $comment->author_name ?? $comment->author_handle ?? 'User';
        $sentiment = $comment->sentiment ?? 'unknown';
        $commentText = $comment->text;

        return <<<PROMPT
Platform: {$platform}
Comment author: {$authorName}
Comment sentiment: {$sentiment}
Comment text:
"{$commentText}"

Generate an appropriate reply from the brand "{$brand->name}".
PROMPT;
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
            Log::warning('SmAutoReply: could not parse JSON response', [
                'raw' => mb_substr($content, 0, 300),
            ]);

            return [
                'reply' => $content,
                'tone' => 'friendly',
            ];
        }

        return $decoded;
    }

    /**
     * Determine if the reply should require manual approval before posting.
     *
     * Negative/crisis comments always require approval for safety.
     */
    protected function shouldRequireApproval(SmComment $comment, string $tone): bool
    {
        if ($comment->isNegative()) {
            return true;
        }

        if ($comment->is_flagged) {
            return true;
        }

        if (in_array($tone, ['empathetic', 'apologetic'])) {
            return true;
        }

        return false;
    }
}
