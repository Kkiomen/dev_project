<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use App\Enums\FieldType;
use App\Services\FieldTypeRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Field extends Model
{
    use HasFactory, HasPublicId, HasPosition;

    protected $fillable = [
        'name',
        'type',
        'options',
        'is_required',
        'is_primary',
        'position',
        'width',
    ];

    protected $casts = [
        'type' => FieldType::class,
        'options' => 'array',
        'is_required' => 'boolean',
        'is_primary' => 'boolean',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'table_id';
    }

    // Relationships
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }

    // Accessors
    public function getTypeHandlerAttribute()
    {
        return app(FieldTypeRegistry::class)->get($this->type);
    }

    // Helper methods
    public function getDefaultValue(): mixed
    {
        return $this->typeHandler->getDefaultValue($this);
    }

    public function validateValue(mixed $value): bool
    {
        return $this->typeHandler->validate($value, $this);
    }

    public function formatValue(mixed $value): mixed
    {
        return $this->typeHandler->format($value, $this);
    }

    public function parseValue(mixed $value): mixed
    {
        return $this->typeHandler->parse($value, $this);
    }

    public function getValueColumn(): string
    {
        return $this->type->valueColumn();
    }

    // Options helpers for select/multi_select
    public function getChoices(): array
    {
        return $this->options['choices'] ?? [];
    }

    public function addChoice(string $name, ?string $color = null): self
    {
        $choices = $this->getChoices();
        $choices[] = [
            'id' => Str::ulid()->toBase32(),
            'name' => $name,
            'color' => $color ?? $this->generateColor(),
        ];

        $this->update(['options' => array_merge($this->options ?? [], ['choices' => $choices])]);

        return $this;
    }

    public function updateChoice(string $choiceId, string $name, ?string $color = null): self
    {
        $choices = collect($this->getChoices())->map(function ($choice) use ($choiceId, $name, $color) {
            if ($choice['id'] === $choiceId) {
                $choice['name'] = $name;
                if ($color) {
                    $choice['color'] = $color;
                }
            }
            return $choice;
        })->toArray();

        $this->update(['options' => array_merge($this->options ?? [], ['choices' => $choices])]);

        return $this;
    }

    public function removeChoice(string $choiceId): self
    {
        $choices = collect($this->getChoices())
            ->reject(fn($choice) => $choice['id'] === $choiceId)
            ->values()
            ->toArray();

        $this->update(['options' => array_merge($this->options ?? [], ['choices' => $choices])]);

        return $this;
    }

    private function generateColor(): string
    {
        $colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#14B8A6', '#3B82F6', '#8B5CF6', '#EC4899'];
        return $colors[array_rand($colors)];
    }
}
