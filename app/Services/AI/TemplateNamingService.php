<?php

namespace App\Services\AI;

use App\Services\OpenAiClientService;
use Exception;
use Illuminate\Support\Facades\Log;

class TemplateNamingService
{
    protected OpenAiClientService $openAiClient;

    public function __construct(OpenAiClientService $openAiClient)
    {
        $this->openAiClient = $openAiClient;
    }

    /**
     * Generate a creative template name based on its content.
     *
     * @param array $parsedData The parsed PSD data
     * @param int $variantNumber Optional variant number for multi-variant PSDs
     * @return string
     */
    public function generateName(array $parsedData, ?int $variantNumber = null): string
    {
        try {
            $context = $this->buildContext($parsedData, $variantNumber);
            $prompt = $this->buildPrompt($context);

            $response = $this->openAiClient->chatCompletion([
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $name = trim($response->choices[0]->message->content);

            // Clean up the name - remove quotes if present
            $name = trim($name, '"\'');

            // Ensure reasonable length
            if (strlen($name) > 60) {
                $name = substr($name, 0, 57) . '...';
            }

            Log::info('TemplateNamingService: Name generated', [
                'name' => $name,
                'variant' => $variantNumber,
            ]);

            return $name;

        } catch (Exception $e) {
            Log::warning('TemplateNamingService: Failed to generate name, using fallback', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackName($parsedData, $variantNumber);
        }
    }

    /**
     * Generate names for multiple variants at once (more efficient).
     *
     * @param array $parsedData The parsed PSD data
     * @param array $variantNumbers Array of variant numbers
     * @return array Map of variant number to name
     */
    public function generateNamesForVariants(array $parsedData, array $variantNumbers): array
    {
        try {
            $context = $this->buildContext($parsedData, null);
            $variantCount = count($variantNumbers);

            $prompt = $this->buildBatchPrompt($context, $variantCount);

            $response = $this->openAiClient->chatCompletion([
                ['role' => 'system', 'content' => $this->getBatchSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $content = trim($response->choices[0]->message->content);
            $names = $this->parseBatchResponse($content, $variantNumbers);

            Log::info('TemplateNamingService: Batch names generated', [
                'count' => count($names),
            ]);

            return $names;

        } catch (Exception $e) {
            Log::warning('TemplateNamingService: Failed to generate batch names, using fallback', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to individual names
            $names = [];
            foreach ($variantNumbers as $num) {
                $names[$num] = $this->generateFallbackName($parsedData, $num);
            }
            return $names;
        }
    }

    /**
     * Build context from parsed PSD data.
     */
    protected function buildContext(array $parsedData, ?int $variantNumber): array
    {
        $width = $parsedData['width'] ?? 1080;
        $height = $parsedData['height'] ?? 1080;

        // Determine format based on dimensions
        $format = $this->detectFormat($width, $height);

        // Extract text content from layers
        $textContent = $this->extractTextContent($parsedData['layers'] ?? []);

        // Extract layer names
        $layerNames = $this->extractLayerNames($parsedData['layers'] ?? []);

        return [
            'width' => $width,
            'height' => $height,
            'format' => $format,
            'text_content' => $textContent,
            'layer_names' => $layerNames,
            'variant_number' => $variantNumber,
        ];
    }

    /**
     * Detect the template format based on dimensions.
     */
    protected function detectFormat(int $width, int $height): string
    {
        $ratio = $width / $height;

        // Square formats
        if (abs($ratio - 1) < 0.1) {
            if ($width >= 1080) {
                return 'Instagram Post';
            }
            return 'Square Post';
        }

        // Portrait formats (9:16, Stories)
        if ($ratio < 0.7) {
            if ($width == 1080 && $height == 1920) {
                return 'Instagram Story';
            }
            return 'Vertical Post';
        }

        // Landscape formats (16:9)
        if ($ratio > 1.5) {
            if ($width == 1920 && $height == 1080) {
                return 'YouTube Thumbnail';
            }
            if ($width == 1200 && $height == 630) {
                return 'Facebook Post';
            }
            return 'Horizontal Banner';
        }

        // 4:5 format (Instagram portrait)
        if (abs($ratio - 0.8) < 0.1) {
            return 'Instagram Portrait';
        }

        return 'Social Media Post';
    }

    /**
     * Extract text content from layers.
     */
    protected function extractTextContent(array $layers, int $maxLength = 500): array
    {
        $texts = [];

        foreach ($layers as $layer) {
            if (in_array($layer['type'] ?? '', ['text', 'textbox'])) {
                $text = $layer['properties']['text'] ?? '';
                if (!empty($text) && strlen($text) > 3) {
                    $texts[] = substr($text, 0, 100);
                }
            }

            // Recurse into children
            if (!empty($layer['children'])) {
                $childTexts = $this->extractTextContent($layer['children'], $maxLength);
                $texts = array_merge($texts, $childTexts);
            }
        }

        return array_slice($texts, 0, 10); // Max 10 text samples
    }

    /**
     * Extract meaningful layer names.
     */
    protected function extractLayerNames(array $layers): array
    {
        $names = [];
        $skipPatterns = [
            '/^layer\s*\d*$/i',
            '/^group\s*\d*$/i',
            '/^background$/i',
            '/^shape\s*\d*$/i',
            '/^rectangle\s*\d*$/i',
            '/^image\s*\d*$/i',
            '/^copy$/i',
            '/^\d+$/',
        ];

        foreach ($layers as $layer) {
            $name = $layer['name'] ?? '';

            // Skip generic names
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip && strlen($name) > 2 && strlen($name) < 50) {
                $names[] = $name;
            }

            // Recurse into children
            if (!empty($layer['children'])) {
                $childNames = $this->extractLayerNames($layer['children']);
                $names = array_merge($names, $childNames);
            }
        }

        return array_unique(array_slice($names, 0, 15)); // Max 15 unique names
    }

    /**
     * Get the system prompt for name generation.
     */
    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
You are a creative naming assistant for social media templates. Generate short, catchy, professional English names for design templates.

Rules:
- Name should be 2-5 words
- Use descriptive, marketing-friendly language
- Focus on the template's purpose or style
- Don't include file extensions or numbers
- Don't use generic words like "template", "design", "post"
- Make it memorable and easy to search

Respond with ONLY the template name, nothing else.
PROMPT;
    }

    /**
     * Get the system prompt for batch name generation.
     */
    protected function getBatchSystemPrompt(): string
    {
        return <<<PROMPT
You are a creative naming assistant for social media templates. Generate short, catchy, professional English names for design template variants.

Rules:
- Each name should be 2-5 words
- Use descriptive, marketing-friendly language
- Names should be related but distinct (they're variants of the same design)
- Don't include file extensions or numbers
- Don't use generic words like "template", "design", "post"
- Make them memorable and easy to search

Respond with ONLY the names, one per line, in order. No numbering, no explanations.
PROMPT;
    }

    /**
     * Build the prompt for single name generation.
     */
    protected function buildPrompt(array $context): string
    {
        $parts = ["Generate a creative name for this {$context['format']} template."];

        if (!empty($context['text_content'])) {
            $texts = implode(', ', array_slice($context['text_content'], 0, 5));
            $parts[] = "Text content includes: {$texts}";
        }

        if (!empty($context['layer_names'])) {
            $names = implode(', ', array_slice($context['layer_names'], 0, 8));
            $parts[] = "Design elements: {$names}";
        }

        $parts[] = "Dimensions: {$context['width']}x{$context['height']}px";

        if ($context['variant_number']) {
            $parts[] = "This is variant #{$context['variant_number']} of a template set.";
        }

        return implode("\n", $parts);
    }

    /**
     * Build the prompt for batch name generation.
     */
    protected function buildBatchPrompt(array $context, int $count): string
    {
        $parts = ["Generate {$count} creative names for variants of this {$context['format']} template set."];

        if (!empty($context['text_content'])) {
            $texts = implode(', ', array_slice($context['text_content'], 0, 5));
            $parts[] = "Text content includes: {$texts}";
        }

        if (!empty($context['layer_names'])) {
            $names = implode(', ', array_slice($context['layer_names'], 0, 8));
            $parts[] = "Design elements: {$names}";
        }

        $parts[] = "Dimensions: {$context['width']}x{$context['height']}px";
        $parts[] = "Generate exactly {$count} distinct but related names.";

        return implode("\n", $parts);
    }

    /**
     * Parse the batch response into a map of variant numbers to names.
     */
    protected function parseBatchResponse(string $content, array $variantNumbers): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $names = [];

        foreach ($variantNumbers as $index => $num) {
            if (isset($lines[$index])) {
                $name = trim($lines[$index], '"\'.-0123456789) ');
                $names[$num] = $name ?: $this->generateFallbackName([], $num);
            } else {
                $names[$num] = $this->generateFallbackName([], $num);
            }
        }

        return $names;
    }

    /**
     * Generate a fallback name when AI fails.
     */
    protected function generateFallbackName(array $parsedData, ?int $variantNumber): string
    {
        $width = $parsedData['width'] ?? 1080;
        $height = $parsedData['height'] ?? 1080;
        $format = $this->detectFormat($width, $height);

        if ($variantNumber) {
            return "{$format} - Style {$variantNumber}";
        }

        return $format;
    }
}
