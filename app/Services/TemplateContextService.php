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
        $context .= "\nLayers:\n";

        foreach ($template->layers as $index => $layer) {
            $context .= "- [{$layer->type->value}] \"{$layer->name}\"";

            if ($layer->type->value === 'text' && isset($layer->properties['text'])) {
                $text = mb_substr($layer->properties['text'], 0, 50);
                $context .= " - text: \"{$text}\"";
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
- fontFamily: string (e.g., "Arial", "Roboto", "Playfair Display", "Montserrat")
- fontSize: number (in pixels)
- fontWeight: "normal" | "bold" | "100"-"900"
- fontStyle: "normal" | "italic"
- fill: string (hex color, e.g., "#000000")
- align: "left" | "center" | "right"
- verticalAlign: "top" | "middle" | "bottom"
- lineHeight: number (e.g., 1.2)
- letterSpacing: number (in pixels)
- textTransform: "none" | "uppercase" | "lowercase" | "capitalize"
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
