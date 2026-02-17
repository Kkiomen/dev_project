<?php

namespace App\Services\AI;

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\SmBrandKit;
use App\Models\SmStrategy;
use App\Models\SocialPost;
use App\Services\Concerns\LogsApiUsage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class DirectTextGeneratorService
{
    use LogsApiUsage;

    /**
     * Generate text for a post via OpenAI when no webhook is configured.
     */
    public function generate(SocialPost $post, ?string $promptOverride = null): array
    {
        $brand = $post->brand;

        if (!$brand) {
            return ['success' => false, 'error' => 'No brand associated with post'];
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $userPrompt = $promptOverride ?? $post->text_prompt;

        if (empty($userPrompt)) {
            return ['success' => false, 'error' => 'No text prompt provided for this post'];
        }

        $systemPrompt = $this->resolveSystemPrompt($brand);
        $fullUserPrompt = $userPrompt . "\n\nRespond with JSON: {\"caption\": \"...\", \"title\": \"...\"}";

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'direct_text_generation', [
            'post_id' => $post->public_id,
            'user_prompt' => $userPrompt,
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $fullUserPrompt],
                ],
                'max_tokens' => 2048,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $parsed = $this->parseResponse($content);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, $parsed, $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'caption' => $parsed['caption'] ?? '',
                'title' => $parsed['title'] ?? '',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('Direct text generation failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate an image description (image_prompt) from post content via OpenAI.
     */
    public function generateImageDescription(SocialPost $post, ?string $contentType = null): array
    {
        $brand = $post->brand;

        if (!$brand) {
            return ['success' => false, 'error' => 'No brand associated with post'];
        }

        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi);

        if (!$apiKey) {
            return ['success' => false, 'error_code' => 'no_api_key', 'error' => 'No OpenAI API key configured for this brand'];
        }

        $caption = $post->main_caption ?: $post->text_prompt;

        if (empty($caption)) {
            return ['success' => false, 'error' => 'No caption or text prompt available for this post'];
        }

        $visualContext = $this->loadVisualContext($brand);
        $brandDirective = $this->buildBrandVisualDirective($visualContext);
        $photographyDirection = $this->getPhotographyDirection($contentType ?? 'post', $brand->industry);

        $systemPrompt = "You are a commercial photographer who shoots campaigns for brands like Apple, Aesop, and Nike. You write prompts for AI image generation that produce photographs indistinguishable from real agency photoshoots.";
        $systemPrompt .= "\n\nBrand: \"{$brand->name}\"";
        if ($brand->description) {
            $systemPrompt .= " - {$brand->description}";
        }
        if ($brand->industry) {
            $systemPrompt .= "\nIndustry: {$brand->industry}";
        }
        if ($brandDirective) {
            $systemPrompt .= "\n\nBrand color direction:\n{$brandDirective}";
        }
        $systemPrompt .= "\n\nCamera/lighting reference:\n{$photographyDirection}";

        $systemPrompt .= <<<'PROMPT'


You write a prompt for the Nano Banana AI image model. The output is a single photograph used as a social media post image.

WHAT YOU CREATE:
A real photograph. Not a "graphic design", not a "template", not a "social media layout". A photograph - like one taken by a professional photographer on a real set with real lighting. The kind of image you'd see on Apple.com or in a Kinfolk magazine spread.

HOW TO WRITE THE DESCRIPTION (4-6 sentences max):
Sentence 1 - THE SCENE: Describe what the camera sees. Be specific and cinematic. Not "a workspace" but "a woman's hand resting on an open hardcover book on a sunlit oak table, a single dried eucalyptus sprig beside it".
Sentence 2 - CAMERA + LIGHT: Always include a real camera model and lens for texture. Describe the light source and its quality. Example: "Shot on Hasselblad X2D, 80mm f/1.9. Soft morning light from a large north-facing window, creating long gentle shadows."
Sentence 3 - DEPTH + FOCUS: Describe what's sharp and what's blurred. "Shallow depth of field, tack-sharp focus on the hands, background dissolving into warm creamy bokeh."
Sentence 4 - COLOR + MOOD: Name 2-3 actual colors you see and the emotional feeling. "Muted terracotta, warm ivory, and sage green. The mood is quiet confidence."

WHAT MAKES A GREAT SUBJECT:
- Something a real photographer would actually shoot for a brand campaign
- ONE clear subject with ONE point of focus
- Human elements add warmth: hands, back of someone walking, a profile in window light
- Objects that tell a story: a half-finished coffee, a worn leather journal, a key in a lock
- Environments that breathe: a sunlit corridor, a rainy window, morning mist on a field

HARD BAN - NEVER INCLUDE THESE IN THE SCENE (they always look fake in AI-generated images):
- ANY electronics: laptop, computer, monitor, phone, tablet, smartwatch, keyboard, mouse, headphones, screen of any kind
- ANY workspace/office elements: desk setup, office chair, standing desk, workspace, home office
- ANY UI/digital: dashboards, graphs, code, websites, apps, notifications
- ANY graphic design terms: "social media graphic", "template", "layout", "blob shape", "accent circle"
If the post is about technology/productivity/business - DO NOT show technology. Show the HUMAN SIDE: the person freed from their desk, the calm morning, the hands doing something creative, the view from the window.

ALSO AVOID:
- Multiple objects arranged "perfectly" - real photos have natural imperfection
- Abstract nothing (just bokeh, just gradients, just light) - needs a tangible subject
- Oversaturated colors, neon, HDR look - keep it natural and muted
- Cold blue/gray corporate tones - use warm, inviting tones instead
- Literal interpretations of the caption text - capture the FEELING, not the words

TRIGGER WORD SAFETY (WaveSpeed rejects these):
Never use: luxury, opulent, seductive, sensual, provocative, exposed, bare, naked, silk, satin, lingerie

TECHNICAL CUES THAT IMPROVE NANO BANANA OUTPUT:
- "4k RAW photograph" (not "photo", not "image")
- "Bokeh" (always)
- Real camera + lens names (Hasselblad X2D, Sony A7R IV, Leica M11, Canon R5, Fujifilm GFX)
- Specific f-stop (f/1.4, f/1.8, f/2.8)
- One texture detail ("visible wood grain", "condensation on glass", "knit texture of a wool sweater")

PROMPT;
        $systemPrompt .= "\nWrite the prompt in English. Respond with valid JSON: {\"image_prompt\": \"...\"}";

        $fullUserPrompt = "Post caption:\n\"{$caption}\"\n\nContent type: {$contentType}\n\nWrite a photograph description (4-6 sentences). CRITICAL RULE: absolutely NO laptops, NO computers, NO phones, NO screens, NO office/desk scenes. Even if the caption is about tech or productivity - show the human emotion instead: a person, nature, hands, light, architecture. Warm tones. JSON only.";

        $startTime = microtime(true);
        $log = $this->logAiStart($brand, 'direct_image_description', [
            'post_id' => $post->public_id,
            'content_type' => $contentType,
            'caption' => mb_substr($caption, 0, 200),
        ], 'gpt-4o');

        try {
            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $fullUserPrompt],
                ],
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = trim($response->choices[0]->message->content);

            // Strip markdown code blocks if present
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $content = $matches[1];
            }

            $decoded = json_decode($content, true);
            $imagePrompt = '';

            if (json_last_error() === JSON_ERROR_NONE) {
                $imagePrompt = $decoded['image_prompt'] ?? '';
            } else {
                $imagePrompt = $content;
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;

            $this->completeAiLog($log, ['image_prompt' => $imagePrompt], $promptTokens, $completionTokens, $durationMs);

            return [
                'success' => true,
                'image_prompt' => $imagePrompt,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->failLog($log, $e->getMessage(), $durationMs);

            Log::error('Direct image description generation failed', [
                'post_id' => $post->public_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Load visual context from SmBrandKit and SmStrategy for the brand.
     */
    public function loadVisualContext(Brand $brand): array
    {
        $brandKit = SmBrandKit::where('brand_id', $brand->id)->first();
        $strategy = SmStrategy::where('brand_id', $brand->id)
            ->active()
            ->latest()
            ->first();

        return [
            'colors' => $brandKit?->colors ?? [],
            'fonts' => $brandKit?->fonts ?? [],
            'style_preset' => $brandKit?->style_preset,
            'target_audience' => $strategy?->target_audience ?? [],
            'goals' => $strategy?->goals ?? [],
        ];
    }

    /**
     * Map content_type to a specific visual layout description.
     */
    public function getVisualLayoutForContentType(string $contentType): string
    {
        return match ($contentType) {
            'carousel' => 'Multi-panel design with numbered page indicator. Use a structured grid layout with geometric photo crops (rounded rectangles or circles). Each panel should feel like a chapter - consistent visual rhythm with alternating emphasis. Clean dividers between sections.',
            'story', 'reel' => 'Full-bleed vertical composition (9:16 feel adapted to square). Typography-dominant with large, impactful text overlay. Cinematic letterboxing or gradient overlays on the photograph. The photo should fill at least 70% of the frame with a dramatic crop.',
            'quote' => 'Centered symmetrical editorial layout. Large decorative quotation marks as graphic elements. The photograph is secondary - used as a small circular inset or subtle background wash at 15-20% opacity. Magazine-editorial feel with generous margins and a single accent line.',
            'educational', 'infographic' => 'Structured information design with clear visual sections. Use numbered markers, icon-style illustrations, or visual metaphors. Grid-based layout with the photograph occupying one quadrant. Clean hierarchy with a bold header area and supporting detail zones.',
            'behind-the-scenes' => 'Authentic, candid composition - the photograph dominates at 70%+ of the canvas. Documentary-style framing with natural imperfections (slight grain, available light). Minimal design overlays - just a thin brand-colored border or corner accent. Raw, real, unpolished feel.',
            'promotional' => 'Product/service hero shot with studio-quality lighting. The photograph is center-stage with brand-colored frames or geometric accent shapes. Strong visual hierarchy leading the eye to the subject. Premium packaging/unboxing aesthetic with shadow play.',
            default => $this->getRandomPostLayout(),
        };
    }

    /**
     * Pick a random layout variant for generic "post" content type.
     */
    protected function getRandomPostLayout(): string
    {
        $variants = [
            'Split layout - photograph occupies the left 55% with a soft rounded edge, right side is clean negative space with a small decorative label and one accent shape. Asymmetric balance with the photo as the anchor.',
            'Full-bleed photograph with a translucent gradient overlay (bottom 30%) fading from the brand accent color. The photo stretches edge-to-edge with a subtle vignette. One small text label floats in the lower third.',
            'Gradient background flowing from brand primary to a lighter tint. A floating photograph with soft drop shadow sits slightly off-center. Thin geometric line accents (circles, arcs) in the secondary color frame the composition.',
            'Mosaic/collage-inspired layout - one large dominant photograph (60%) paired with two color-block rectangles in brand palette tones. The blocks create a balanced L-shaped frame around the photo. One block holds a 1-word label.',
        ];

        return $variants[array_rand($variants)];
    }

    /**
     * Convert SmBrandKit data into visual prompt directives.
     */
    public function buildBrandVisualDirective(array $visualContext): string
    {
        $parts = [];

        $colors = $visualContext['colors'] ?? [];
        if (!empty($colors)) {
            $colorDirectives = [];
            if (!empty($colors['primary'])) {
                $colorDirectives[] = "Primary: {$colors['primary']} - use for dominant graphic elements, frames, and key accents";
            }
            if (!empty($colors['secondary'])) {
                $colorDirectives[] = "Secondary: {$colors['secondary']} - use for supporting shapes, backgrounds, and subtle tints";
            }
            if (!empty($colors['accent'])) {
                $colorDirectives[] = "Accent: {$colors['accent']} - use sparingly for small decorative details and highlights";
            }
            if (!empty($colorDirectives)) {
                $parts[] = "COLOR PALETTE:\n" . implode("\n", $colorDirectives);
            }
        }

        $fonts = $visualContext['fonts'] ?? [];
        if (!empty($fonts)) {
            $fontParts = [];
            if (!empty($fonts['heading'])) {
                $weight = $fonts['heading']['weight'] ?? 'bold';
                $fontParts[] = $this->describeFont($fonts['heading']['family'] ?? '', $weight) . ' for headings';
            }
            if (!empty($fonts['body'])) {
                $weight = $fonts['body']['weight'] ?? 'regular';
                $fontParts[] = $this->describeFont($fonts['body']['family'] ?? '', $weight) . ' for body text';
            }
            if (!empty($fontParts)) {
                $parts[] = "TYPOGRAPHY STYLE: " . implode('; ', $fontParts);
            }
        }

        $stylePreset = $visualContext['style_preset'] ?? null;
        if ($stylePreset) {
            $styleDescription = match ($stylePreset) {
                'modern' => 'Modern aesthetic: clean geometry, sharp edges, bold contrasts, flat design elements, sans-serif dominance',
                'classic' => 'Classic aesthetic: timeless elegance, serif accents, warm neutral tones, symmetrical balance, refined restraint',
                'bold' => 'Bold aesthetic: high-contrast colors, oversized typography, dynamic angles, punchy composition, unapologetic presence',
                'minimal' => 'Minimal aesthetic: extreme simplicity, monochrome with one accent, maximum whitespace, whisper-quiet elegance',
                'playful' => 'Playful aesthetic: rounded shapes, vibrant-but-not-neon colors, organic curves, friendly warmth, approachable creativity',
                default => "Visual style: {$stylePreset}",
            };
            $parts[] = "STYLE: {$styleDescription}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * Describe a font weight as a visual style for prompts.
     */
    protected function describeFont(string $family, string $weight): string
    {
        $weightDescription = match (strtolower($weight)) {
            'thin', 'hairline', '100' => 'ultra-thin elegant',
            'light', '300' => 'light refined',
            'regular', 'normal', '400' => 'clean balanced',
            'medium', '500' => 'medium-weight confident',
            'semibold', 'semi-bold', '600' => 'semi-bold structured',
            'bold', '700' => 'bold geometric',
            'extrabold', 'extra-bold', '800' => 'extra-bold impactful',
            'black', '900' => 'ultra-black commanding',
            default => 'balanced',
        };

        return $family
            ? "{$weightDescription} sans-serif ({$family})"
            : "{$weightDescription} sans-serif";
    }

    /**
     * Generate photography direction based on content type and industry.
     */
    public function getPhotographyDirection(string $contentType, ?string $industry): string
    {
        $cameraDirection = match ($contentType) {
            'carousel' => 'Shot on Sony A7R IV, 35mm f/1.8, single softbox at 45 degrees. Clean editorial framing with precise subject isolation. Even, controlled lighting for consistency across panels.',
            'story', 'reel' => 'Shot on Canon R5, 24mm f/1.4, natural backlight with lens flare. Cinematic shallow depth of field, dynamic movement feel. Dramatic lighting contrast.',
            'quote' => 'Shot on Fujifilm X-T5, 56mm f/1.2, diffused north-facing window light. Soft, contemplative mood. Minimal background detail, portrait-oriented if human subject.',
            'behind-the-scenes' => 'Shot on Leica Q3, 28mm f/1.7, available light only. Documentary style, slightly imperfect framing. Natural grain, authentic moment capture. No artificial posing.',
            'promotional' => 'Shot on Phase One IQ4, 80mm f/2.8, two-light studio setup with rim light. Product/hero emphasis, precise focus on key detail. Commercial-grade lighting and composition.',
            'educational', 'infographic' => 'Shot on Nikon Z8, 50mm f/1.4, soft overhead lighting. Clean, clear subjects photographed at informative angles. Object or concept illustration priority.',
            default => 'Shot on Sony A7R IV, 35mm f/1.8, Bokeh. Soft directional lighting, natural tones. Editorial lifestyle framing with an intentional subject.',
        };

        $industryHints = $this->getIndustryPhotoHints($industry);

        return $industryHints
            ? "{$cameraDirection}\nSubject hints for {$industry}: {$industryHints}"
            : $cameraDirection;
    }

    /**
     * Get industry-specific photography subject hints.
     */
    protected function getIndustryPhotoHints(?string $industry): string
    {
        if (!$industry) {
            return '';
        }

        $industryLower = strtolower($industry);

        return match (true) {
            str_contains($industryLower, 'beauty') || str_contains($industryLower, 'cosmetic') || str_contains($industryLower, 'skincare')
                => 'Skin textures in golden hour light, product swatches on clean surfaces, dewy close-ups, luxurious packaging details',
            str_contains($industryLower, 'food') || str_contains($industryLower, 'gastro') || str_contains($industryLower, 'restaurant') || str_contains($industryLower, 'culinary')
                => 'Overhead food compositions on rustic surfaces, steam and texture details, ingredient close-ups, warm inviting tones',
            str_contains($industryLower, 'fitness') || str_contains($industryLower, 'sport') || str_contains($industryLower, 'gym')
                => 'Dynamic movement freeze-frames, sweat and effort details, dramatic side-lighting, powerful body mechanics',
            str_contains($industryLower, 'tech') || str_contains($industryLower, 'software') || str_contains($industryLower, 'saas')
                => 'Clean device screens in context, minimal workspace setups, geometric precision, cool-toned ambient lighting',
            str_contains($industryLower, 'fashion') || str_contains($industryLower, 'clothing') || str_contains($industryLower, 'apparel')
                => 'Fabric texture close-ups, editorial styling, movement in garments, high-contrast fashion lighting',
            str_contains($industryLower, 'health') || str_contains($industryLower, 'medical') || str_contains($industryLower, 'wellness')
                => 'Clinical precision with warmth, calming environments, trust-building human connections, clean airy spaces',
            str_contains($industryLower, 'real estate') || str_contains($industryLower, 'property') || str_contains($industryLower, 'interior')
                => 'Architectural lines and natural light, warm interior ambiance, lifestyle vignettes in spaces, wide-angle with depth',
            str_contains($industryLower, 'education') || str_contains($industryLower, 'coaching') || str_contains($industryLower, 'training')
                => 'Knowledge-sharing moments, focused engagement, books and notebooks as props, warm study environments',
            str_contains($industryLower, 'travel') || str_contains($industryLower, 'tourism') || str_contains($industryLower, 'hospitality')
                => 'Expansive landscapes with human scale, golden hour destinations, travel lifestyle moments, vibrant local details',
            str_contains($industryLower, 'finance') || str_contains($industryLower, 'banking') || str_contains($industryLower, 'invest')
                => 'Confident professional settings, clean geometric objects, trust and stability visual metaphors, blue-toned palettes',
            default => '',
        };
    }

    /**
     * Resolve the system prompt: use brand's configured prompt or build a default.
     */
    protected function resolveSystemPrompt(Brand $brand): string
    {
        $settings = $brand->automation_settings ?? [];
        $prompt = $settings['text_system_prompt'] ?? '';

        if (!empty($prompt)) {
            return $this->replaceVariables($brand, $prompt);
        }

        return $this->buildDefaultSystemPrompt($brand);
    }

    /**
     * Replace {{variable}} placeholders in a prompt template.
     */
    protected function replaceVariables(Brand $brand, string $prompt): string
    {
        $targetAudience = $brand->target_audience ?? [];
        $voice = $brand->voice ?? [];

        $variables = [
            'brand_name' => $brand->name ?? '',
            'brand_description' => $brand->description ?? '',
            'industry' => $brand->industry ?? '',
            'tone' => $voice['tone'] ?? '',
            'language' => $voice['language'] ?? 'pl',
            'emoji_usage' => $voice['emoji_usage'] ?? 'sometimes',
            'personality' => implode(', ', $voice['personality'] ?? []),
            'target_age_range' => $targetAudience['age_range'] ?? '',
            'target_gender' => $targetAudience['gender'] ?? 'all',
            'interests' => implode(', ', $targetAudience['interests'] ?? []),
            'pain_points' => implode(', ', $targetAudience['pain_points'] ?? []),
            'content_pillars' => implode(', ', array_column($brand->content_pillars ?? [], 'name')),
        ];

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Build a rich default system prompt from brand data.
     */
    protected function buildDefaultSystemPrompt(Brand $brand): string
    {
        $language = $this->getLanguageName($brand->getLanguage());
        $tone = $brand->getTone() ?? 'professional';
        $personality = implode(', ', $brand->getPersonality());
        $emojiUsage = $brand->getEmojiUsage();
        $context = $brand->buildAiContext();

        $prompt = "You are an expert social media content creator for the brand \"{$brand->name}\".";

        if ($brand->description) {
            $prompt .= "\nBrand description: {$brand->description}";
        }

        if ($brand->industry) {
            $prompt .= "\nIndustry: {$brand->industry}";
        }

        $prompt .= "\n\nBRAND VOICE:";
        $prompt .= "\n- Tone: {$tone}";
        if ($personality) {
            $prompt .= "\n- Personality: {$personality}";
        }
        $prompt .= "\n- Language: {$language}";
        $prompt .= "\n- Emoji usage: {$emojiUsage}";

        $prompt .= "\n\nTARGET AUDIENCE:";
        $prompt .= "\n- Age range: " . ($context['target_audience']['age_range'] ?? 'Not specified');
        $prompt .= "\n- Gender: " . ($context['target_audience']['gender'] ?? 'all');
        if (!empty($context['target_audience']['interests'])) {
            $prompt .= "\n- Interests: " . implode(', ', $context['target_audience']['interests']);
        }
        if (!empty($context['target_audience']['pain_points'])) {
            $prompt .= "\n- Pain points: " . implode(', ', $context['target_audience']['pain_points']);
        }

        $pillars = array_column($brand->content_pillars ?? [], 'name');
        if (!empty($pillars)) {
            $prompt .= "\n\nCONTENT PILLARS: " . implode(', ', $pillars);
        }

        $prompt .= <<<'PROMPT'


RULES:
1. Write all content in the specified language
2. Match the brand's voice exactly
3. Create content that resonates with the target audience
4. Include a strong hook (first sentence that grabs attention)
5. Provide value to the reader
6. Include a call-to-action when appropriate
7. DO NOT use Unicode bold/italic formatting - use plain text only

RESPONSE FORMAT:
You MUST respond with valid JSON only. No additional text, no markdown code blocks.
{
  "caption": "The main content/caption for the post",
  "title": "A short title for the post (max 100 characters)"
}
PROMPT;

        return $prompt;
    }

    /**
     * Parse the AI response JSON.
     */
    protected function parseResponse(string $content): array
    {
        $content = trim($content);

        // Strip markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        // Try to extract the first JSON object if there's surrounding text
        if (!str_starts_with($content, '{')) {
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $content = $matches[0];
            }
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Last resort: treat entire response as the caption
            Log::warning('Direct text generation: could not parse JSON, using raw text', [
                'raw' => mb_substr($content, 0, 200),
            ]);

            return [
                'caption' => $content,
                'title' => '',
            ];
        }

        // Convert literal \n to actual newlines
        $convertNewlines = fn($text) => is_string($text) ? str_replace('\n', "\n", $text) : $text;

        return [
            'caption' => $convertNewlines($decoded['caption'] ?? $decoded['main_caption'] ?? ''),
            'title' => $convertNewlines($decoded['title'] ?? ''),
        ];
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
}
