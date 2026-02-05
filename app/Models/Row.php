<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Row extends Model
{
    use HasFactory, HasPublicId, HasPosition, SoftDeletes;

    protected $fillable = [
        'position',
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

    // Helper methods
    public function getCellValue(int|string $fieldId): mixed
    {
        $field = $this->resolveField($fieldId);
        $cell = $this->cells()->where('field_id', $field->id)->first();

        return $cell?->getValue() ?? $field->getDefaultValue();
    }

    public function setCellValue(int|string $fieldId, mixed $value): Cell
    {
        $field = $this->resolveField($fieldId);

        \Log::info('setCellValue - field resolved', [
            'input_field_id' => $fieldId,
            'resolved_field_id' => $field->id,
            'field_public_id' => $field->public_id,
            'field_type' => $field->type->value ?? $field->type,
        ]);

        $cell = $this->cells()->firstOrNew(['field_id' => $field->id]);
        $isNew = !$cell->exists;

        \Log::info('setCellValue - cell found/created', [
            'is_new' => $isNew,
            'cell_id' => $cell->id ?? 'new',
        ]);

        $cell->row_id = $this->id;
        $cell->field_id = $field->id;
        // Set relationship to avoid extra query and ensure field is available
        $cell->setRelation('field', $field);
        $cell->setValue($value);

        \Log::info('setCellValue - before save', [
            'value_boolean' => $cell->value_boolean,
            'value_text' => $cell->value_text,
            'dirty' => $cell->getDirty(),
        ]);

        $cell->save();

        \Log::info('setCellValue - after save', [
            'cell_id' => $cell->id,
            'value_boolean' => $cell->fresh()->value_boolean,
        ]);

        return $cell;
    }

    public function getAllValues(): Collection
    {
        $fields = $this->table->fields;
        $cells = $this->cells->keyBy('field_id');

        return $fields->mapWithKeys(function ($field) use ($cells) {
            $cell = $cells->get($field->id);
            return [$field->public_id => $cell?->getValue() ?? $field->getDefaultValue()];
        });
    }

    public function getPrimaryValue(): mixed
    {
        $primaryField = $this->table->getPrimaryField();
        return $primaryField ? $this->getCellValue($primaryField->id) : null;
    }

    public function toArrayWithValues(): array
    {
        return [
            'id' => $this->public_id,
            'values' => $this->getAllValues()->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveField(int|string $fieldId): Field
    {
        // First try to find by public_id (for string IDs like "01KGQEY9T27FV2B5CA4R08HP36")
        if (is_string($fieldId)) {
            $field = Field::where('public_id', $fieldId)->first();
            if ($field) {
                return $field;
            }
        }

        // Fallback to numeric ID
        return Field::findOrFail($fieldId);
    }
}
