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

        $cell = $this->cells()->firstOrNew(['field_id' => $field->id]);
        $cell->row_id = $this->id;
        $cell->field_id = $field->id;
        // Set relationship to avoid extra query and ensure field is available
        $cell->setRelation('field', $field);
        $cell->setValue($value);
        $cell->save();

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
