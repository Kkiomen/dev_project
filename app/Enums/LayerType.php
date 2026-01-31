<?php

namespace App\Enums;

enum LayerType: string
{
    case TEXT = 'text';
    case TEXTBOX = 'textbox';
    case IMAGE = 'image';
    case RECTANGLE = 'rectangle';
    case ELLIPSE = 'ellipse';
    case LINE = 'line';
    case GROUP = 'group';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('graphics.layer_types.text'),
            self::TEXTBOX => __('graphics.layer_types.textbox'),
            self::IMAGE => __('graphics.layer_types.image'),
            self::RECTANGLE => __('graphics.layer_types.rectangle'),
            self::ELLIPSE => __('graphics.layer_types.ellipse'),
            self::LINE => __('graphics.layer_types.line'),
            self::GROUP => __('graphics.layer_types.group'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TEXT => 'type',
            self::TEXTBOX => 'square-text',
            self::IMAGE => 'image',
            self::RECTANGLE => 'square',
            self::ELLIPSE => 'circle',
            self::LINE => 'line',
            self::GROUP => 'folder',
        };
    }

    public function defaultProperties(): array
    {
        return match ($this) {
            self::TEXT => [
                'text' => '',
                'fontFamily' => 'Montserrat',
                'fontSize' => 24,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'lineHeight' => 1.2,
                'letterSpacing' => 0,
                'fill' => '#000000',
                'align' => 'left',
                'verticalAlign' => 'top',
                'textDirection' => 'horizontal',
            ],
            self::TEXTBOX => [
                'text' => 'Button',
                'fontFamily' => 'Montserrat',
                'fontSize' => 16,
                'fontWeight' => '600',
                'fontStyle' => 'normal',
                'lineHeight' => 1.1,
                'letterSpacing' => 0,
                'fill' => '#3B82F6',
                'textColor' => '#FFFFFF',
                'align' => 'center',
                'padding' => 16,
                'cornerRadius' => 25,
            ],
            self::IMAGE => [
                'src' => null,
                'fit' => 'cover',
            ],
            self::RECTANGLE => [
                'fill' => '#CCCCCC',
                'stroke' => null,
                'strokeWidth' => 0,
                'cornerRadius' => 0,
            ],
            self::ELLIPSE => [
                'fill' => '#CCCCCC',
                'stroke' => null,
                'strokeWidth' => 0,
            ],
            self::LINE => [
                'points' => [0, 0, 100, 0],
                'stroke' => '#000000',
                'strokeWidth' => 2,
                'lineCap' => 'round',
                'lineJoin' => 'round',
                'dash' => [],
            ],
            self::GROUP => [
                'expanded' => true,
            ],
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'icon' => $case->icon(),
        ])->toArray();
    }
}
