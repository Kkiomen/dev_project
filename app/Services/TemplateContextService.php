<?php

namespace App\Services;

use App\Models\Template;

class TemplateContextService
{
    /**
     * Get full template data as JSON-serializable array.
     */
    public function getTemplateAsJson(Template $template): array
    {
        $template->load(['layers', 'fonts']);

        return [
            'id' => $template->public_id,
            'name' => $template->name,
            'description' => $template->description,
            'width' => $template->width,
            'height' => $template->height,
            'background_color' => $template->background_color,
            'background_image' => $template->background_image,
            'layers' => $template->layers->map(function ($layer) {
                return [
                    'id' => $layer->public_id,
                    'name' => $layer->name,
                    'type' => $layer->type->value,
                    'x' => $layer->x,
                    'y' => $layer->y,
                    'width' => $layer->width,
                    'height' => $layer->height,
                    'rotation' => $layer->rotation,
                    'visible' => $layer->visible,
                    'locked' => $layer->locked,
                    'properties' => $layer->properties ?? [],
                ];
            })->toArray(),
        ];
    }

    /**
     * Get simplified context for AI system prompt.
     */
    public function getSimplifiedContext(Template $template): string
    {
        $template->load('layers');

        $context = "Template: {$template->name}\n";
        $context .= "Dimensions: {$template->width}x{$template->height}px\n";
        $context .= "Background: {$template->background_color}\n";
        $context .= "\nLayers (with current properties):\n";

        foreach ($template->layers as $index => $layer) {
            $props = $layer->properties ?? [];
            $context .= "- [{$layer->type->value}] \"{$layer->name}\"";

            // Show relevant properties based on layer type
            if ($layer->type->value === 'text') {
                $text = mb_substr($props['text'] ?? '', 0, 30);
                $context .= " - text: \"{$text}\"";
                if (isset($props['fontFamily'])) {
                    $context .= ", font: {$props['fontFamily']}";
                }
                if (isset($props['fontSize'])) {
                    $context .= ", size: {$props['fontSize']}px";
                }
                if (isset($props['fontWeight']) && $props['fontWeight'] !== 'normal') {
                    $context .= ", weight: {$props['fontWeight']}";
                }
                if (isset($props['fill'])) {
                    $context .= ", color: {$props['fill']}";
                }
                if (isset($props['textTransform']) && $props['textTransform'] !== 'none') {
                    $context .= ", transform: {$props['textTransform']}";
                }
            } elseif (in_array($layer->type->value, ['rectangle', 'ellipse'])) {
                if (isset($props['fillType']) && $props['fillType'] === 'gradient') {
                    $context .= " - GRADIENT from {$props['gradientStartColor']} to {$props['gradientEndColor']}";
                } elseif (isset($props['fill'])) {
                    $context .= " - fill: {$props['fill']}";
                }
            } elseif ($layer->type->value === 'line') {
                if (isset($props['stroke'])) {
                    $context .= " - stroke: {$props['stroke']}";
                }
            }

            $context .= "\n";
        }

        return $context;
    }

    /**
     * Get API documentation for AI context.
     */
    public function getApiDocumentation(): string
    {
        $baseUrl = config('app.url');

        return <<<DOC
API Documentation:

1. Generate Image from Template:
   POST {$baseUrl}/api/v1/templates/{template_id}/generate

   Request body:
   {
       "modifications": {
           "Layer Name": {
               "text": "New text content",
               "fill": "#FF0000",
               "fontSize": 24,
               "fontWeight": "bold",
               "src": "https://image-url.jpg"
           }
       },
       "format": "png|jpeg|webp",
       "quality": 100,
       "scale": 1
   }

   Headers:
   - Authorization: Bearer YOUR_API_TOKEN
   - Content-Type: application/json

2. Get Template Details:
   GET {$baseUrl}/api/v1/templates/{template_id}

3. Update Template:
   PUT {$baseUrl}/api/v1/templates/{template_id}

4. List All Templates:
   GET {$baseUrl}/api/v1/templates

Authentication:
All API requests require a Bearer token in the Authorization header.
DOC;
    }

    /**
     * Get layer type definitions for AI context.
     */
    public function getLayerTypeDefinitions(): string
    {
        return <<<TYPES
Layer Types and Properties:

TEXT:
- text: string (the text content)
- fontFamily: string - ANY Google Font name! Examples: "Roboto", "Open Sans", "Montserrat", "Playfair Display", "Lato", "Poppins", "Oswald", "Raleway", "Merriweather", "Ubuntu", "Nunito", "Dancing Script", "Pacifico", "Bebas Neue", "Lobster", "Abril Fatface". Use modern, stylish fonts for headlines!
- fontSize: number (in pixels, headlines: 48-72px, subtext: 18-32px)
- fontWeight: "normal" | "bold" | "100" | "200" | "300" | "400" | "500" | "600" | "700" | "800" | "900"
- fontStyle: "normal" | "italic"
- fill: string (hex color, e.g., "#000000")
- align: "left" | "center" | "right"
- verticalAlign: "top" | "middle" | "bottom"
- lineHeight: number (e.g., 1.0 for tight, 1.2 normal, 1.5 for loose)
- letterSpacing: number (in pixels, 0 normal, 2-5 for spaced headlines)
- textTransform: "none" | "uppercase" | "lowercase" | "capitalize" (use "uppercase" for modern headlines!)
- textDecoration: "" | "underline" | "line-through"

IMAGE:
- src: string (image URL or data URL)
- fit: "cover" | "contain" | "tile" | "stretch"

RECTANGLE:
- fill: string (hex color) - OR use gradient (see below)
- stroke: string (hex color, optional)
- strokeWidth: number
- cornerRadius: number (for rounded corners)
- fillType: "solid" | "gradient" (default: "solid")
- gradientType: "linear" | "radial" (when fillType is "gradient")
- gradientStartColor: string (hex color for gradient start)
- gradientEndColor: string (hex color for gradient end)
- gradientAngle: number (0-360, for linear gradients)

ELLIPSE:
- fill: string (hex color) - OR use gradient
- stroke: string (hex color, optional)
- strokeWidth: number
- fillType: "solid" | "gradient"
- gradientType, gradientStartColor, gradientEndColor, gradientAngle (same as rectangle)

LINE:
- points: array of numbers [x1, y1, x2, y2] (coordinates relative to layer position, e.g., [0, 0, 200, 0] for horizontal line)
- stroke: string (hex color for the line)
- strokeWidth: number (line thickness, e.g., 2-10)
- lineCap: "butt" | "round" | "square" (line ending style)
- lineJoin: "miter" | "round" | "bevel" (line join style)
- dash: array of numbers (e.g., [10, 5] for dashed, [2, 4] for dotted, [] for solid)

COMMON PROPERTIES (all layer types):
- opacity: number (0-1, default: 1)
- shadowEnabled: boolean (default: false)
- shadowColor: string (hex color, e.g., "#000000")
- shadowBlur: number (blur radius, e.g., 10-20)
- shadowOffsetX: number (horizontal offset, e.g., 5)
- shadowOffsetY: number (vertical offset, e.g., 5)
- shadowOpacity: number (0-1, e.g., 0.3)
TYPES;
    }
}
