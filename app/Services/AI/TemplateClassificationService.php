<?php

namespace App\Services\AI;

use App\Models\Layer;
use App\Models\Template;
use App\Models\TemplateTag;
use App\Services\OpenAiClientService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TemplateClassificationService
{
    protected OpenAiClientService $openAiClient;

    public function __construct(OpenAiClientService $openAiClient)
    {
        $this->openAiClient = $openAiClient;
    }

    /**
     * Classify all layers in a template by semantic role.
     *
     * @param Template $template
     * @return void
     * @throws Exception
     */
    public function classifyLayers(Template $template): void
    {
        $layers = $template->layers()->get();

        if ($layers->isEmpty()) {
            Log::info('TemplateClassificationService: No layers to classify', [
                'template_id' => $template->id,
            ]);
            return;
        }

        // Build layer data for the prompt
        $layerData = $layers->map(function (Layer $layer) {
            return [
                'id' => $layer->id,
                'name' => $layer->name,
                'type' => $layer->type->value,
                'width' => $layer->width,
                'height' => $layer->height,
                'x' => $layer->x,
                'y' => $layer->y,
            ];
        })->toArray();

        $prompt = $this->buildLayerClassificationPrompt($layerData, $template);

        try {
            $response = $this->openAiClient->chatCompletion([
                ['role' => 'system', 'content' => $this->getLayerClassificationSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = $response->choices[0]->message->content;
            $classifications = $this->parseLayerClassificationResponse($content);

            // Apply classifications
            DB::transaction(function () use ($classifications) {
                foreach ($classifications as $classification) {
                    $layerId = $classification['layer_id'] ?? null;
                    $role = $classification['role'] ?? null;
                    $confidence = $classification['confidence'] ?? 1.0;

                    if (!$layerId || !$role) {
                        continue;
                    }

                    if (!Layer::isValidSemanticRole($role)) {
                        Log::warning('TemplateClassificationService: Invalid semantic role', [
                            'layer_id' => $layerId,
                            'role' => $role,
                        ]);
                        continue;
                    }

                    // Use the Layer model method which syncs semantic_role with properties.semanticTags
                    $layer = Layer::find($layerId);
                    if ($layer) {
                        $layer->setSemanticRole($role, min(1.0, max(0.0, (float) $confidence)));
                    }
                }
            });

            Log::info('TemplateClassificationService: Layers classified', [
                'template_id' => $template->id,
                'classified_count' => count($classifications),
            ]);

        } catch (Exception $e) {
            Log::error('TemplateClassificationService: Layer classification failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate style tags for a template.
     *
     * @param Template $template
     * @return void
     * @throws Exception
     */
    public function generateStyleTags(Template $template): void
    {
        $template->load('layers');

        // Build template description for the prompt
        $templateData = [
            'name' => $template->name,
            'width' => $template->width,
            'height' => $template->height,
            'background_color' => $template->background_color,
            'layer_count' => $template->layers->count(),
            'layer_types' => $template->layers->pluck('type.value')->countBy()->toArray(),
            'layer_names' => $template->layers->pluck('name')->toArray(),
        ];

        $prompt = $this->buildStyleTaggingPrompt($templateData);

        try {
            $response = $this->openAiClient->chatCompletion([
                ['role' => 'system', 'content' => $this->getStyleTaggingSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = $response->choices[0]->message->content;
            $tags = $this->parseStyleTaggingResponse($content);

            // Apply tags
            DB::transaction(function () use ($template, $tags) {
                foreach ($tags as $tagData) {
                    $name = $tagData['name'] ?? null;
                    $category = $tagData['category'] ?? null;
                    $confidence = $tagData['confidence'] ?? 1.0;

                    if (!$name || !$category) {
                        continue;
                    }

                    if (!in_array($category, TemplateTag::VALID_CATEGORIES, true)) {
                        Log::warning('TemplateClassificationService: Invalid tag category', [
                            'template_id' => $template->id,
                            'name' => $name,
                            'category' => $category,
                        ]);
                        continue;
                    }

                    // Find or create the tag
                    $tag = TemplateTag::findOrCreateByName($name, $category);

                    // Attach to template if not already attached
                    if (!$template->tags()->where('template_tag_id', $tag->id)->exists()) {
                        $template->tags()->attach($tag->id, [
                            'confidence' => min(1.0, max(0.0, (float) $confidence)),
                            'is_ai_generated' => true,
                        ]);

                        $tag->incrementUsage();
                    }
                }
            });

            Log::info('TemplateClassificationService: Style tags generated', [
                'template_id' => $template->id,
                'tag_count' => count($tags),
            ]);

        } catch (Exception $e) {
            Log::error('TemplateClassificationService: Style tagging failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the system prompt for layer classification.
     */
    protected function getLayerClassificationSystemPrompt(): string
    {
        $roles = implode(', ', Layer::SEMANTIC_ROLES);

        return <<<PROMPT
You are a design analysis expert. Your task is to classify template layers by their semantic role based on their name, type, position, and size.

Available semantic roles: {$roles}

Guidelines for classification:
- "header" - Main title/headline, usually largest text
- "subtitle" - Secondary title, smaller than header
- "body" - Body text, paragraph content
- "cta" - Call-to-action buttons or links
- "decoration" - Decorative elements, shapes, lines
- "main_image" - Primary image content
- "avatar" - Profile pictures, user images
- "logo" - Brand logos
- "background" - Background layers, large covering shapes
- "accent" - Accent elements, highlights
- "social_handle" - Social media handles, @mentions
- "date" - Date/time information
- "quote" - Quoted text, testimonials

Respond ONLY with a valid JSON object in this format:
{"classifications": [{"layer_id": 123, "role": "header", "confidence": 0.95}]}

Do not include any explanation or additional text.
PROMPT;
    }

    /**
     * Get the system prompt for style tagging.
     */
    protected function getStyleTaggingSystemPrompt(): string
    {
        $validTags = json_encode(TemplateTag::VALID_TAGS, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a design style analyst. Your task is to generate style tags for a template based on its structure and content.

Valid tags by category:
{$validTags}

Guidelines:
- Choose 3-6 most relevant tags
- Each tag must be from the valid tags list
- Assign confidence scores (0.0-1.0) based on how strongly the template matches the tag
- Consider layer names, types, and overall composition

Respond ONLY with a valid JSON object in this format:
{"tags": [{"name": "minimalist", "category": "style", "confidence": 0.9}]}

Do not include any explanation or additional text.
PROMPT;
    }

    /**
     * Build the prompt for layer classification.
     */
    protected function buildLayerClassificationPrompt(array $layerData, Template $template): string
    {
        $layersJson = json_encode($layerData, JSON_PRETTY_PRINT);

        return <<<PROMPT
Classify the following layers from a template named "{$template->name}" ({$template->width}x{$template->height}):

{$layersJson}

Analyze each layer and assign the most appropriate semantic role based on the layer name, type, and dimensions.
PROMPT;
    }

    /**
     * Build the prompt for style tagging.
     */
    protected function buildStyleTaggingPrompt(array $templateData): string
    {
        $dataJson = json_encode($templateData, JSON_PRETTY_PRINT);

        return <<<PROMPT
Generate style tags for this template:

{$dataJson}

Analyze the template structure and generate appropriate style tags from the valid categories.
PROMPT;
    }

    /**
     * Parse the layer classification response.
     */
    protected function parseLayerClassificationResponse(string $content): array
    {
        $content = trim($content);

        // Extract JSON from potential markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('TemplateClassificationService: Failed to parse classification response', [
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);
            return [];
        }

        return $data['classifications'] ?? [];
    }

    /**
     * Parse the style tagging response.
     */
    protected function parseStyleTaggingResponse(string $content): array
    {
        $content = trim($content);

        // Extract JSON from potential markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('TemplateClassificationService: Failed to parse tagging response', [
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);
            return [];
        }

        return $data['tags'] ?? [];
    }
}
