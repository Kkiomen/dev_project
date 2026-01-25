<?php

namespace App\Services;

use App\Enums\LayerType;
use App\Models\Layer;
use App\Models\Template;
use App\Services\AI\CompositionArchetypeService;
use App\Services\AI\ContrastValidator;
use App\Services\AI\DesignTokensService;
use App\Services\AI\ElevationService;
use App\Services\AI\FormatService;
use App\Services\AI\GridSnapService;
use App\Services\AI\ImageAnalysisService;
use App\Services\AI\LayoutPatternTracker;
use App\Services\AI\PhotoRankingService;
use App\Services\AI\PremiumQueryBuilder;
use App\Services\AI\SelfCorrectionService;
use App\Services\AI\TemplateValidator;
use App\Services\AI\TypographyHierarchyValidator;
use App\Services\AI\VisualCriticService;
use App\Services\Concerns\LogsApiUsage;
use App\Services\UnsplashService;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    use LogsApiUsage;

    /**
     * Track if plan_design was called in current session.
     */
    protected bool $designPlanApproved = false;

    /**
     * Current design plan data.
     */
    protected ?array $currentDesignPlan = null;

    /**
     * Language codes to language names mapping.
     */
    protected const LANGUAGE_NAMES = [
        'pl' => 'Polish (Polski)',
        'en' => 'English',
        'de' => 'German (Deutsch)',
        'fr' => 'French (Français)',
        'es' => 'Spanish (Español)',
        'it' => 'Italian (Italiano)',
    ];

    /**
     * Example phrases in each language for the AI prompt.
     */
    protected const LANGUAGE_EXAMPLES = [
        'pl' => [
            'headline' => 'Odkryj Nową Erę Twojego Stylu',
            'subtext' => 'Profesjonalna pielęgnacja dla wymagających',
            'cta' => 'Sprawdź Teraz',
        ],
        'en' => [
            'headline' => 'Discover Your Perfect Style',
            'subtext' => 'Professional care for the discerning',
            'cta' => 'Learn More',
        ],
        'de' => [
            'headline' => 'Entdecke Deinen Perfekten Stil',
            'subtext' => 'Professionelle Pflege für anspruchsvolle Kunden',
            'cta' => 'Mehr Erfahren',
        ],
    ];

    public function __construct(
        protected OpenAiClientService $openAiClient,
        protected TemplateContextService $contextService,
        protected UnsplashService $stockPhotoService, // Switched from PexelsService to UnsplashService
        protected LayoutPatternTracker $layoutTracker,
        protected TemplateValidator $templateValidator,
        protected GridSnapService $gridSnapService,
        protected DesignTokensService $designTokensService,
        protected ImageAnalysisService $imageAnalysisService,
        protected SelfCorrectionService $selfCorrectionService,
        protected ContrastValidator $contrastValidator,
        protected TypographyHierarchyValidator $typographyValidator,
        protected CompositionArchetypeService $compositionArchetypeService,
        protected PremiumQueryBuilder $premiumQueryBuilder,
        protected ElevationService $elevationService,
        protected FormatService $formatService,
        protected VisualCriticService $visualCriticService,
        protected ?PhotoRankingService $photoRankingService = null
    ) {
        $this->photoRankingService = $photoRankingService ?? new PhotoRankingService($imageAnalysisService);
    }

    /**
     * Process a chat message and return AI response with actions.
     */
    public function chat(Template $template, array $history, string $userMessage): array
    {
        $startTime = microtime(true);
        $brand = $template->brand;

        Log::channel('single')->info('=== AI CHAT REQUEST ===', [
            'template_id' => $template->public_id,
            'template_name' => $template->name,
            'user_message' => $userMessage,
            'history_count' => count($history),
        ]);

        // Start API usage logging
        $log = $this->logAiStart($brand, 'template_ai_chat', [
            'template_id' => $template->public_id,
            'user_message' => $userMessage,
            'history_count' => count($history),
        ]);

        try {
            $systemPrompt = $this->buildSystemPrompt($template);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            // Add conversation history
            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            // Add current user message
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            // Get available tools
            $tools = $this->getAvailableTools();

            // Multi-turn loop - continue until we get a final response or create_full_template
            $maxIterations = 3;
            $iteration = 0;
            $allActions = [];
            $totalPromptTokens = 0;
            $totalCompletionTokens = 0;

            while ($iteration < $maxIterations) {
                $iteration++;

                // Call OpenAI
                $response = $this->openAiClient->chatCompletion($messages, $tools);

                $totalPromptTokens += $response->usage->promptTokens ?? 0;
                $totalCompletionTokens += $response->usage->completionTokens ?? 0;

                Log::channel('single')->info("=== OPENAI RESPONSE (iteration {$iteration}) ===", [
                    'has_tool_calls' => ! empty($response->choices[0]->message->toolCalls),
                    'content' => $response->choices[0]->message->content,
                    'finish_reason' => $response->choices[0]->finishReason,
                ]);

                $message = $response->choices[0]->message;

                // If no tool calls, we're done
                if (empty($message->toolCalls)) {
                    $reply = $message->content ?? '';
                    break;
                }

                // Process tool calls
                $toolResults = [];
                $hasPlanDesign = false;
                $hasCreateTemplate = false;

                foreach ($message->toolCalls as $idx => $toolCall) {
                    $functionName = $toolCall->function->name;
                    $arguments = json_decode($toolCall->function->arguments, true);

                    Log::channel('single')->info("=== TOOL CALL #{$idx} (iteration {$iteration}) ===", [
                        'function_name' => $functionName,
                        'arguments' => $arguments,
                    ]);

                    if ($functionName === 'plan_design') {
                        $hasPlanDesign = true;
                    }
                    if ($functionName === 'create_full_template') {
                        $hasCreateTemplate = true;
                    }

                    $action = $this->executeToolCall($functionName, $arguments, $template);

                    if ($action) {
                        if (is_array($action) && isset($action[0])) {
                            $allActions = array_merge($allActions, $action);
                        } else {
                            $allActions[] = $action;
                        }
                    }

                    // Prepare tool result for next API call
                    $toolResults[] = [
                        'tool_call_id' => $toolCall->id,
                        'function_name' => $functionName,
                        'result' => json_encode($action['data'] ?? ['success' => true]),
                    ];
                }

                // If we got create_full_template, we're done
                if ($hasCreateTemplate) {
                    $reply = $this->generateActionSummary($allActions, $template);
                    break;
                }

                // If only plan_design was called, continue the conversation
                if ($hasPlanDesign && ! $hasCreateTemplate) {
                    // Add assistant message with tool calls
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $message->content,
                        'tool_calls' => array_map(fn($tc) => [
                            'id' => $tc->id,
                            'type' => 'function',
                            'function' => [
                                'name' => $tc->function->name,
                                'arguments' => $tc->function->arguments,
                            ],
                        ], $message->toolCalls),
                    ];

                    // Add tool results
                    foreach ($toolResults as $result) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $result['tool_call_id'],
                            'content' => $result['result'],
                        ];
                    }

                    Log::channel('single')->info('=== CONTINUING CONVERSATION AFTER PLAN_DESIGN ===');
                    continue;
                }

                // For other tools, we're done
                $reply = $this->generateActionSummary($allActions, $template);
                break;
            }

            $result = [
                'success' => true,
                'reply' => $reply ?? $this->generateActionSummary($allActions, $template),
                'actions' => $allActions,
                'usage' => [
                    'prompt_tokens' => $totalPromptTokens,
                    'completion_tokens' => $totalCompletionTokens,
                    'total_tokens' => $totalPromptTokens + $totalCompletionTokens,
                ],
            ];

            // Complete logging with token usage
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->completeAiLog(
                $log,
                [
                    'reply' => $result['reply'] ?? '',
                    'actions' => $result['actions'] ?? [],
                    'actions_count' => count($result['actions'] ?? []),
                ],
                $totalPromptTokens,
                $totalCompletionTokens,
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
     * Build the system prompt with template context.
     */
    protected function buildSystemPrompt(Template $template): string
    {
        $context = $this->contextService->getSimplifiedContext($template);
        $layerTypes = $this->contextService->getLayerTypeDefinitions();
        $pexelsAvailable = $this->stockPhotoService->isConfigured() ? 'YES' : 'NO';

        // Get layout restrictions from tracker
        $layoutInstructions = $this->layoutTracker->getPromptInstructions($template->brand);

        // Get design tokens for brand
        $designTokens = $this->designTokensService->getTokensForPrompt($template->brand);

        return <<<PROMPT
You are a professional template designer using a DESIGN SYSTEM.

TEMPLATE: {$context}
LAYER TYPES: {$layerTypes}
PEXELS: {$pexelsAvailable}

################################################################################
#                    DESIGN SYSTEM (enforced automatically)
################################################################################

GRID SYSTEM (8pt):
- All coordinates MUST be multiples of 8
- Valid positions: 0, 8, 16, 24, 32, 40, 48, 56, 64, 72, 80...
- Valid sizes: 8, 16, 24, 32, 40, 48, 56, 64, 72, 80, 96, 120...
- Margins: use 80px (10×8) for professional spacing

{$designTokens}

CONSTRAINTS (auto-validated):
- Typography hierarchy: headline > subtext > CTA (font size)
- Contrast: WCAG AA minimum (4.5:1 for text)
- Grid snap: all values rounded to 8pt
- Font sizes: from modular scale only

################################################################################
#                    COMPOSITION ARCHETYPES
################################################################################

CRITICAL POSITIONING RULE:
- Canvas: 1080x1080
- Safe margin: 80px (10% of canvas)
- Usable width: 920px (1080 - 2*80)
- ALL text layers must use x=80, width=920 (or less)
- NEVER place text with x > 80 unless in a column layout

Choose ONE archetype based on image focal point:

1. hero_left: Text left column (40%), photo right (60%)
   - Use when: focal point is on RIGHT side of image
   - Text zone: x=80, y=200, width=400 (left column only)

2. hero_right: Photo left (60%), text right column (40%)
   - Use when: focal point is on LEFT side of image
   - Text zone: x=600, y=200, width=400 (right column only)

3. split_diagonal: Diagonal split with overlay
   - Use when: centered focal point
   - Text zone: x=80, width=920 (full usable width)
   - Requires semi-transparent overlay for text readability

4. bottom_focus: Photo top (60%), text block bottom (40%)
   - Use when: focal point is in UPPER half
   - Text zone: x=80, y=650, width=920 (full usable width)
   - ALL text layers (headline, subtext, CTA) use same x=80

5. centered_minimal: Full-bleed photo, centered text
   - Default when unsure
   - Text zone: x=80, width=920, align=center
   - Requires overlay (opacity 0.6) for readability

################################################################################
#                    LAYER ORDER (CRITICAL!)
################################################################################

- FIRST layer in array = BOTTOM (behind everything)
- LAST layer in array = TOP (in front of everything)
- Correct order: background → photo → overlay → accent → headline → subtext → CTA

################################################################################
#                    MANDATORY ELEMENTS (5-7 layers)
################################################################################

1. ✅ BACKGROUND (type: "rectangle") - FIRST IN ARRAY
   - Full canvas size, dark color (#1E3A5F, #0F2544, #1A1A2E)

2. ✅ PHOTO (type: "image") - AFTER BACKGROUND
   - Main visual, fit: "cover"

3. ✅ ACCENT (type: "line" or "rectangle")
   - Thin decorative elements, NOT blobs

4. ✅ HEADLINE (type: "text")
   - 4-8 creative words
   - fontSize: 39-61px (from scale), fontWeight: "bold"
   - fill: #FFFFFF

5. ✅ SUBTEXT (type: "text")
   - Supporting text, fontSize: 16-25px
   - fill: Use 30% lighter tint of background (NOT #CCCCCC - too generic!)

6. ✅ CTA BUTTON (type: "textbox") - LAST IN ARRAY
   - cornerRadius: 500 (pill), fill: accent color

################################################################################
#                           VISUAL GUIDELINES
################################################################################

TYPOGRAPHY:
- Google Fonts ONLY: Montserrat, Poppins, Playfair Display, Oswald, Lora, Inter
- NEVER Arial, Helvetica, Times

LAYOUT VARIETY:
{$layoutInstructions}

################################################################################
#                              HOW TO CREATE
################################################################################

Step 1: Call plan_design with:
  - composition_archetype: Choose based on expected image focal point
  - headline, subtext, cta_text: Creative content

Step 2: Call create_full_template with:
  - template_settings: {background_color: "#1E3A5F"}
  - layers: 5-7 layers IN CORRECT ORDER
  - image_searches: Pexels search for photos

################################################################################
#                              BANNED
################################################################################

❌ White text on white/light background
❌ Less than 5 layers
❌ Wrong layer order
❌ Corner blobs/decorative circles
❌ Headlines shorter than 4 words
❌ Arial, Helvetica, Times fonts
❌ Arbitrary font sizes (use scale only)

{$this->getLanguageInstructions($template)}
PROMPT;
    }

    /**
     * Detect user language from template context.
     */
    protected function detectUserLanguage(Template $template): string
    {
        $detectionMethod = 'default';
        $detectedLanguage = 'pl';

        // Check template language setting
        $templateLanguage = $template->language ?? null;
        if ($templateLanguage && isset(self::LANGUAGE_NAMES[$templateLanguage])) {
            $detectedLanguage = $templateLanguage;
            $detectionMethod = 'template_setting';
        }
        // Check brand language setting
        elseif (($brandLanguage = $template->brand?->language ?? null) && isset(self::LANGUAGE_NAMES[$brandLanguage])) {
            $detectedLanguage = $brandLanguage;
            $detectionMethod = 'brand_setting';
        }
        // Check template name for language hints
        else {
            $templateName = strtolower($template->name ?? '');
            if (preg_match('/[ąćęłńóśźż]/u', $templateName)) {
                $detectedLanguage = 'pl';
                $detectionMethod = 'name_characters_polish';
            } elseif (preg_match('/[äöüß]/u', $templateName)) {
                $detectedLanguage = 'de';
                $detectionMethod = 'name_characters_german';
            }
        }

        Log::channel('single')->info('Language: Detected user language', [
            'detected_language' => $detectedLanguage,
            'detection_method' => $detectionMethod,
            'language_name' => self::LANGUAGE_NAMES[$detectedLanguage] ?? 'Unknown',
            'template_id' => $template->public_id,
        ]);

        return $detectedLanguage;
    }

    /**
     * Get language instructions for the system prompt.
     */
    protected function getLanguageInstructions(Template $template): string
    {
        $language = $this->detectUserLanguage($template);
        $languageName = self::LANGUAGE_NAMES[$language] ?? 'Polish (Polski)';
        $examples = self::LANGUAGE_EXAMPLES[$language] ?? self::LANGUAGE_EXAMPLES['pl'];

        return <<<LANG
################################################################################
#                    LANGUAGE REQUIREMENTS (CRITICAL!)
################################################################################

USER LANGUAGE: {$languageName}

CRITICAL: All generated text content MUST be in {$languageName}!
- Headlines: Write in {$languageName} (e.g., "{$examples['headline']}")
- Subtext: Write in {$languageName} (e.g., "{$examples['subtext']}")
- CTA buttons: Write in {$languageName} (e.g., "{$examples['cta']}")

DO NOT generate English text for non-English speaking users!
If the user writes in Polish, respond with Polish text content.
LANG;
    }

    /**
     * Validate that text is in the expected language.
     */
    protected function isTextInLanguage(string $text, string $expectedLanguage): bool
    {
        $text = strtolower($text);

        if ($expectedLanguage === 'pl') {
            // Check for Polish-specific characters or common Polish words
            if (preg_match('/[ąćęłńóśźż]/u', $text)) {
                return true;
            }
            // Common Polish words
            $polishWords = ['nowy', 'twój', 'twoja', 'odkryj', 'sprawdź', 'teraz', 'więcej', 'profesjonalna'];
            foreach ($polishWords as $word) {
                if (str_contains($text, $word)) {
                    return true;
                }
            }
            // Reject common English words
            $englishWords = ['discover', 'your', 'new', 'check', 'learn', 'more', 'the', 'and', 'for'];
            $englishCount = 0;
            foreach ($englishWords as $word) {
                if (str_contains($text, $word)) {
                    $englishCount++;
                }
            }
            if ($englishCount >= 2) {
                return false;
            }
        }

        if ($expectedLanguage === 'de') {
            if (preg_match('/[äöüß]/u', $text)) {
                return true;
            }
        }

        // For other languages or if uncertain, assume valid
        return true;
    }

    /**
     * Get available tools for function calling.
     */
    protected function getAvailableTools(): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'plan_design',
                    'description' => 'REQUIRED FIRST STEP before create_full_template. Plan ALL mandatory elements: headline, CTA, photo, subtext. Choose a composition archetype based on image focal point.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'layout_description' => [
                                'type' => 'string',
                                'description' => 'Describe your unique layout idea. Where will photo be? Where headline? Where CTA button? Example: "Photo full-bleed at top 60%, headline overlaid at bottom with semi-transparent navy bar, CTA button centered below"',
                            ],
                            'composition_archetype' => [
                                'type' => 'string',
                                'description' => 'Choose composition archetype based on image focal point. hero_left: text left (40%), photo right (60%). hero_right: photo left (60%), text right (40%). split_diagonal: diagonal split with overlay. bottom_focus: photo top, text block bottom. centered_minimal: full-bleed photo with centered text.',
                                'enum' => ['hero_left', 'hero_right', 'split_diagonal', 'bottom_focus', 'centered_minimal'],
                            ],
                            'color_scheme' => [
                                'type' => 'string',
                                'description' => 'Color scheme with hex values. Example: "Navy background #1E3A5F, gold CTA button #D4AF37, white text #FFFFFF"',
                            ],
                            'headline' => [
                                'type' => 'string',
                                'description' => 'Creative headline 4-8 words. NOT just "Nowa"! Example: "Odkryj Nową Erę Twojego Stylu"',
                            ],
                            'subtext' => [
                                'type' => 'string',
                                'description' => 'Supporting text/tagline. Example: "Profesjonalna pielęgnacja dla wymagających"',
                            ],
                            'cta_text' => [
                                'type' => 'string',
                                'description' => 'CTA button text. Example: "Sprawdź Teraz", "Rezerwuj Wizytę", "Dowiedz Się Więcej"',
                            ],
                        ],
                        'required' => ['layout_description', 'composition_archetype', 'color_scheme', 'headline', 'subtext', 'cta_text'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_full_template',
                    'description' => 'Create a complete, professional template with multiple layers. CRITICAL: Every layer MUST have a "properties" object with appropriate values! Text layers need: text, fontSize, fontWeight, fill, align. Shapes need: fill color. Images need: fit. Without properties, layers will have ugly defaults!',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'template_settings' => [
                                'type' => 'object',
                                'description' => 'Template canvas settings',
                                'properties' => [
                                    'width' => ['type' => 'integer', 'description' => 'Canvas width in pixels'],
                                    'height' => ['type' => 'integer', 'description' => 'Canvas height in pixels'],
                                    'background_color' => ['type' => 'string', 'description' => 'Background color hex'],
                                ],
                                'required' => ['width', 'height', 'background_color'],
                            ],
                            'layers' => [
                                'type' => 'array',
                                'description' => 'Array of layers. EACH LAYER MUST HAVE properties!',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string', 'description' => 'Unique layer name'],
                                        'type' => ['type' => 'string', 'enum' => ['text', 'textbox', 'image', 'rectangle', 'ellipse', 'line']],
                                        'x' => ['type' => 'number', 'description' => 'X position'],
                                        'y' => ['type' => 'number', 'description' => 'Y position'],
                                        'width' => ['type' => 'number', 'description' => 'Width in pixels (textbox auto-resizes)'],
                                        'height' => ['type' => 'number', 'description' => 'Height in pixels (textbox auto-resizes)'],
                                        'properties' => [
                                            'type' => 'object',
                                            'description' => 'REQUIRED! TEXT: {text, fontFamily, fontSize, fontWeight, fill, align}. TEXTBOX (button): {text, fontFamily, fontSize, fontWeight, fill (background), textColor, padding, cornerRadius, align}. RECTANGLE/ELLIPSE: {fill OR fillType:"gradient"}. IMAGE: {fit}. LINE: {points, stroke, strokeWidth}.',
                                            'properties' => [
                                                'text' => ['type' => 'string', 'description' => 'For text/textbox: actual content'],
                                                'fontFamily' => ['type' => 'string', 'description' => 'Any Google Font: Roboto, Montserrat, Poppins, Playfair Display, Oswald, Raleway, Bebas Neue, Dancing Script, Pacifico, Lobster, etc.'],
                                                'fontSize' => ['type' => 'number', 'description' => 'Size in pixels (48-72 headlines, 18-32 body, 14-18 buttons)'],
                                                'fontWeight' => ['type' => 'string', 'description' => 'normal, bold, or 100-900 (600 for buttons)'],
                                                'fontStyle' => ['type' => 'string', 'enum' => ['normal', 'italic']],
                                                'fill' => ['type' => 'string', 'description' => 'Color hex. For text: text color. For textbox: background color.'],
                                                'textColor' => ['type' => 'string', 'description' => 'For textbox: text color (usually white #FFFFFF)'],
                                                'padding' => ['type' => 'number', 'description' => 'For textbox: inner padding (default 16)'],
                                                'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                                                'verticalAlign' => ['type' => 'string', 'enum' => ['top', 'middle', 'bottom']],
                                                'lineHeight' => ['type' => 'number', 'description' => '1.0 tight, 1.2 normal, 1.5 loose'],
                                                'letterSpacing' => ['type' => 'number', 'description' => '0 normal, 2-5 for spaced headlines'],
                                                'textTransform' => ['type' => 'string', 'enum' => ['none', 'uppercase', 'lowercase', 'capitalize'], 'description' => 'Use uppercase for modern headlines!'],
                                                'textDecoration' => ['type' => 'string', 'enum' => ['', 'underline', 'line-through']],
                                                'cornerRadius' => ['type' => 'number', 'description' => 'For rectangles/textbox: rounded corners'],
                                                'fit' => ['type' => 'string', 'enum' => ['cover', 'contain']],
                                                'fillType' => ['type' => 'string', 'enum' => ['solid', 'gradient']],
                                                'gradientStartColor' => ['type' => 'string', 'description' => 'Gradient start hex'],
                                                'gradientEndColor' => ['type' => 'string', 'description' => 'Gradient end hex'],
                                                'gradientAngle' => ['type' => 'number', 'description' => 'Gradient angle 0-360'],
                                                'stroke' => ['type' => 'string', 'description' => 'For lines/shapes: stroke color hex'],
                                                'strokeWidth' => ['type' => 'number', 'description' => 'Stroke thickness'],
                                                'points' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'For lines: [x1,y1,x2,y2]'],
                                                'lineCap' => ['type' => 'string', 'enum' => ['butt', 'round', 'square']],
                                                'dash' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'Dash pattern e.g. [10,5] or []'],
                                                'opacity' => ['type' => 'number', 'description' => '0-1 opacity'],
                                                'shadowEnabled' => ['type' => 'boolean'],
                                                'shadowColor' => ['type' => 'string', 'description' => 'Shadow color hex'],
                                                'shadowBlur' => ['type' => 'number', 'description' => 'Shadow blur radius'],
                                                'shadowOffsetX' => ['type' => 'number'],
                                                'shadowOffsetY' => ['type' => 'number'],
                                                'shadowOpacity' => ['type' => 'number', 'description' => '0-1'],
                                            ],
                                        ],
                                    ],
                                    'required' => ['name', 'type', 'x', 'y', 'width', 'height', 'properties'],
                                ],
                            ],
                            'image_searches' => [
                                'type' => 'array',
                                'description' => 'Images to search for on Pexels',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'layer_name' => ['type' => 'string', 'description' => 'Name of the image layer to fill'],
                                        'search_query' => ['type' => 'string', 'description' => 'Search query in English for Pexels'],
                                        'orientation' => ['type' => 'string', 'enum' => ['landscape', 'portrait', 'square']],
                                    ],
                                    'required' => ['layer_name', 'search_query'],
                                ],
                            ],
                        ],
                        'required' => ['template_settings', 'layers'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'modify_layer',
                    'description' => 'Modify properties of an existing layer by name. Use this to change text, colors, font properties, position, size, etc.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'layer_name' => [
                                'type' => 'string',
                                'description' => 'The exact name of the layer to modify (case-sensitive)',
                            ],
                            'changes' => [
                                'type' => 'object',
                                'description' => 'Object containing the properties to change',
                                'properties' => [
                                    'x' => ['type' => 'number', 'description' => 'X position'],
                                    'y' => ['type' => 'number', 'description' => 'Y position'],
                                    'width' => ['type' => 'number', 'description' => 'Width in pixels'],
                                    'height' => ['type' => 'number', 'description' => 'Height in pixels'],
                                    'rotation' => ['type' => 'number', 'description' => 'Rotation in degrees'],
                                    'visible' => ['type' => 'boolean', 'description' => 'Layer visibility'],
                                    'properties' => [
                                        'type' => 'object',
                                        'description' => 'Type-specific properties to modify. TEXT: {text, fontFamily (any Google Font!), fontSize, fontWeight (100-900), fontStyle, fill, align, verticalAlign, lineHeight, letterSpacing, textTransform, textDecoration}. RECTANGLE/ELLIPSE: {fill, OR fillType+gradient*, cornerRadius}. LINE: {stroke, strokeWidth, dash, lineCap}. COMMON: {opacity, shadow*}.',
                                        'properties' => [
                                            'text' => ['type' => 'string'],
                                            'fontFamily' => ['type' => 'string', 'description' => 'Any Google Font: Roboto, Montserrat, Poppins, Oswald, Playfair Display, etc.'],
                                            'fontSize' => ['type' => 'number'],
                                            'fontWeight' => ['type' => 'string', 'description' => 'normal, bold, or 100-900'],
                                            'fontStyle' => ['type' => 'string', 'enum' => ['normal', 'italic']],
                                            'lineHeight' => ['type' => 'number', 'description' => '1.0-1.5'],
                                            'letterSpacing' => ['type' => 'number', 'description' => '0-5 pixels'],
                                            'textTransform' => ['type' => 'string', 'enum' => ['none', 'uppercase', 'lowercase', 'capitalize']],
                                            'textDecoration' => ['type' => 'string', 'enum' => ['', 'underline', 'line-through']],
                                            'fill' => ['type' => 'string', 'description' => 'Color hex for solid fill'],
                                            'fillType' => ['type' => 'string', 'enum' => ['solid', 'gradient']],
                                            'gradientStartColor' => ['type' => 'string'],
                                            'gradientEndColor' => ['type' => 'string'],
                                            'gradientAngle' => ['type' => 'number'],
                                            'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                                            'verticalAlign' => ['type' => 'string', 'enum' => ['top', 'middle', 'bottom']],
                                            'cornerRadius' => ['type' => 'number'],
                                            'stroke' => ['type' => 'string'],
                                            'strokeWidth' => ['type' => 'number'],
                                            'dash' => ['type' => 'array', 'items' => ['type' => 'number']],
                                            'lineCap' => ['type' => 'string', 'enum' => ['butt', 'round', 'square']],
                                            'opacity' => ['type' => 'number'],
                                            'shadowEnabled' => ['type' => 'boolean'],
                                            'shadowColor' => ['type' => 'string'],
                                            'shadowBlur' => ['type' => 'number'],
                                            'shadowOffsetX' => ['type' => 'number'],
                                            'shadowOffsetY' => ['type' => 'number'],
                                            'shadowOpacity' => ['type' => 'number'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'required' => ['layer_name', 'changes'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_layer',
                    'description' => 'Add a single new layer to the template. ALWAYS include properties with appropriate values for the layer type!',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Name for the new layer'],
                            'type' => ['type' => 'string', 'enum' => ['text', 'textbox', 'image', 'rectangle', 'ellipse', 'line']],
                            'x' => ['type' => 'number', 'description' => 'X position in pixels'],
                            'y' => ['type' => 'number', 'description' => 'Y position in pixels'],
                            'width' => ['type' => 'number', 'description' => 'Width in pixels (textbox auto-resizes based on text)'],
                            'height' => ['type' => 'number', 'description' => 'Height in pixels (textbox auto-resizes based on text)'],
                            'properties' => [
                                'type' => 'object',
                                'description' => 'REQUIRED! TEXT: {text, fontFamily (any Google Font!), fontSize, fontWeight (100-900), fontStyle, fill, align, lineHeight, textTransform}. TEXTBOX (button with text): {text, fontFamily, fontSize, fontWeight, fill (background color), textColor, padding, cornerRadius, align}. RECTANGLE/ELLIPSE: {fill OR fillType:"gradient" with gradient*, cornerRadius}. IMAGE: {src, fit}. LINE: {points, stroke, strokeWidth, lineCap, dash}.',
                                'properties' => [
                                    'text' => ['type' => 'string', 'description' => 'For text/textbox: actual content'],
                                    'fontFamily' => ['type' => 'string', 'description' => 'Any Google Font: Roboto, Montserrat, Poppins, Oswald, Playfair Display, Bebas Neue, etc.'],
                                    'fontSize' => ['type' => 'number', 'description' => 'Size in pixels (48-72 headlines, 18-32 body, 14-18 for buttons)'],
                                    'fontWeight' => ['type' => 'string', 'description' => 'normal, bold, or 100-900 (600 recommended for buttons)'],
                                    'fontStyle' => ['type' => 'string', 'enum' => ['normal', 'italic']],
                                    'lineHeight' => ['type' => 'number', 'description' => '1.0 tight, 1.2 normal, 1.5 loose'],
                                    'letterSpacing' => ['type' => 'number', 'description' => '0 normal, 2-5 for spaced headlines'],
                                    'textTransform' => ['type' => 'string', 'enum' => ['none', 'uppercase', 'lowercase', 'capitalize']],
                                    'textDecoration' => ['type' => 'string', 'enum' => ['', 'underline', 'line-through']],
                                    'fill' => ['type' => 'string', 'description' => 'Color in hex. For text: text color. For textbox: background color.'],
                                    'textColor' => ['type' => 'string', 'description' => 'For textbox only: text color (usually white #FFFFFF on colored buttons)'],
                                    'padding' => ['type' => 'number', 'description' => 'For textbox: inner padding in pixels (default 16)'],
                                    'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                                    'verticalAlign' => ['type' => 'string', 'enum' => ['top', 'middle', 'bottom']],
                                    'cornerRadius' => ['type' => 'number', 'description' => 'For rectangles/textbox: rounded corners (8 default for buttons)'],
                                    'fit' => ['type' => 'string', 'enum' => ['cover', 'contain']],
                                    'stroke' => ['type' => 'string', 'description' => 'For lines: color in hex'],
                                    'strokeWidth' => ['type' => 'number', 'description' => 'For lines: thickness'],
                                    'points' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'For lines: [x1,y1,x2,y2]'],
                                    'lineCap' => ['type' => 'string', 'enum' => ['butt', 'round', 'square']],
                                    'dash' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'Dash pattern [10,5] or []'],
                                    'fillType' => ['type' => 'string', 'enum' => ['solid', 'gradient']],
                                    'gradientStartColor' => ['type' => 'string', 'description' => 'Gradient start hex'],
                                    'gradientEndColor' => ['type' => 'string', 'description' => 'Gradient end hex'],
                                    'gradientAngle' => ['type' => 'number', 'description' => 'Gradient angle 0-360'],
                                    'opacity' => ['type' => 'number', 'description' => '0-1'],
                                    'shadowEnabled' => ['type' => 'boolean'],
                                    'shadowColor' => ['type' => 'string'],
                                    'shadowBlur' => ['type' => 'number'],
                                    'shadowOffsetX' => ['type' => 'number'],
                                    'shadowOffsetY' => ['type' => 'number'],
                                    'shadowOpacity' => ['type' => 'number'],
                                ],
                            ],
                        ],
                        'required' => ['name', 'type', 'properties'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_images',
                    'description' => 'Search for stock photos on Pexels. Use this when you need to find images for a template. Returns image URLs that can be used in image layers.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query in English (e.g., "beauty salon", "fitness woman", "healthy food")',
                            ],
                            'orientation' => [
                                'type' => 'string',
                                'enum' => ['landscape', 'portrait', 'square'],
                                'description' => 'Image orientation preference',
                            ],
                            'count' => [
                                'type' => 'integer',
                                'description' => 'Number of images to return (1-5)',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_layer',
                    'description' => 'Remove a layer from the template by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'layer_name' => ['type' => 'string'],
                        ],
                        'required' => ['layer_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_template',
                    'description' => 'Update template properties like background color or dimensions.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'background_color' => ['type' => 'string'],
                            'width' => ['type' => 'integer'],
                            'height' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_api_info',
                    'description' => 'Get information about using the API.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'topic' => [
                                'type' => 'string',
                                'enum' => ['generate', 'authentication', 'templates', 'layers', 'general'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $tools;
    }

    /**
     * Process OpenAI response and extract actions.
     */
    protected function processResponse($response, Template $template): array
    {
        $message = $response->choices[0]->message;
        $actions = [];
        $reply = '';

        // Check if there are tool calls
        if (! empty($message->toolCalls)) {
            foreach ($message->toolCalls as $toolCall) {
                $functionName = $toolCall->function->name;
                $arguments = json_decode($toolCall->function->arguments, true);

                $action = $this->executeToolCall($functionName, $arguments, $template);
                if ($action) {
                    if (is_array($action) && isset($action[0])) {
                        // Multiple actions returned
                        $actions = array_merge($actions, $action);
                    } else {
                        $actions[] = $action;
                    }
                }
            }

            // Generate a follow-up response describing what was done
            $reply = $this->generateActionSummary($actions, $template);
        } else {
            // Regular text response
            $reply = $message->content ?? '';
        }

        Log::channel('single')->info('=== FINAL RESPONSE ===', [
            'actions_count' => count($actions),
            'actions_types' => array_map(fn($a) => $a['type'] ?? 'unknown', $actions),
            'reply_length' => strlen($reply),
        ]);

        // Log detailed actions
        foreach ($actions as $idx => $action) {
            Log::channel('single')->info("=== FINAL ACTION #{$idx} ===", [
                'type' => $action['type'] ?? 'unknown',
                'data' => $action['data'] ?? $action,
            ]);
        }

        return [
            'success' => true,
            'reply' => $reply,
            'actions' => $actions,
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'total_tokens' => $response->usage->totalTokens,
            ],
        ];
    }

    /**
     * Execute a tool call and return the action data.
     */
    protected function executeToolCall(string $functionName, array $arguments, Template $template): mixed
    {
        return match ($functionName) {
            'plan_design' => $this->handlePlanDesign($arguments, $template),
            'create_full_template' => $this->handleCreateFullTemplate($arguments, $template),
            'modify_layer' => $this->handleModifyLayer($arguments, $template),
            'add_layer' => $this->handleAddLayer($arguments, $template),
            'delete_layer' => $this->handleDeleteLayer($arguments, $template),
            'update_template' => $this->handleUpdateTemplate($arguments, $template),
            'search_images' => $this->handleSearchImages($arguments, $template),
            'get_api_info' => $this->handleGetApiInfo($arguments),
            default => null,
        };
    }

    /**
     * Handle plan_design - validates and stores the design plan before template creation.
     */
    protected function handlePlanDesign(array $arguments, Template $template): array
    {
        $layoutDescription = $arguments['layout_description'] ?? '';
        $compositionArchetype = $arguments['composition_archetype'] ?? 'centered_minimal';
        $colorScheme = $arguments['color_scheme'] ?? '';
        $headline = $arguments['headline'] ?? '';
        $subtext = $arguments['subtext'] ?? '';
        $ctaText = $arguments['cta_text'] ?? 'Sprawdź Teraz';

        Log::channel('single')->info('=== PLAN DESIGN CALLED ===', [
            'layout_description' => $layoutDescription,
            'composition_archetype' => $compositionArchetype,
            'color_scheme' => $colorScheme,
            'headline' => $headline,
            'subtext' => $subtext,
            'cta_text' => $ctaText,
        ]);

        // Check if archetype was recently used
        $forbiddenArchetypes = $this->layoutTracker->getForbiddenLayouts($template->brand);
        if (in_array($compositionArchetype, $forbiddenArchetypes)) {
            $allowedArchetypes = array_diff(
                $this->compositionArchetypeService->getArchetypeNames(),
                $forbiddenArchetypes
            );
            Log::channel('single')->warning('Composition archetype recently used - rejecting', [
                'tried' => $compositionArchetype,
                'forbidden' => $forbiddenArchetypes,
                'allowed' => $allowedArchetypes,
            ]);

            return [
                'type' => 'plan_design_rejected',
                'data' => [
                    'success' => false,
                    'message' => "Archetype '{$compositionArchetype}' was recently used! Try a different one: " . implode(', ', $allowedArchetypes),
                    'allowed_archetypes' => array_values($allowedArchetypes),
                ],
            ];
        }

        // Validate headline - reject if too short or boring
        $boringHeadlines = ['nowa', 'new', 'sale', 'nowość', 'oferta', 'promocja'];
        if (strlen($headline) < 15 || in_array(strtolower(trim($headline)), $boringHeadlines)) {
            return [
                'type' => 'plan_design_rejected',
                'data' => [
                    'success' => false,
                    'message' => "Headline '{$headline}' is too short or boring! Must be 4-8 creative words. Try again!",
                ],
            ];
        }

        // Validate language - reject English text for Polish users
        $expectedLanguage = $this->detectUserLanguage($template);
        if ($expectedLanguage === 'pl' && !$this->isTextInLanguage($headline, 'pl')) {
            Log::channel('single')->warning('Headline in wrong language - rejecting', [
                'headline' => $headline,
                'expected_language' => $expectedLanguage,
            ]);

            return [
                'type' => 'plan_design_rejected',
                'data' => [
                    'success' => false,
                    'message' => "Headline must be in Polish! '{$headline}' appears to be in English. Rewrite in Polish, e.g., 'Odkryj Nową Erę Twojego Stylu'",
                    'expected_language' => 'Polish',
                ],
            ];
        }

        // Record this archetype as used
        $this->layoutTracker->recordLayoutUsage($template->brand, $compositionArchetype);

        // Get archetype constraints
        $archetype = $this->compositionArchetypeService->getArchetype($compositionArchetype);
        $archetypePrompt = $this->compositionArchetypeService->getArchetypeForPrompt($compositionArchetype);

        // Store the approved design plan
        $this->designPlanApproved = true;
        $this->currentDesignPlan = [
            'layout_description' => $layoutDescription,
            'composition_archetype' => $compositionArchetype,
            'archetype_definition' => $archetype,
            'color_scheme' => $colorScheme,
            'headline' => $headline,
            'subtext' => $subtext,
            'cta_text' => $ctaText,
        ];

        return [
            'type' => 'plan_design_approved',
            'data' => [
                'success' => true,
                'message' => "Creative plan approved! Archetype: {$archetype['name']}. Now create a unique template based on your vision: {$layoutDescription}",
                'plan' => $this->currentDesignPlan,
                'archetype_constraints' => $archetypePrompt,
            ],
        ];
    }

    /**
     * Build image constraints for AI prompt based on image analysis.
     */
    protected function buildImageConstraints(array $imageAnalysis): string
    {
        if (empty($imageAnalysis) || !($imageAnalysis['success'] ?? false)) {
            return '';
        }

        $busyZones = $imageAnalysis['busy_zones'] ?? [];
        $safeZones = $imageAnalysis['safe_zones'] ?? [];
        $suggestedPosition = $imageAnalysis['suggested_text_position'] ?? 'bottom';
        $colors = $imageAnalysis['colors'] ?? [];

        $constraints = "IMAGE ANALYSIS CONSTRAINTS:\n";
        $constraints .= "- Suggested text position: {$suggestedPosition}\n";

        // Forbidden zones (where the subject is)
        foreach ($busyZones as $zone) {
            $constraints .= "- FORBIDDEN ZONE (contains main subject): ";
            $constraints .= "x:{$zone['x']}-" . ($zone['x'] + $zone['width']) . ", ";
            $constraints .= "y:{$zone['y']}-" . ($zone['y'] + $zone['height']) . "\n";
        }

        // Safe zones for text
        $constraints .= "- SAFE ZONES for text:\n";
        foreach ($safeZones as $zone) {
            $constraints .= "  * {$zone['position']}: x:{$zone['x']}, y:{$zone['y']}, ";
            $constraints .= "w:{$zone['width']}, h:{$zone['height']}\n";
        }

        // Image-extracted colors for accents
        if (!empty($colors['accent_candidates'])) {
            $constraints .= "\nIMAGE-EXTRACTED COLORS (harmonize with photo):\n";
            foreach ($colors['accent_candidates'] as $idx => $color) {
                $constraints .= "- Accent option " . ($idx + 1) . ": {$color}\n";
            }
            $constraints .= "Use these colors for decorative accents/lines to create visual harmony.\n";
        }

        return $constraints;
    }

    /**
     * Apply archetype constraints to layers (ensure text is within text zone, etc.).
     */
    protected function applyArchetypeConstraints(array $layers, string $archetypeName, int $templateWidth, int $templateHeight): array
    {
        $archetype = $this->compositionArchetypeService->getArchetype($archetypeName);
        $textZone = $archetype['text_zone'];
        $photoZone = $archetype['photo_zone'];

        Log::channel('single')->info('Applying archetype constraints', [
            'archetype' => $archetypeName,
            'text_zone' => $textZone,
            'photo_zone' => $photoZone,
        ]);

        $fixedLayers = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            $name = strtolower($layer['name'] ?? '');

            // Text layers should be within text zone
            if (in_array($type, ['text', 'textbox'])) {
                // Check if layer is outside text zone
                $layerX = $layer['x'] ?? 0;
                $layerY = $layer['y'] ?? 0;
                $layerWidth = $layer['width'] ?? 200;

                // Move text into text zone if it's significantly outside
                $textZoneRight = $textZone['x'] + $textZone['width'];
                $textZoneBottom = $textZone['y'] + $textZone['height'];

                // If headline or subtext is outside text zone, move it
                if (str_contains($name, 'headline') || str_contains($name, 'title') || str_contains($name, 'subtext') || str_contains($name, 'desc')) {
                    // Ensure X is within text zone
                    if ($layerX < $textZone['x'] || $layerX > $textZoneRight) {
                        $layer['x'] = $textZone['x'];
                        Log::channel('single')->debug("Moved {$name} X to text zone", ['new_x' => $layer['x']]);
                    }

                    // Ensure width fits text zone
                    if ($layerWidth > $textZone['width']) {
                        $layer['width'] = $textZone['width'];
                    }

                    // Ensure Y is within text zone
                    if ($layerY < $textZone['y'] || $layerY > $textZoneBottom) {
                        // Headlines go at top of text zone, subtext below
                        if (str_contains($name, 'headline') || str_contains($name, 'title')) {
                            $layer['y'] = $textZone['y'];
                        } else {
                            $layer['y'] = $textZone['y'] + 100; // Offset for subtext
                        }
                        Log::channel('single')->debug("Moved {$name} Y to text zone", ['new_y' => $layer['y']]);
                    }
                }

                // CTA button positioning based on archetype
                if (str_contains($name, 'cta') || str_contains($name, 'button') || $type === 'textbox') {
                    $ctaPos = $this->compositionArchetypeService->getCtaPosition($archetypeName, $templateWidth, $templateHeight);
                    $layer['x'] = (int) $ctaPos['x'];
                    $layer['y'] = (int) $ctaPos['y'];
                }
            }

            // Image layers should fit photo zone
            if ($type === 'image') {
                // Apply photo zone dimensions
                if (!str_contains($name, 'background')) {
                    $layer['x'] = $photoZone['x'];
                    $layer['y'] = $photoZone['y'];
                    $layer['width'] = $photoZone['width'];
                    $layer['height'] = $photoZone['height'];
                }
            }

            $fixedLayers[] = $layer;
        }

        // Add overlay if archetype requires it
        if ($this->compositionArchetypeService->requiresOverlay($archetypeName)) {
            $hasOverlay = false;
            foreach ($fixedLayers as $layer) {
                $name = strtolower($layer['name'] ?? '');
                if (str_contains($name, 'overlay')) {
                    $hasOverlay = true;
                    break;
                }
            }

            if (!$hasOverlay) {
                $overlayOpacity = $archetype['overlay_opacity'] ?? 0.5;
                // Insert overlay after photo, before text
                $overlayLayer = [
                    'name' => 'overlay',
                    'type' => 'rectangle',
                    'x' => 0,
                    'y' => 0,
                    'width' => $templateWidth,
                    'height' => $templateHeight,
                    'properties' => [
                        'fill' => '#000000',
                        'opacity' => $overlayOpacity,
                    ],
                ];

                // Find position to insert (after image layers)
                $insertPos = 1;
                foreach ($fixedLayers as $idx => $layer) {
                    if (($layer['type'] ?? '') === 'image') {
                        $insertPos = $idx + 1;
                    }
                }

                array_splice($fixedLayers, $insertPos, 0, [$overlayLayer]);
                Log::channel('single')->info('Added required overlay for archetype', ['opacity' => $overlayOpacity]);
            }
        }

        return $fixedLayers;
    }

    /**
     * Handle create_full_template - creates a complete template with multiple layers.
     */
    protected function handleCreateFullTemplate(array $arguments, Template $template): array
    {
        $pipelineStartTime = microtime(true);

        Log::channel('single')->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel('single')->info('║           CREATE FULL TEMPLATE - PIPELINE START                   ║');
        Log::channel('single')->info('╚══════════════════════════════════════════════════════════════════╝');

        Log::channel('single')->info('[STEP 0] RAW INPUT FROM AI', [
            'template_settings' => $arguments['template_settings'] ?? [],
            'layers_count' => count($arguments['layers'] ?? []),
            'image_searches_count' => count($arguments['image_searches'] ?? []),
            'raw_layers_json' => json_encode($arguments['layers'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);

        $actions = [];
        $templateSettings = $arguments['template_settings'] ?? [];
        $layers = $arguments['layers'] ?? [];
        $imageSearches = $arguments['image_searches'] ?? [];

        // Log each layer in detail
        Log::channel('single')->info('[STEP 0.1] DETAILED LAYER INSPECTION');
        foreach ($layers as $idx => $layer) {
            $layerName = $layer['name'] ?? 'unnamed';
            $layerType = $layer['type'] ?? 'unknown';
            $hasProperties = !empty($layer['properties']);
            $properties = $layer['properties'] ?? [];

            // Check for common issues
            $issues = [];
            if (!isset($layer['x']) || !isset($layer['y'])) {
                $issues[] = 'MISSING_POSITION';
            }
            if (!isset($layer['width']) || !isset($layer['height'])) {
                $issues[] = 'MISSING_DIMENSIONS';
            }
            if (!$hasProperties) {
                $issues[] = 'EMPTY_PROPERTIES';
            }
            if (in_array($layerType, ['text', 'textbox']) && empty($properties['text'])) {
                $issues[] = 'TEXT_LAYER_NO_TEXT';
            }
            if (in_array($layerType, ['text', 'textbox']) && empty($properties['fontSize'])) {
                $issues[] = 'TEXT_LAYER_NO_FONTSIZE';
            }
            if ($layerType === 'image' && empty($properties['src']) && empty($properties['fit'])) {
                $issues[] = 'IMAGE_NO_FIT_OR_SRC';
            }

            Log::channel('single')->info("  └─ Layer #{$idx}: {$layerName} ({$layerType})", [
                'position' => ['x' => $layer['x'] ?? null, 'y' => $layer['y'] ?? null],
                'dimensions' => ['w' => $layer['width'] ?? null, 'h' => $layer['height'] ?? null],
                'has_properties' => $hasProperties,
                'properties_keys' => array_keys($properties),
                'issues' => $issues,
                'full_properties' => $properties,
            ]);
        }

        // Use current template dimensions as default (don't change unless explicitly requested)
        $currentWidth = $template->width;
        $currentHeight = $template->height;

        // Only update template settings if explicitly provided AND different from current
        if (! empty($templateSettings)) {
            $changes = [];

            // Only include width/height if explicitly provided and different
            if (isset($templateSettings['width']) && $templateSettings['width'] != $currentWidth) {
                $changes['width'] = $templateSettings['width'];
            }
            if (isset($templateSettings['height']) && $templateSettings['height'] != $currentHeight) {
                $changes['height'] = $templateSettings['height'];
            }
            // Background color can always be updated
            if (isset($templateSettings['background_color'])) {
                $changes['background_color'] = $templateSettings['background_color'];
            }

            if (! empty($changes)) {
                $actions[] = [
                    'type' => 'update_template',
                    'data' => [
                        'changes' => $changes,
                    ],
                ];
            }
        }

        // STEP 1: Search for images with premium queries and analyze them
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 1] IMAGE SEARCH & ANALYSIS');

        $imageUrls = [];
        $imageAnalysis = [];
        $industry = $template->brand?->industry;

        Log::channel('single')->info('[STEP 1.0] Image search config', [
            'searches_count' => count($imageSearches),
            'industry' => $industry,
            'template_dimensions' => "{$currentWidth}x{$currentHeight}",
            'image_analysis_available' => $this->imageAnalysisService->isAvailable(),
        ]);

        foreach ($imageSearches as $searchIdx => $search) {
            $layerName = $search['layer_name'] ?? '';
            $query = $search['search_query'] ?? '';
            $orientation = $search['orientation'] ?? null;

            Log::channel('single')->info("[STEP 1.{$searchIdx}] Processing image search", [
                'layer_name' => $layerName,
                'original_query' => $query,
                'orientation' => $orientation,
            ]);

            if ($query && $layerName) {
                // Auto-detect industry from query if not set
                $effectiveIndustry = $industry ?? $this->premiumQueryBuilder->detectIndustryFromQuery($query);

                // Build premium query with industry modifiers and composition hints
                $premiumQuery = $this->premiumQueryBuilder->buildQueryWithComposition(
                    $query,
                    $this->currentDesignPlan['composition_archetype'] ?? 'centered_minimal',
                    $effectiveIndustry,
                    $this->premiumQueryBuilder->getSuggestedLighting($effectiveIndustry)
                );

                Log::channel('single')->info("[STEP 1.{$searchIdx}] Premium query built", [
                    'original' => $query,
                    'premium' => $premiumQuery,
                    'archetype' => $this->currentDesignPlan['composition_archetype'] ?? 'centered_minimal',
                    'brand_industry' => $industry,
                    'detected_industry' => $effectiveIndustry,
                ]);

                $result = $this->stockPhotoService->searchPhotos($premiumQuery, 5, $orientation);

                Log::channel('single')->info("[STEP 1.{$searchIdx}] Stock photo search result (premium)", [
                    'success' => $result['success'],
                    'photos_found' => count($result['photos'] ?? []),
                    'error' => $result['error'] ?? null,
                ]);

                // Fallback to original query if premium query returns no results
                if (!$result['success'] || empty($result['photos'])) {
                    Log::channel('single')->warning("[STEP 1.{$searchIdx}] Premium query failed, trying original");
                    $result = $this->stockPhotoService->searchPhotos($query, 5, $orientation);

                    Log::channel('single')->info("[STEP 1.{$searchIdx}] Stock photo search result (fallback)", [
                        'success' => $result['success'],
                        'photos_found' => count($result['photos'] ?? []),
                        'error' => $result['error'] ?? null,
                    ]);
                }

                if ($result['success'] && !empty($result['photos'])) {
                    // Select best photo using PhotoRankingService
                    $photo = $this->selectBestPhotoForTemplate(
                        $result['photos'],
                        $orientation,
                        $currentWidth,
                        $currentHeight,
                        $this->currentDesignPlan['composition_archetype'] ?? null
                    );
                    $imageUrl = $this->stockPhotoService->getBestImageUrl($photo, $currentWidth, $currentHeight);
                    $imageUrls[$layerName] = $imageUrl;

                    Log::channel('single')->info("[STEP 1.{$searchIdx}] Photo selected", [
                        'photo_id' => $photo['id'] ?? 'unknown',
                        'photographer' => $photo['photographer'] ?? 'unknown',
                        'dimensions' => ($photo['width'] ?? 0) . 'x' . ($photo['height'] ?? 0),
                        'image_url' => substr($imageUrl, 0, 100) . '...',
                    ]);

                    // Analyze the image for composition
                    if ($this->imageAnalysisService->isAvailable()) {
                        Log::channel('single')->info("[STEP 1.{$searchIdx}] Analyzing image...");
                        $imageAnalysis[$layerName] = $this->imageAnalysisService->analyzeImage(
                            $imageUrl,
                            $currentWidth,
                            $currentHeight
                        );

                        Log::channel('single')->info("[STEP 1.{$searchIdx}] Image analysis result", [
                            'success' => $imageAnalysis[$layerName]['success'] ?? false,
                            'busy_zones_count' => count($imageAnalysis[$layerName]['busy_zones'] ?? []),
                            'safe_zones_count' => count($imageAnalysis[$layerName]['safe_zones'] ?? []),
                            'suggested_text_position' => $imageAnalysis[$layerName]['suggested_text_position'] ?? 'unknown',
                            'colors' => $imageAnalysis[$layerName]['colors'] ?? [],
                        ]);
                    }
                } else {
                    Log::channel('single')->error("[STEP 1.{$searchIdx}] NO PHOTOS FOUND for layer: {$layerName}");
                }
            }
        }

        Log::channel('single')->info('[STEP 1] Image search complete', [
            'images_found' => count($imageUrls),
            'layers_with_images' => array_keys($imageUrls),
        ]);

        // STEP 2: Apply Grid Snap (8pt grid)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 2] GRID SNAP (8pt grid)');
        $layersBeforeGridSnap = json_encode($this->extractLayerPositions($layers));
        $layers = $this->gridSnapService->snapAllLayers($layers);
        $layersAfterGridSnap = json_encode($this->extractLayerPositions($layers));
        Log::channel('single')->info('[STEP 2] Grid snap applied', [
            'before' => $layersBeforeGridSnap,
            'after' => $layersAfterGridSnap,
            'changed' => $layersBeforeGridSnap !== $layersAfterGridSnap,
        ]);

        // STEP 3: Apply Design Tokens (modular scale for fonts, etc.)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 3] DESIGN TOKENS (modular scale)');
        $fontSizesBefore = $this->extractFontSizes($layers);
        $layers = $this->designTokensService->snapAllLayersToTokens($layers);
        $fontSizesAfter = $this->extractFontSizes($layers);
        Log::channel('single')->info('[STEP 3] Design tokens applied', [
            'font_sizes_before' => $fontSizesBefore,
            'font_sizes_after' => $fontSizesAfter,
        ]);

        // STEP 3.5: Apply Vertical Rhythm & Tracking (Phase 3.2)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 3.5] VERTICAL RHYTHM & TRACKING');
        $typoBefore = $this->extractTypographyDetails($layers);
        $layers = $this->designTokensService->applyVerticalRhythmToLayers($layers);
        $typoAfter = $this->extractTypographyDetails($layers);
        Log::channel('single')->info('[STEP 3.5] Vertical rhythm applied', [
            'before' => $typoBefore,
            'after' => $typoAfter,
        ]);

        // STEP 4: Validate and fix layers using TemplateValidator
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 4] TEMPLATE VALIDATOR');
        $layersBeforeValidation = count($layers);
        $layers = $this->templateValidator->validateAndFix($layers, $currentWidth);
        Log::channel('single')->info('[STEP 4] Template validation complete', [
            'layers_before' => $layersBeforeValidation,
            'layers_after' => count($layers),
        ]);

        // STEP 5: Fix typography hierarchy
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 5] TYPOGRAPHY HIERARCHY');
        $fontSizesBeforeTypo = $this->extractFontSizes($layers);
        $layers = $this->typographyValidator->fixHierarchy($layers);
        $fontSizesAfterTypo = $this->extractFontSizes($layers);
        Log::channel('single')->info('[STEP 5] Typography hierarchy fixed', [
            'font_sizes_before' => $fontSizesBeforeTypo,
            'font_sizes_after' => $fontSizesAfterTypo,
        ]);

        // STEP 6: Fix contrast issues
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 6] CONTRAST VALIDATION');
        $backgroundColor = $templateSettings['background_color'] ?? '#FFFFFF';
        $colorsBeforeContrast = $this->extractTextColors($layers);
        $layers = $this->contrastValidator->fixContrastIssues($layers, $backgroundColor);
        $colorsAfterContrast = $this->extractTextColors($layers);
        Log::channel('single')->info('[STEP 6] Contrast validation complete', [
            'background_color' => $backgroundColor,
            'colors_before' => $colorsBeforeContrast,
            'colors_after' => $colorsAfterContrast,
        ]);

        // STEP 7: Check completeness - auto-add missing required elements
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 7] COMPLETENESS CHECK');
        $missing = $this->templateValidator->checkCompleteness($layers, $currentWidth, $currentHeight);
        Log::channel('single')->info('[STEP 7] Completeness check result', [
            'missing_elements' => $missing,
            'layers_before' => count($layers),
        ]);

        if (!empty($missing)) {
            Log::channel('single')->warning('[STEP 7] Template incomplete, auto-adding elements', [
                'missing' => $missing,
            ]);
            $layers = $this->templateValidator->addMissingElements(
                $layers,
                $missing,
                $currentWidth,
                $currentHeight,
                $this->currentDesignPlan
            );
            Log::channel('single')->info('[STEP 7] Elements added', [
                'layers_after' => count($layers),
            ]);
        }

        // STEP 7.5: Apply archetype constraints to layers
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 7.5] ARCHETYPE CONSTRAINTS');
        if ($this->currentDesignPlan && isset($this->currentDesignPlan['composition_archetype'])) {
            $archetype = $this->currentDesignPlan['composition_archetype'];
            $positionsBefore = $this->extractLayerPositions($layers);
            $layers = $this->applyArchetypeConstraints(
                $layers,
                $archetype,
                $currentWidth,
                $currentHeight
            );
            $positionsAfter = $this->extractLayerPositions($layers);
            Log::channel('single')->info('[STEP 7.5] Archetype constraints applied', [
                'archetype' => $archetype,
                'positions_before' => $positionsBefore,
                'positions_after' => $positionsAfter,
            ]);
        } else {
            Log::channel('single')->info('[STEP 7.5] No design plan - skipping archetype constraints');
        }

        // STEP 8: Adjust layers based on image analysis (avoid focal point)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 8] IMAGE ANALYSIS ADJUSTMENTS');
        if (!empty($imageAnalysis)) {
            $primaryAnalysis = reset($imageAnalysis);
            if ($primaryAnalysis && ($primaryAnalysis['success'] ?? false)) {
                $positionsBefore = $this->extractLayerPositions($layers);
                $layers = $this->imageAnalysisService->adjustLayersToAnalysis($layers, $primaryAnalysis);
                $positionsAfter = $this->extractLayerPositions($layers);
                Log::channel('single')->info('[STEP 8] Layers adjusted for image composition', [
                    'busy_zones' => $primaryAnalysis['busy_zones'] ?? [],
                    'positions_before' => $positionsBefore,
                    'positions_after' => $positionsAfter,
                ]);
            } else {
                Log::channel('single')->info('[STEP 8] Image analysis unsuccessful - skipping');
            }
        } else {
            Log::channel('single')->info('[STEP 8] No image analysis data - skipping');
        }

        // STEP 9: Self-correction pass (final review)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 9] SELF-CORRECTION PASS');
        $primaryAnalysis = !empty($imageAnalysis) ? reset($imageAnalysis) : [];
        $correctionResult = $this->selfCorrectionService->reviewAndCorrect(
            $layers,
            $primaryAnalysis,
            $currentWidth,
            $currentHeight
        );
        $layers = $correctionResult['layers'];

        Log::channel('single')->info('[STEP 9] Self-correction result', [
            'corrections_applied' => $correctionResult['corrections_applied'],
            'corrections' => $correctionResult['corrections'] ?? [],
        ]);

        // STEP 9.5: Apply Elevation Shadows (Phase 3.3 - Multi-layer Shadow Physics)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 9.5] ELEVATION SHADOWS');
        $shadowsBefore = $this->extractShadowInfo($layers);
        $layers = $this->elevationService->applyElevationToLayers($layers);
        $shadowsAfter = $this->extractShadowInfo($layers);
        Log::channel('single')->info('[STEP 9.5] Elevation shadows applied', [
            'shadows_before' => $shadowsBefore,
            'shadows_after' => $shadowsAfter,
        ]);

        // STEP 10: Final grid snap to ensure everything is aligned
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 10] FINAL GRID SNAP');
        $positionsBefore = $this->extractLayerPositions($layers);
        $layers = $this->gridSnapService->snapAllLayers($layers);
        $positionsAfter = $this->extractLayerPositions($layers);
        Log::channel('single')->info('[STEP 10] Final grid snap complete', [
            'positions_before' => $positionsBefore,
            'positions_after' => $positionsAfter,
        ]);

        // STEP 11: Sort layers by z-order (background first, CTA last)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 11] LAYER SORTING (z-order)');
        $orderBefore = array_map(fn($l) => $l['name'] ?? 'unnamed', $layers);
        $layers = $this->templateValidator->sortLayersByZOrder($layers);
        $orderAfter = array_map(fn($l) => $l['name'] ?? 'unnamed', $layers);
        Log::channel('single')->info('[STEP 11] Layer order sorted', [
            'order_before' => $orderBefore,
            'order_after' => $orderAfter,
        ]);

        // STEP 12: Visual Critic Review with Retry Loop (Phase 3.1 - Premium Quality Check)
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 12] VISUAL CRITIC REVIEW (with retry loop)');

        $maxRetries = 2;
        $attempt = 0;
        $critique = null;

        do {
            $attempt++;

            $critique = $this->visualCriticService->critique(
                $layers,
                $primaryAnalysis,
                $currentWidth,
                $currentHeight
            );

            Log::channel('single')->info("[STEP 12] Visual Critic scores (attempt {$attempt})", [
                'passed' => $critique['passed'],
                'total_score' => $critique['total_score'],
                'scores' => $critique['scores'],
                'issues' => $critique['issues'],
                'suggestions' => $critique['suggestions'],
                'verdict' => $critique['verdict'],
            ]);

            if ($critique['passed']) {
                Log::channel('single')->info("[STEP 12] Visual Critic APPROVED design on attempt {$attempt}");
                break;
            }

            if ($attempt >= $maxRetries) {
                Log::channel('single')->warning("[STEP 12] Max retries reached ({$maxRetries}), proceeding with best effort");
                break;
            }

            Log::channel('single')->warning("[STEP 12] Visual Critic REJECTED design (attempt {$attempt}) - applying fixes and retrying");

            // Apply auto-fixes based on critique
            $layersBeforeFix = json_encode($layers, JSON_PRETTY_PRINT);
            $layers = $this->visualCriticService->applyFixes($layers, $critique, $currentWidth, $currentHeight);
            $layersAfterFix = json_encode($layers, JSON_PRETTY_PRINT);

            Log::channel('single')->info("[STEP 12] Fixes applied (attempt {$attempt})", [
                'layers_changed' => $layersBeforeFix !== $layersAfterFix,
            ]);

            // Re-run self-correction pass after fixes
            $correctionResult = $this->selfCorrectionService->reviewAndCorrect(
                $layers,
                $primaryAnalysis,
                $currentWidth,
                $currentHeight
            );
            $layers = $correctionResult['layers'];

            // Re-apply elevation shadows
            $layers = $this->elevationService->applyElevationToLayers($layers);

            // Final grid snap
            $layers = $this->gridSnapService->snapAllLayers($layers);

            // Re-sort after fixes
            $layers = $this->templateValidator->sortLayersByZOrder($layers);

        } while (true);

        // Log final attempt summary
        Log::channel('single')->info('[STEP 12] Producer-Critic loop complete', [
            'total_attempts' => $attempt,
            'final_score' => $critique['total_score'] ?? 0,
            'final_passed' => $critique['passed'] ?? false,
        ]);

        // STEP 13: Create final layer actions
        Log::channel('single')->info('────────────────────────────────────────────────────────────────────');
        Log::channel('single')->info('[STEP 13] FINAL LAYER CREATION');

        foreach ($layers as $idx => $layer) {
            $type = $layer['type'] ?? 'rectangle';
            $name = $layer['name'] ?? ucfirst($type);
            $properties = $layer['properties'] ?? [];

            // If this is an image layer and we have a URL for it, add the src
            if ($type === 'image' && isset($imageUrls[$name])) {
                $properties['src'] = $imageUrls[$name];
                Log::channel('single')->info("[STEP 13.{$idx}] Image src assigned", [
                    'layer_name' => $name,
                    'src' => substr($imageUrls[$name], 0, 80) . '...',
                ]);
            }

            $defaultProperties = $this->getDefaultLayerProperties($type, $properties);

            $layerAction = [
                'type' => 'add_layer',
                'data' => [
                    'name' => $name,
                    'type' => $type,
                    'x' => $layer['x'] ?? 0,
                    'y' => $layer['y'] ?? 0,
                    'width' => $layer['width'] ?? ($type === 'text' ? 300 : ($type === 'line' ? 150 : 200)),
                    'height' => $layer['height'] ?? ($type === 'text' ? 50 : ($type === 'line' ? 10 : 200)),
                    'rotation' => $layer['rotation'] ?? 0,
                    'visible' => true,
                    'locked' => false,
                    'properties' => $defaultProperties,
                ],
            ];

            $actions[] = $layerAction;

            Log::channel('single')->info("[STEP 13.{$idx}] Layer action created: {$name}", [
                'type' => $type,
                'position' => ['x' => $layerAction['data']['x'], 'y' => $layerAction['data']['y']],
                'dimensions' => ['w' => $layerAction['data']['width'], 'h' => $layerAction['data']['height']],
                'properties_keys' => array_keys($defaultProperties),
            ]);
        }

        // Pipeline complete
        $pipelineDuration = round((microtime(true) - $pipelineStartTime) * 1000, 2);

        Log::channel('single')->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel('single')->info('║           CREATE FULL TEMPLATE - PIPELINE COMPLETE               ║');
        Log::channel('single')->info('╚══════════════════════════════════════════════════════════════════╝');

        Log::channel('single')->info('[PIPELINE SUMMARY]', [
            'total_duration_ms' => $pipelineDuration,
            'total_actions' => count($actions),
            'layers_created' => count($layers),
            'images_found' => count($imageUrls),
            'visual_critic_passed' => $critique['passed'] ?? false,
            'visual_critic_score' => $critique['total_score'] ?? 0,
            'design_plan_used' => $this->currentDesignPlan !== null,
            'composition_archetype' => $this->currentDesignPlan['composition_archetype'] ?? 'none',
        ]);

        // Log final output JSON for debugging
        Log::channel('single')->info('[FINAL OUTPUT] Actions JSON', [
            'actions_json' => json_encode($actions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);

        return $actions;
    }

    /**
     * Extract layer positions for logging comparison.
     */
    protected function extractLayerPositions(array $layers): array
    {
        $positions = [];
        foreach ($layers as $layer) {
            $name = $layer['name'] ?? 'unnamed';
            $positions[$name] = [
                'x' => $layer['x'] ?? null,
                'y' => $layer['y'] ?? null,
                'w' => $layer['width'] ?? null,
                'h' => $layer['height'] ?? null,
            ];
        }
        return $positions;
    }

    /**
     * Extract font sizes from text layers for logging.
     */
    protected function extractFontSizes(array $layers): array
    {
        $fontSizes = [];
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            if (in_array($type, ['text', 'textbox'])) {
                $name = $layer['name'] ?? 'unnamed';
                $fontSizes[$name] = $layer['properties']['fontSize'] ?? null;
            }
        }
        return $fontSizes;
    }

    /**
     * Extract typography details from text layers for logging.
     */
    protected function extractTypographyDetails(array $layers): array
    {
        $typo = [];
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            if (in_array($type, ['text', 'textbox'])) {
                $name = $layer['name'] ?? 'unnamed';
                $props = $layer['properties'] ?? [];
                $typo[$name] = [
                    'fontSize' => $props['fontSize'] ?? null,
                    'lineHeight' => $props['lineHeight'] ?? null,
                    'letterSpacing' => $props['letterSpacing'] ?? null,
                ];
            }
        }
        return $typo;
    }

    /**
     * Extract text colors from layers for logging.
     */
    protected function extractTextColors(array $layers): array
    {
        $colors = [];
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? '';
            if (in_array($type, ['text', 'textbox'])) {
                $name = $layer['name'] ?? 'unnamed';
                $props = $layer['properties'] ?? [];
                $colors[$name] = [
                    'fill' => $props['fill'] ?? null,
                    'textColor' => $props['textColor'] ?? null,
                ];
            }
        }
        return $colors;
    }

    /**
     * Extract shadow information from layers for logging.
     */
    protected function extractShadowInfo(array $layers): array
    {
        $shadows = [];
        foreach ($layers as $layer) {
            $name = $layer['name'] ?? 'unnamed';
            $props = $layer['properties'] ?? [];
            if (isset($props['shadowEnabled'])) {
                $shadows[$name] = [
                    'enabled' => $props['shadowEnabled'],
                    'blur' => $props['shadowBlur'] ?? null,
                    'offsetY' => $props['shadowOffsetY'] ?? null,
                    'opacity' => $props['shadowOpacity'] ?? null,
                ];
            }
        }
        return $shadows;
    }

    /**
     * Select the best photo for template composition.
     * Uses PhotoRankingService to score and rank photos based on composition quality.
     */
    protected function selectBestPhotoForTemplate(
        array $photos,
        ?string $orientation,
        int $targetWidth = 1080,
        int $targetHeight = 1080,
        ?string $archetype = null
    ): array {
        if (empty($photos)) {
            return [];
        }

        // Filter by orientation first if specified
        if ($orientation) {
            $orientedPhotos = array_filter($photos, function ($photo) use ($orientation) {
                return $this->getPhotoOrientation($photo) === $orientation;
            });

            if (!empty($orientedPhotos)) {
                $photos = array_values($orientedPhotos);
            }
        }

        // Use PhotoRankingService for intelligent selection
        $archetype = $archetype ?? $this->currentDesignPlan['composition_archetype'] ?? 'centered_minimal';

        $bestPhoto = $this->photoRankingService->getBestPhoto(
            $photos,
            $archetype,
            $targetWidth,
            $targetHeight
        );

        if ($bestPhoto) {
            Log::channel('single')->info('PhotoRankingService selected best photo', [
                'photo_id' => $bestPhoto['id'] ?? 'unknown',
                'archetype' => $archetype,
            ]);

            return $bestPhoto;
        }

        // Fallback to first photo if ranking fails
        return $photos[0];
    }

    /**
     * Determine photo orientation based on dimensions.
     */
    protected function getPhotoOrientation(array $photo): string
    {
        $width = $photo['width'] ?? 0;
        $height = $photo['height'] ?? 0;

        if ($width > $height * 1.2) {
            return 'landscape';
        } elseif ($height > $width * 1.2) {
            return 'portrait';
        }

        return 'square';
    }

    /**
     * Handle search_images - search for stock photos on Pexels.
     */
    protected function handleSearchImages(array $arguments, Template $template): array
    {
        $query = $arguments['query'] ?? '';
        $orientation = $arguments['orientation'] ?? null;
        $count = min($arguments['count'] ?? 3, 5);

        if (empty($query)) {
            return [
                'type' => 'search_images_result',
                'data' => [
                    'success' => false,
                    'error' => 'Search query is required',
                    'images' => [],
                ],
            ];
        }

        $result = $this->stockPhotoService->searchPhotos($query, $count, $orientation);

        $images = [];
        if ($result['success']) {
            foreach ($result['photos'] as $photo) {
                $images[] = [
                    'id' => $photo['id'],
                    'url' => $photo['src']['large'] ?? $photo['src']['original'],
                    'thumbnail' => $photo['src']['small'] ?? $photo['src']['tiny'],
                    'photographer' => $photo['photographer'],
                    'alt' => $photo['alt'],
                    'width' => $photo['width'],
                    'height' => $photo['height'],
                ];
            }
        }

        return [
            'type' => 'search_images_result',
            'data' => [
                'success' => $result['success'],
                'query' => $query,
                'images' => $images,
                'message' => $result['success']
                    ? 'Found '.count($images).' images for "'.$query.'"'
                    : ($result['error'] ?? 'Search failed'),
            ],
        ];
    }

    /**
     * Handle modify_layer tool call.
     */
    protected function handleModifyLayer(array $arguments, Template $template): ?array
    {
        $layerName = $arguments['layer_name'] ?? null;
        $changes = $arguments['changes'] ?? [];

        if (! $layerName) {
            return null;
        }

        // Find layer by name
        $layer = $template->layers()->where('name', $layerName)->first();

        if (! $layer) {
            return [
                'type' => 'error',
                'message' => "Layer '{$layerName}' not found",
            ];
        }

        // Prepare changes for frontend
        $layerChanges = [];

        // Handle position/dimension changes
        foreach (['x', 'y', 'width', 'height', 'rotation', 'visible'] as $prop) {
            if (isset($changes[$prop])) {
                $layerChanges[$prop] = $changes[$prop];
            }
        }

        // Handle properties changes (nested)
        if (isset($changes['properties'])) {
            $layerChanges['properties'] = array_merge(
                $layer->properties ?? [],
                $changes['properties']
            );
        }

        return [
            'type' => 'modify_layer',
            'data' => [
                'layerId' => $layer->public_id,
                'layerName' => $layerName,
                'changes' => $layerChanges,
            ],
        ];
    }

    /**
     * Handle add_layer tool call.
     */
    protected function handleAddLayer(array $arguments, Template $template): array
    {
        $type = $arguments['type'] ?? 'rectangle';
        $name = $arguments['name'] ?? ucfirst($type);

        // Get default properties for the layer type
        $defaultProperties = $this->getDefaultLayerProperties($type, $arguments['properties'] ?? []);

        return [
            'type' => 'add_layer',
            'data' => [
                'name' => $name,
                'type' => $type,
                'x' => $arguments['x'] ?? 100,
                'y' => $arguments['y'] ?? 100,
                'width' => $arguments['width'] ?? ($type === 'text' ? 300 : ($type === 'line' ? 150 : 200)),
                'height' => $arguments['height'] ?? ($type === 'text' ? 50 : ($type === 'line' ? 10 : 200)),
                'visible' => true,
                'locked' => false,
                'properties' => $defaultProperties,
            ],
        ];
    }

    /**
     * Handle delete_layer tool call.
     */
    protected function handleDeleteLayer(array $arguments, Template $template): ?array
    {
        $layerName = $arguments['layer_name'] ?? null;

        if (! $layerName) {
            return null;
        }

        $layer = $template->layers()->where('name', $layerName)->first();

        if (! $layer) {
            return [
                'type' => 'error',
                'message' => "Layer '{$layerName}' not found",
            ];
        }

        return [
            'type' => 'delete_layer',
            'data' => [
                'layerId' => $layer->public_id,
                'layerName' => $layerName,
            ],
        ];
    }

    /**
     * Handle update_template tool call.
     */
    protected function handleUpdateTemplate(array $arguments, Template $template): array
    {
        $changes = [];

        if (isset($arguments['background_color'])) {
            $changes['background_color'] = $arguments['background_color'];
        }

        if (isset($arguments['width'])) {
            $changes['width'] = $arguments['width'];
        }

        if (isset($arguments['height'])) {
            $changes['height'] = $arguments['height'];
        }

        return [
            'type' => 'update_template',
            'data' => [
                'changes' => $changes,
            ],
        ];
    }

    /**
     * Handle get_api_info tool call.
     */
    protected function handleGetApiInfo(array $arguments): array
    {
        $topic = $arguments['topic'] ?? 'general';
        $apiDocs = $this->contextService->getApiDocumentation();

        return [
            'type' => 'api_info',
            'data' => [
                'topic' => $topic,
                'documentation' => $apiDocs,
            ],
        ];
    }

    /**
     * Get default properties for a layer type.
     */
    protected function getDefaultLayerProperties(string $type, array $customProperties = []): array
    {
        // For text layers, warn if no text content was provided
        if ($type === 'text' && empty($customProperties['text'])) {
            Log::channel('single')->warning('AI generated text layer without text content', [
                'customProperties' => $customProperties,
            ]);
        }

        // Calculate optimal line height and tracking for text layers
        $fontSize = $customProperties['fontSize'] ?? 24;
        $lineHeight = $customProperties['lineHeight']
            ?? $this->designTokensService->calculateLineHeight($fontSize, 'body_normal');
        $tracking = $customProperties['letterSpacing']
            ?? round($this->designTokensService->calculateTracking($fontSize) * $fontSize, 1);

        // Get elevation shadow for CTA buttons
        $ctaShadow = $this->elevationService->getShadowForElevation(3);

        $defaults = match ($type) {
            'text' => [
                'text' => $customProperties['text'] ?? '[Text content needed]',
                'fontFamily' => $customProperties['fontFamily'] ?? 'Montserrat',
                'fontSize' => $fontSize,
                'fontWeight' => $customProperties['fontWeight'] ?? 'normal',
                'fontStyle' => $customProperties['fontStyle'] ?? 'normal',
                'fill' => $customProperties['fill'] ?? '#000000',
                'align' => $customProperties['align'] ?? 'left',
                'verticalAlign' => $customProperties['verticalAlign'] ?? 'top',
                'lineHeight' => $lineHeight,
                'letterSpacing' => $tracking,
            ],
            'textbox' => [
                'text' => $customProperties['text'] ?? 'Button',
                'fontFamily' => $customProperties['fontFamily'] ?? 'Montserrat',
                'fontSize' => $customProperties['fontSize'] ?? 16,
                'fontWeight' => $customProperties['fontWeight'] ?? '600',
                'fontStyle' => $customProperties['fontStyle'] ?? 'normal',
                'fill' => $customProperties['fill'] ?? '#3B82F6',
                'textColor' => $customProperties['textColor'] ?? '#FFFFFF',
                'align' => $customProperties['align'] ?? 'center',
                'padding' => $customProperties['padding'] ?? 16,
                'cornerRadius' => $customProperties['cornerRadius'] ?? 500, // Pill shape for premium
                'lineHeight' => $customProperties['lineHeight'] ?? 1.1, // Tight for buttons
                // Apply elevation shadow for floating CTA effect
                'shadowEnabled' => $customProperties['shadowEnabled'] ?? $ctaShadow['shadowEnabled'],
                'shadowColor' => $customProperties['shadowColor'] ?? $ctaShadow['shadowColor'],
                'shadowBlur' => $customProperties['shadowBlur'] ?? $ctaShadow['shadowBlur'],
                'shadowOffsetX' => $customProperties['shadowOffsetX'] ?? $ctaShadow['shadowOffsetX'],
                'shadowOffsetY' => $customProperties['shadowOffsetY'] ?? $ctaShadow['shadowOffsetY'],
                'shadowOpacity' => $customProperties['shadowOpacity'] ?? $ctaShadow['shadowOpacity'],
            ],
            'image' => [
                'src' => $customProperties['src'] ?? null,
                'fit' => $customProperties['fit'] ?? 'cover',
            ],
            'rectangle' => [
                // For gradient overlays, preserve gradient properties and use transparent fill
                'fill' => isset($customProperties['fillType']) && $customProperties['fillType'] === 'gradient'
                    ? 'transparent'
                    : ($customProperties['fill'] ?? '#CCCCCC'),
                'fillType' => $customProperties['fillType'] ?? 'solid',
                'gradientStartColor' => $customProperties['gradientStartColor'] ?? null,
                'gradientEndColor' => $customProperties['gradientEndColor'] ?? null,
                'gradientAngle' => $customProperties['gradientAngle'] ?? null,
                'stroke' => $customProperties['stroke'] ?? null,
                'strokeWidth' => $customProperties['strokeWidth'] ?? 0,
                'cornerRadius' => $customProperties['cornerRadius'] ?? 0,
                'opacity' => $customProperties['opacity'] ?? 1,
            ],
            'ellipse' => [
                'fill' => $customProperties['fill'] ?? '#CCCCCC',
                'stroke' => $customProperties['stroke'] ?? null,
                'strokeWidth' => $customProperties['strokeWidth'] ?? 0,
            ],
            'line' => [
                'points' => $customProperties['points'] ?? [0, 0, 100, 0],
                'stroke' => $customProperties['stroke'] ?? '#000000',
                'strokeWidth' => $customProperties['strokeWidth'] ?? 2,
                'lineCap' => $customProperties['lineCap'] ?? 'round',
                'lineJoin' => $customProperties['lineJoin'] ?? 'round',
                'dash' => $customProperties['dash'] ?? [],
            ],
            default => [],
        };

        // Merge custom properties over defaults
        return array_merge($defaults, $customProperties);
    }

    /**
     * Generate a summary of executed actions in the user's language.
     */
    protected function generateActionSummary(array $actions, Template $template): string
    {
        if (empty($actions)) {
            return '';
        }

        $summaries = [];
        $layersAdded = 0;
        $templateUpdated = false;
        $imagesFound = false;

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';

            if ($type === 'add_layer') {
                $layersAdded++;
            } elseif ($type === 'update_template') {
                $templateUpdated = true;
            } elseif ($type === 'search_images_result') {
                $imagesFound = true;
                if (! empty($action['data']['images'])) {
                    $summaries[] = $action['data']['message'];
                }
            } elseif ($type === 'modify_layer') {
                $summaries[] = $this->getModifyLayerSummary($action['data']);
            } elseif ($type === 'delete_layer') {
                $summaries[] = $this->getDeleteLayerSummary($action['data']);
            } elseif ($type === 'api_info') {
                return $action['data']['documentation'];
            } elseif ($type === 'plan_design_approved') {
                $archetype = $action['data']['plan']['composition_archetype'] ?? 'custom';
                $summaries[] = "Design plan approved: {$archetype} archetype";
            } elseif ($type === 'plan_design_rejected') {
                $summaries[] = $action['data']['message'] ?? 'Design plan rejected';
            } elseif ($type === 'error') {
                $summaries[] = $action['message'] ?? 'An error occurred';
            }
        }

        // Summary for bulk creation
        if ($layersAdded > 0) {
            $summaries[] = "Created {$layersAdded} layers";
        }

        if ($templateUpdated) {
            $summaries[] = 'Updated template settings';
        }

        return implode("\n", $summaries);
    }

    protected function getModifyLayerSummary(array $data): string
    {
        $layerName = $data['layerName'] ?? 'layer';
        $changes = $data['changes'] ?? [];

        $changedProps = [];

        if (isset($changes['properties'])) {
            foreach ($changes['properties'] as $key => $value) {
                if ($key === 'text') {
                    $changedProps[] = "text: \"{$value}\"";
                } elseif ($key === 'fontWeight' && $value === 'bold') {
                    $changedProps[] = 'bold';
                } elseif ($key === 'fontStyle' && $value === 'italic') {
                    $changedProps[] = 'italic';
                } elseif ($key === 'fill') {
                    $changedProps[] = "color: {$value}";
                } elseif ($key === 'fontSize') {
                    $changedProps[] = "font size: {$value}px";
                } elseif ($key === 'src') {
                    $changedProps[] = 'image updated';
                }
            }
        }

        $propsStr = ! empty($changedProps) ? ' ('.implode(', ', $changedProps).')' : '';

        return "Modified layer \"{$layerName}\"{$propsStr}";
    }

    protected function getDeleteLayerSummary(array $data): string
    {
        $layerName = $data['layerName'] ?? 'layer';

        return "Deleted layer \"{$layerName}\"";
    }
}
