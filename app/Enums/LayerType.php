<?php

namespace App\Enums;

enum LayerType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case RECTANGLE = 'rectangle';
    case ELLIPSE = 'ellipse';
    case LINE = 'line';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('graphics.layer_types.text'),
            self::IMAGE => __('graphics.layer_types.image'),
            self::RECTANGLE => __('graphics.layer_types.rectangle'),
            self::ELLIPSE => __('graphics.layer_types.ellipse'),
            self::LINE => __('graphics.layer_types.line'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TEXT => 'type',
            self::IMAGE => 'image',
            self::RECTANGLE => 'square',
            self::ELLIPSE => 'circle',
            self::LINE => 'line',
        };
    }

    public function defaultProperties(): array
    {
        return match ($this) {
            self::TEXT => [
                'text' => '',
                'fontFamily' => 'Arial',
                'fontSize' => 24,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'lineHeight' => 1.2,
                'letterSpacing' => 0,
                'fill' => '#000000',
                'align' => 'left',
                'verticalAlign' => 'top',
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
