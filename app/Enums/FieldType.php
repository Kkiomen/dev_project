<?php

namespace App\Enums;

enum FieldType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case CHECKBOX = 'checkbox';
    case SELECT = 'select';
    case MULTI_SELECT = 'multi_select';
    case ATTACHMENT = 'attachment';
    case URL = 'url';
    case JSON = 'json';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Tekst',
            self::NUMBER => 'Liczba',
            self::DATE => 'Data',
            self::DATETIME => 'Data i czas',
            self::CHECKBOX => 'Checkbox',
            self::SELECT => 'Wybór pojedynczy',
            self::MULTI_SELECT => 'Wybór wielokrotny',
            self::ATTACHMENT => 'Załącznik',
            self::URL => 'URL',
            self::JSON => 'JSON',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TEXT => 'type',
            self::NUMBER => 'hash',
            self::DATE => 'calendar',
            self::DATETIME => 'clock',
            self::CHECKBOX => 'check-square',
            self::SELECT => 'chevron-down',
            self::MULTI_SELECT => 'list',
            self::ATTACHMENT => 'paperclip',
            self::URL => 'link',
            self::JSON => 'code',
        };
    }

    public function valueColumn(): string
    {
        return match ($this) {
            self::TEXT, self::URL => 'value_text',
            self::NUMBER => 'value_number',
            self::DATE, self::DATETIME => 'value_datetime',
            self::CHECKBOX => 'value_boolean',
            self::SELECT, self::MULTI_SELECT, self::ATTACHMENT, self::JSON => 'value_json',
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
