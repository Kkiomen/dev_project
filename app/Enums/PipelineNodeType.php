<?php

namespace App\Enums;

enum PipelineNodeType: string
{
    case ImageInput = 'image_input';
    case TextInput = 'text_input';
    case Template = 'template';
    case AiImageGenerator = 'ai_image_generator';
    case ImageAnalysis = 'image_analysis';
    case TemplateRender = 'template_render';
    case Output = 'output';

    public function label(): string
    {
        return match ($this) {
            self::ImageInput => 'Image Input',
            self::TextInput => 'Text Input',
            self::Template => 'Template',
            self::AiImageGenerator => 'AI Image Generator',
            self::ImageAnalysis => 'Image Analysis',
            self::TemplateRender => 'Template Render',
            self::Output => 'Output',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ImageInput => 'blue',
            self::TextInput => 'green',
            self::Template => 'purple',
            self::AiImageGenerator => 'pink',
            self::ImageAnalysis => 'orange',
            self::TemplateRender => 'indigo',
            self::Output => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ImageInput => 'photo',
            self::TextInput => 'document-text',
            self::Template => 'template',
            self::AiImageGenerator => 'sparkles',
            self::ImageAnalysis => 'eye',
            self::TemplateRender => 'paint-brush',
            self::Output => 'arrow-down-tray',
        };
    }

    public function outputs(): array
    {
        return match ($this) {
            self::ImageInput => ['image'],
            self::TextInput => ['text'],
            self::Template => ['template'],
            self::AiImageGenerator => ['image'],
            self::ImageAnalysis => ['analysis', 'image'],
            self::TemplateRender => ['image'],
            self::Output => [],
        };
    }

    public function inputs(): array
    {
        return match ($this) {
            self::ImageInput => [],
            self::TextInput => [],
            self::Template => [],
            self::AiImageGenerator => ['text', 'image', 'template'],
            self::ImageAnalysis => ['image'],
            self::TemplateRender => ['template', 'image', 'text', 'analysis'],
            self::Output => ['image', 'text'],
        };
    }

    public function requiredInputs(): array
    {
        return match ($this) {
            self::AiImageGenerator => ['text'],
            self::ImageAnalysis => ['image'],
            self::TemplateRender => ['template'],
            default => [],
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
