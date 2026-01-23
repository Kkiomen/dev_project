<?php

namespace App\Services;

use App\Enums\FieldType;
use App\Services\FieldTypes\FieldTypeInterface;
use App\Services\FieldTypes\TextField;
use App\Services\FieldTypes\NumberField;
use App\Services\FieldTypes\DateField;
use App\Services\FieldTypes\CheckboxField;
use App\Services\FieldTypes\SelectField;
use App\Services\FieldTypes\MultiSelectField;
use App\Services\FieldTypes\AttachmentField;
use App\Services\FieldTypes\UrlField;
use App\Services\FieldTypes\JsonField;

class FieldTypeRegistry
{
    private array $handlers = [];

    public function __construct()
    {
        $this->register(FieldType::TEXT, new TextField());
        $this->register(FieldType::NUMBER, new NumberField());
        $this->register(FieldType::DATE, new DateField());
        $this->register(FieldType::DATETIME, new DateField());
        $this->register(FieldType::CHECKBOX, new CheckboxField());
        $this->register(FieldType::SELECT, new SelectField());
        $this->register(FieldType::MULTI_SELECT, new MultiSelectField());
        $this->register(FieldType::ATTACHMENT, new AttachmentField());
        $this->register(FieldType::URL, new UrlField());
        $this->register(FieldType::JSON, new JsonField());
    }

    public function register(FieldType $type, FieldTypeInterface $handler): void
    {
        $this->handlers[$type->value] = $handler;
    }

    public function get(FieldType $type): FieldTypeInterface
    {
        return $this->handlers[$type->value]
            ?? throw new \InvalidArgumentException("Unknown field type: {$type->value}");
    }

    public function has(FieldType $type): bool
    {
        return isset($this->handlers[$type->value]);
    }

    public function all(): array
    {
        return $this->handlers;
    }
}
