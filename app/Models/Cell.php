<?php

namespace App\Models;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cell extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'value_text',
        'value_number',
        'value_datetime',
        'value_boolean',
        'value_json',
    ];

    protected $casts = [
        'value_number' => 'decimal:6',
        'value_datetime' => 'datetime',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
    ];

    // Relationships
    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->ordered();
    }

    // Value handling
    public function getValue(): mixed
    {
        $column = $this->field->type->valueColumn();
        $value = $this->{$column};

        return $this->field->formatValue($value);
    }

    public function getRawValue(): mixed
    {
        $column = $this->field->type->valueColumn();
        return $this->{$column};
    }

    public function setValue(mixed $value): void
    {
        // Reset all value columns
        $this->value_text = null;
        $this->value_number = null;
        $this->value_datetime = null;
        $this->value_boolean = null;
        $this->value_json = null;

        if ($value === null || $value === '') {
            return;
        }

        // Set the appropriate column
        $column = $this->field->type->valueColumn();
        $this->{$column} = $this->field->parseValue($value);
    }

    // Check if cell has any value
    public function hasValue(): bool
    {
        return $this->value_text !== null
            || $this->value_number !== null
            || $this->value_datetime !== null
            || $this->value_boolean !== null
            || $this->value_json !== null;
    }

    // For attachment type - get attachment models
    public function getAttachmentModels(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->field->type !== FieldType::ATTACHMENT) {
            return collect();
        }

        return $this->attachments;
    }
}
