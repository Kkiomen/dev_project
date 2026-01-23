<?php

namespace App\Services;

use App\Enums\LayerType;
use App\Models\Layer;
use App\Models\Template;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    public function __construct(
        protected OpenAiClientService $openAiClient,
        protected TemplateContextService $contextService,
        protected PexelsService $pexelsService
    ) {}

    /**
     * Process a chat message and return AI response with actions.
     */
    public function chat(Template $template, array $history, string $userMessage): array
    {
        Log::channel('single')->info('=== AI CHAT REQUEST ===', [
            'template_id' => $template->public_id,
            'template_name' => $template->name,
            'user_message' => $userMessage,
            'history_count' => count($history),
        ]);

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

        // Call OpenAI
        $response = $this->openAiClient->chatCompletion($messages, $tools);

        Log::channel('single')->info('=== OPENAI RAW RESPONSE ===', [
            'has_tool_calls' => ! empty($response->choices[0]->message->toolCalls),
            'content' => $response->choices[0]->message->content,
            'finish_reason' => $response->choices[0]->finishReason,
        ]);

        if (! empty($response->choices[0]->message->toolCalls)) {
            foreach ($response->choices[0]->message->toolCalls as $idx => $toolCall) {
                Log::channel('single')->info("=== TOOL CALL #{$idx} ===", [
                    'function_name' => $toolCall->function->name,
                    'arguments_raw' => $toolCall->function->arguments,
                    'arguments_decoded' => json_decode($toolCall->function->arguments, true),
                ]);
            }
        }

        // Process response
        return $this->processResponse($response, $template);
    }

    /**
     * Build the system prompt with template context.
     */
    protected function buildSystemPrompt(Template $template): string
    {
        $context = $this->contextService->getSimplifiedContext($template);
        $layerTypes = $this->contextService->getLayerTypeDefinitions();
        $pexelsAvailable = $this->pexelsService->isConfigured() ? 'YES' : 'NO';

        return <<<PROMPT
You are a professional graphic designer, UX/UI EXPERT, and MARKETING SPECIALIST AI assistant. You create STUNNING, MODERN, and VISUALLY RICH templates for social media, marketing, and personal branding.

Your design philosophy follows MODERN UX/UI BEST PRACTICES:
- **Visual Hierarchy**: Guide the eye with size, color, and spacing - most important elements are largest and most contrasting
- **Whitespace**: Strategic use of empty space for breathing room and focus
- **Contrast**: High contrast for readability (WCAG standards), accent colors for CTAs
- **Consistency**: Unified color palette (2-3 main colors + neutrals), consistent spacing
- **Mobile-first mindset**: Bold, readable text even on small screens
- **Micro-interactions feel**: Rounded corners, subtle shadows create depth
- **Modern aesthetics**: Clean lines, geometric shapes, contemporary color palettes

As a marketing expert, you understand:
- How to create scroll-stopping content that grabs attention
- Psychology of colors and their impact on conversion
- Effective CTAs (Call-To-Action) that drive engagement - contrasting colors, clear action words
- Visual storytelling and brand consistency
- Social media trends and best practices for each platform
- The 3-second rule: message must be clear within 3 seconds

CURRENT TEMPLATE CONTEXT:
{$context}

AVAILABLE LAYER TYPES AND PROPERTIES:
{$layerTypes}

PEXELS STOCK PHOTOS AVAILABLE: {$pexelsAvailable}

##############################################################################
# CRITICAL: RESPECT TEMPLATE DIMENSIONS
##############################################################################

The template has FIXED dimensions: check the "Dimensions:" in CURRENT TEMPLATE CONTEXT above.
DO NOT change width/height unless the user EXPLICITLY asks to resize.
All layers must fit within the current template dimensions.
Use the ACTUAL dimensions from the context, not default 1080x1080.

##############################################################################
# CRITICAL REQUIREMENT - PROPERTIES ARE MANDATORY FOR EVERY LAYER
##############################################################################

YOU MUST ALWAYS include the "properties" object for EVERY layer you create!
Without properties, layers will have ugly default colors and placeholder text.

FOR TEXT LAYERS - properties MUST include:
- "text": actual marketing copy (NEVER placeholders like "Headline" or "Text here")
- "fontSize": appropriate size in pixels (headlines: 40-72px, subtext: 18-32px, CTA: 20-28px)
- "fontWeight": "bold" for headlines, "normal" for body text
- "fill": hex color matching design palette
- "align": "center", "left", or "right"

FOR RECTANGLE/ELLIPSE LAYERS - properties MUST include:
- "fill": hex color (NEVER omit this - shapes need color!)
- "cornerRadius": for rectangles (0 for sharp, 10-30 for rounded)

FOR IMAGE LAYERS - properties MUST include:
- "fit": "cover" or "contain"

##############################################################################

MODERN DESIGN PRINCIPLES - FOLLOW THESE FOR EVERY TEMPLATE:
1. **Multi-layered depth** - minimum 8-12 layers: background, accent shapes, images, content boxes, text layers
2. **Strong visual hierarchy** - headline (largest, boldest), subtext (medium), CTA (contrasting color)
3. **Modern shadows** - soft, diffused shadows (blur: 20-40, opacity: 0.1-0.2) for floating effect
4. **Contemporary color schemes** - bold accent colors, dark or light backgrounds, high contrast CTAs
5. **Geometric accent elements** - circles, rounded rectangles with low opacity (0.2-0.5) for depth
6. **Modern typography** - large headlines (48-72px), generous letter-spacing (2-4), uppercase for impact
7. **Whitespace is key** - don't overcrowd, leave breathing room around elements
8. **Rounded corners everywhere** - cornerRadius 16-32 for modern feel
9. **Stock photos when relevant** - include image_searches for professional imagery

MODERN EFFECTS TOOLKIT:
- **Floating cards**: shadowEnabled: true, shadowColor: "#000000", shadowBlur: 30, shadowOffsetX: 0, shadowOffsetY: 15, shadowOpacity: 0.12
- **Glassmorphism-style boxes**: light fill (#FFFFFF or with opacity), strong rounded corners, subtle shadow
- **Bold gradients for impact**: fillType: "gradient" for backgrounds or CTAs - creates energy and movement
- **Accent blobs**: large ellipses with low opacity (0.2-0.4) partially off-canvas for modern aesthetic
- **Typography hierarchy**: headlines uppercase with letterSpacing: 3-5, body text normal
- **Contrasting CTA buttons**: bright/bold color that pops against background, rounded (cornerRadius: 25-35)
- **Thin accent lines**: height: 2-4px, accent color, as visual separators

IMPORTANT - CTA BUTTONS BEST PRACTICES:
When creating CTA buttons (rectangle + text combo), ALWAYS:
1. Make button width AT LEAST 60% of template width (e.g., 650px for 1080px template) to accommodate longer text
2. Button text and button background MUST have SAME x, width values
3. Use generous padding - button should be much wider than the text inside
4. Center text horizontally (align: "center")
5. Button height: 55-70px, text positioned vertically centered inside
Example: For template 1080px wide, CTA button should be width: 650-700px, centered at x: 190-215

DESIGN STYLES (DEFAULT IS MODERN - use others only when specifically requested):

1. **MODERN** (DEFAULT) - Contemporary, trendy, professional
   - Gradient backgrounds OR bold solid colors, rounded corners (16-32px), soft floating shadows
   - Clean sans-serif fonts, geometric accent shapes, high contrast CTAs
   - Color schemes: vibrant gradients, duotone, bold accent colors
   - This is the DEFAULT style - use it unless user asks for something specific!

2. **MINIMALIST** - Clean, lots of whitespace, simple typography
   - Few layers, large text, subtle neutral colors, no decorative shapes
   - Focus on content, maximum 6-8 layers
   - Good for: luxury, tech, professional services

3. **BOLD/VIBRANT** - Eye-catching, high contrast, energetic
   - Bright saturated colors, large bold text, dynamic overlapping shapes
   - Strong shadows, multiple accent elements
   - Good for: fitness, food, entertainment, sales, promotions

4. **ELEGANT/LUXURY** - Sophisticated, refined, premium feel
   - Dark backgrounds (#0A0A0A, #1A1A2E), gold/champagne accents (#D4AF37, #C9A961)
   - Thin elegant lines, serif fonts, minimal elements
   - Good for: fashion, beauty, jewelry, high-end products

5. **ORGANIC/NATURAL** - Earthy, warm, authentic feel
   - Earth tones (#8B7355, #6B8E23), soft organic shapes, muted colors
   - Rounded organic forms, natural textures
   - Good for: eco, wellness, organic products, nature brands

6. **PLAYFUL/CREATIVE** - Fun, colorful, expressive
   - Multiple bright colors, irregular/playful shapes, patterns
   - Mixed font weights, overlapping elements, high energy
   - Good for: kids, events, creative industries, entertainment

TEXT CONTENT BY INDUSTRY (generate similar creative text, not exact copies):
- Beauty: "Odkryj Swój Blask", "Letnia Kolekcja Pielęgnacji", "Naturalne Piękno", "Zarezerwuj Wizytę"
- Fitness: "Zmień Swoje Ciało", "Rozpocznij Transformację", "Dołącz Do Nas"
- Food: "Świeżo i Pysznie", "Zamów Online", "Spróbuj Różnicy"
- Fashion: "Nowa Kolekcja", "Stylowo i Elegancko", "Odkryj Trendy"
- Tech: "Innowacyjne Rozwiązania", "Przyszłość Dziś", "Sprawdź Ofertę"

SOCIAL MEDIA SIZES:
- Instagram Post: 1080x1080
- Instagram Story: 1080x1920
- Facebook Post: 1200x630
- Twitter/X Post: 1200x675
- LinkedIn Post: 1200x627
- Pinterest Pin: 1000x1500
- YouTube Thumbnail: 1280x720

MODERN COLOR PALETTES BY INDUSTRY (use these for contemporary designs):

**BEAUTY/COSMETICS:**
- Modern: gradient #667eea → #764ba2 (purple-violet), accent #f093fb (pink glow)
- Soft: #FFE4EC, #FFC0CB, #B76E79 (rose gold), white cards
- Dark luxury: #1A1A2E, #C9A961 (champagne gold), #FFFFFF

**TECH/STARTUP:**
- Vibrant: gradient #00d2ff → #3a7bd5 (cyan-blue), #1A1A2E dark, white
- Modern: #6366F1 (indigo), #8B5CF6 (purple), #EC4899 (pink accent)
- Clean: #3B82F6 (blue), #1E293B (slate dark), #F8FAFC (light)

**FITNESS/HEALTH:**
- Energy: gradient #f857a6 → #ff5858 (pink-red), #000000, #FFFFFF
- Bold: #FF6B35 (orange), #22C55E (green), #1F2937 (dark)
- Fresh: #10B981 (emerald), #3B82F6 (blue), #F0FDF4 (mint bg)

**FOOD/RESTAURANT:**
- Warm: #FF6B35 (coral), #FCD34D (yellow), #7C2D12 (brown), cream bg
- Fresh: #22C55E (green), #FBBF24 (amber), #FFFFFF
- Bold: gradient #ff416c → #ff4b2b (red), white cards, dark text

**LUXURY/FASHION:**
- Dark: #0A0A0A (black), #D4AF37 (gold), #FFFFFF (white accents)
- Modern luxury: #1A1A2E, gradient gold #BF953F → #FCF6BA, white
- Minimal: #F5F5F5, #1A1A1A, single accent color

**NATURE/ECO:**
- Organic: #22543D (forest), #86EFAC (mint), #FEF3C7 (cream)
- Fresh: gradient #11998e → #38ef7d (teal-green), earthy browns
- Soft: #D4E7C5 (sage), #8B7355 (earth), #FFFBEB (warm white)

INSTRUCTIONS:
1. When user asks to CREATE a template - use create_full_template with COMPLETE properties for every layer
2. When user asks to MODIFY something - identify the layer by name and use modify_layer
3. Always respond in the user's language (Polish if they write in Polish)
4. Be creative with text content - generate compelling marketing copy for the specific industry

IMPORTANT RULES:
- Layer names are case-sensitive
- Colors must be hex format (#FF0000)
- Font sizes in pixels
- Coordinates (x, y) start from top-left corner
- EVERY layer MUST have a properties object with appropriate values!

##############################################################################
# CORRECT EXAMPLE - Modern Instagram Beauty Post (FOLLOW THIS STRUCTURE)
##############################################################################
```json
{
  "template_settings": {"width": 1080, "height": 1080, "background_color": "#1A1A2E"},
  "layers": [
    {
      "name": "Gradient Background",
      "type": "rectangle",
      "x": 0, "y": 0, "width": 1080, "height": 1080,
      "properties": {"fillType": "gradient", "gradientType": "linear", "gradientStartColor": "#667eea", "gradientEndColor": "#764ba2", "gradientAngle": 135}
    },
    {
      "name": "Accent Blob Top Right",
      "type": "ellipse",
      "x": 680, "y": -200, "width": 600, "height": 600,
      "properties": {"fill": "#f093fb", "opacity": 0.3}
    },
    {
      "name": "Accent Blob Bottom Left",
      "type": "ellipse",
      "x": -150, "y": 700, "width": 500, "height": 500,
      "properties": {"fill": "#4facfe", "opacity": 0.25}
    },
    {
      "name": "Photo Container",
      "type": "rectangle",
      "x": 60, "y": 80, "width": 960, "height": 540,
      "properties": {"fill": "#FFFFFF", "cornerRadius": 24, "shadowEnabled": true, "shadowColor": "#000000", "shadowBlur": 40, "shadowOffsetX": 0, "shadowOffsetY": 20, "shadowOpacity": 0.25}
    },
    {
      "name": "Photo",
      "type": "image",
      "x": 75, "y": 95, "width": 930, "height": 510,
      "properties": {"fit": "cover", "cornerRadius": 16}
    },
    {
      "name": "Content Card",
      "type": "rectangle",
      "x": 80, "y": 660, "width": 920, "height": 360,
      "properties": {"fill": "#FFFFFF", "cornerRadius": 28, "shadowEnabled": true, "shadowColor": "#000000", "shadowBlur": 35, "shadowOffsetX": 0, "shadowOffsetY": 15, "shadowOpacity": 0.15}
    },
    {
      "name": "Accent Line",
      "type": "rectangle",
      "x": 390, "y": 700, "width": 300, "height": 4,
      "properties": {"fillType": "gradient", "gradientType": "linear", "gradientStartColor": "#667eea", "gradientEndColor": "#764ba2", "gradientAngle": 90, "cornerRadius": 2}
    },
    {
      "name": "Headline",
      "type": "text",
      "x": 100, "y": 730, "width": 880, "height": 80,
      "properties": {"text": "Odkryj Swój Blask", "fontSize": 52, "fontWeight": "bold", "fill": "#1A1A2E", "align": "center", "textTransform": "uppercase", "letterSpacing": 4}
    },
    {
      "name": "Subheading",
      "type": "text",
      "x": 100, "y": 820, "width": 880, "height": 60,
      "properties": {"text": "Profesjonalna pielęgnacja dla wymagających", "fontSize": 22, "fontWeight": "normal", "fill": "#666666", "align": "center", "lineHeight": 1.4}
    },
    {
      "name": "CTA Button",
      "type": "rectangle",
      "x": 190, "y": 900, "width": 700, "height": 65,
      "properties": {"fillType": "gradient", "gradientType": "linear", "gradientStartColor": "#667eea", "gradientEndColor": "#764ba2", "gradientAngle": 90, "cornerRadius": 32, "shadowEnabled": true, "shadowColor": "#667eea", "shadowBlur": 20, "shadowOffsetX": 0, "shadowOffsetY": 8, "shadowOpacity": 0.4}
    },
    {
      "name": "CTA Text",
      "type": "text",
      "x": 190, "y": 918, "width": 700, "height": 40,
      "properties": {"text": "Umów Wizytę", "fontSize": 20, "fontWeight": "bold", "fill": "#FFFFFF", "align": "center", "textTransform": "uppercase", "letterSpacing": 3}
    }
  ],
  "image_searches": [{"layer_name": "Photo", "search_query": "beauty spa woman relaxing facial treatment", "orientation": "landscape"}]
}
```
This example shows MODERN style with: gradient background, floating cards with shadows, accent blobs, strong visual hierarchy, contrasting CTA.
##############################################################################
PROMPT;
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
                                        'type' => ['type' => 'string', 'enum' => ['text', 'image', 'rectangle', 'ellipse']],
                                        'x' => ['type' => 'number', 'description' => 'X position'],
                                        'y' => ['type' => 'number', 'description' => 'Y position'],
                                        'width' => ['type' => 'number', 'description' => 'Width in pixels'],
                                        'height' => ['type' => 'number', 'description' => 'Height in pixels'],
                                        'properties' => [
                                            'type' => 'object',
                                            'description' => 'REQUIRED! For text: {text, fontSize, fontWeight, fill, align}. For rectangle: {fill, cornerRadius}. For ellipse: {fill}. For image: {fit}. Example text: {"text": "Summer Sale", "fontSize": 48, "fontWeight": "bold", "fill": "#B76E79", "align": "center"}',
                                            'properties' => [
                                                'text' => ['type' => 'string', 'description' => 'For text layers: actual marketing copy, never placeholders'],
                                                'fontSize' => ['type' => 'number', 'description' => 'For text: font size in pixels (40-72 for headlines, 18-32 for body)'],
                                                'fontWeight' => ['type' => 'string', 'enum' => ['normal', 'bold'], 'description' => 'For text: font weight'],
                                                'fill' => ['type' => 'string', 'description' => 'Color in hex format (#RRGGBB)'],
                                                'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right'], 'description' => 'For text: horizontal alignment'],
                                                'cornerRadius' => ['type' => 'number', 'description' => 'For rectangles: corner radius'],
                                                'fit' => ['type' => 'string', 'enum' => ['cover', 'contain'], 'description' => 'For images: how to fit'],
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
                                        'description' => 'Type-specific properties (text, fill, fontSize, etc.)',
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
                    'description' => 'Add a single new layer to the template.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Name for the new layer'],
                            'type' => ['type' => 'string', 'enum' => ['text', 'image', 'rectangle', 'ellipse']],
                            'x' => ['type' => 'number'],
                            'y' => ['type' => 'number'],
                            'width' => ['type' => 'number'],
                            'height' => ['type' => 'number'],
                            'properties' => ['type' => 'object'],
                        ],
                        'required' => ['name', 'type'],
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
     * Handle create_full_template - creates a complete template with multiple layers.
     */
    protected function handleCreateFullTemplate(array $arguments, Template $template): array
    {
        Log::channel('single')->info('=== CREATE FULL TEMPLATE ===', [
            'template_settings' => $arguments['template_settings'] ?? [],
            'layers_count' => count($arguments['layers'] ?? []),
            'image_searches_count' => count($arguments['image_searches'] ?? []),
        ]);

        $actions = [];
        $templateSettings = $arguments['template_settings'] ?? [];
        $layers = $arguments['layers'] ?? [];
        $imageSearches = $arguments['image_searches'] ?? [];

        // Log each layer
        foreach ($layers as $idx => $layer) {
            Log::channel('single')->info("=== LAYER #{$idx} ===", [
                'name' => $layer['name'] ?? 'unnamed',
                'type' => $layer['type'] ?? 'unknown',
                'x' => $layer['x'] ?? 'not set',
                'y' => $layer['y'] ?? 'not set',
                'width' => $layer['width'] ?? 'not set',
                'height' => $layer['height'] ?? 'not set',
                'properties' => $layer['properties'] ?? [],
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

        // Search for images if needed - use current template dimensions
        $imageUrls = [];
        foreach ($imageSearches as $search) {
            $layerName = $search['layer_name'] ?? '';
            $query = $search['search_query'] ?? '';
            $orientation = $search['orientation'] ?? null;

            if ($query && $layerName) {
                $result = $this->pexelsService->searchPhotos($query, 1, $orientation);
                if ($result['success'] && ! empty($result['photos'])) {
                    $photo = $result['photos'][0];
                    // Use CURRENT template dimensions, not AI-provided ones
                    $imageUrls[$layerName] = $this->pexelsService->getBestImageUrl($photo, $currentWidth, $currentHeight);
                }
            }
        }

        // Create all layers
        foreach ($layers as $layer) {
            $type = $layer['type'] ?? 'rectangle';
            $name = $layer['name'] ?? ucfirst($type);
            $properties = $layer['properties'] ?? [];

            // If this is an image layer and we have a URL for it, add the src
            if ($type === 'image' && isset($imageUrls[$name])) {
                $properties['src'] = $imageUrls[$name];
            }

            $defaultProperties = $this->getDefaultLayerProperties($type, $properties);

            $actions[] = [
                'type' => 'add_layer',
                'data' => [
                    'name' => $name,
                    'type' => $type,
                    'x' => $layer['x'] ?? 0,
                    'y' => $layer['y'] ?? 0,
                    'width' => $layer['width'] ?? ($type === 'text' ? 300 : 200),
                    'height' => $layer['height'] ?? ($type === 'text' ? 50 : 200),
                    'rotation' => $layer['rotation'] ?? 0,
                    'visible' => true,
                    'locked' => false,
                    'properties' => $defaultProperties,
                ],
            ];
        }

        return $actions;
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

        $result = $this->pexelsService->searchPhotos($query, $count, $orientation);

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
                'width' => $arguments['width'] ?? ($type === 'text' ? 300 : 200),
                'height' => $arguments['height'] ?? ($type === 'text' ? 50 : 200),
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

        $defaults = match ($type) {
            'text' => [
                'text' => $customProperties['text'] ?? '[Text content needed]',
                'fontFamily' => $customProperties['fontFamily'] ?? 'Arial',
                'fontSize' => $customProperties['fontSize'] ?? 24,
                'fontWeight' => $customProperties['fontWeight'] ?? 'normal',
                'fontStyle' => $customProperties['fontStyle'] ?? 'normal',
                'fill' => $customProperties['fill'] ?? '#000000',
                'align' => $customProperties['align'] ?? 'left',
                'verticalAlign' => $customProperties['verticalAlign'] ?? 'top',
                'lineHeight' => $customProperties['lineHeight'] ?? 1.2,
                'letterSpacing' => $customProperties['letterSpacing'] ?? 0,
            ],
            'image' => [
                'src' => $customProperties['src'] ?? null,
                'fit' => $customProperties['fit'] ?? 'cover',
            ],
            'rectangle' => [
                'fill' => $customProperties['fill'] ?? '#CCCCCC',
                'stroke' => $customProperties['stroke'] ?? null,
                'strokeWidth' => $customProperties['strokeWidth'] ?? 0,
                'cornerRadius' => $customProperties['cornerRadius'] ?? 0,
            ],
            'ellipse' => [
                'fill' => $customProperties['fill'] ?? '#CCCCCC',
                'stroke' => $customProperties['stroke'] ?? null,
                'strokeWidth' => $customProperties['strokeWidth'] ?? 0,
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
