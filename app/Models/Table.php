<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'position',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'base_id';
    }

    // Relationships
    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->ordered();
    }

    public function rows(): HasMany
    {
        return $this->hasMany(Row::class)->ordered();
    }

    public function cells(): HasManyThrough
    {
        return $this->hasManyThrough(Cell::class, Row::class);
    }

    // Scopes
    public function scopeWithFieldsAndRows($query)
    {
        return $query->with(['fields', 'rows.cells']);
    }

    // Helper methods
    public function addField(string $name, FieldType|string $type, array $options = []): Field
    {
        $typeValue = $type instanceof FieldType ? $type->value : $type;

        return $this->fields()->create([
            'name' => $name,
            'type' => $typeValue,
            'options' => $options ?: null,
        ]);
    }

    public function addRow(array $values = []): Row
    {
        $row = $this->rows()->create();

        foreach ($values as $fieldId => $value) {
            $row->setCellValue($fieldId, $value);
        }

        return $row;
    }

    public function getPrimaryField(): ?Field
    {
        return $this->fields()->where('is_primary', true)->first()
            ?? $this->fields()->first();
    }

    public function getSelectFields(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->fields()->whereIn('type', ['select', 'multi_select'])->get();
    }
}
