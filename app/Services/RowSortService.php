<?php

namespace App\Services;

use App\Enums\FieldType;
use App\Models\Field;
use App\Models\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RowSortService
{
    /**
     * Apply sorting to a rows query.
     */
    public function apply(Builder|HasMany $query, Table $table, array $sortData): Builder|HasMany
    {
        if (empty($sortData)) {
            // Default ordering if no sort specified
            return $query->ordered();
        }

        // Clear any existing orders (e.g., from HasPosition trait)
        $query->reorder();

        // Cache fields for lookup
        $fields = $table->fields->keyBy('public_id');

        $hasSortApplied = false;

        foreach ($sortData as $sort) {
            $fieldId = $sort['field_id'] ?? null;
            $direction = strtolower($sort['direction'] ?? 'asc');

            if (!$fieldId) {
                continue;
            }

            // Validate direction
            if (!in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }

            $field = $fields->get($fieldId);
            if (!$field) {
                continue;
            }

            $this->applySortByField($query, $field, $direction);
            $hasSortApplied = true;
        }

        // Add default position ordering as secondary sort for stability
        $query->orderBy('rows.position', 'asc');

        return $query;
    }

    /**
     * Apply sorting by a specific field using a subquery.
     */
    protected function applySortByField(Builder|HasMany $query, Field $field, string $direction): void
    {
        $valueColumn = $field->type->valueColumn();
        $fieldId = $field->id;

        // Build the sort expression based on field type
        $sortExpression = $this->getSortExpression($field, $valueColumn);

        // NULLS handling: nulls go last for ASC, first for DESC
        // MySQL doesn't have native NULLS FIRST/LAST, so we use a CASE expression
        if ($direction === 'asc') {
            // For ASC: nulls last (sort nulls as 1, non-nulls as 0)
            $query->orderByRaw(
                "CASE WHEN ({$sortExpression}) IS NULL THEN 1 ELSE 0 END ASC"
            );
        } else {
            // For DESC: nulls first (sort nulls as 0, non-nulls as 1)
            $query->orderByRaw(
                "CASE WHEN ({$sortExpression}) IS NULL THEN 0 ELSE 1 END ASC"
            );
        }

        // Then sort by actual value
        $query->orderByRaw("({$sortExpression}) {$direction}");
    }

    /**
     * Get the SQL expression for sorting based on field type.
     */
    protected function getSortExpression(Field $field, string $valueColumn): string
    {
        $fieldId = $field->id;

        $baseSubquery = "(SELECT cells.{$valueColumn} FROM cells WHERE cells.row_id = rows.id AND cells.field_id = {$fieldId} LIMIT 1)";

        // For SELECT type, extract the name from JSON for proper alphabetical sorting
        if ($field->type === FieldType::SELECT) {
            return "(SELECT JSON_UNQUOTE(JSON_EXTRACT(cells.{$valueColumn}, '$.name')) FROM cells WHERE cells.row_id = rows.id AND cells.field_id = {$fieldId} LIMIT 1)";
        }

        // For MULTI_SELECT, sort by count of items
        if ($field->type === FieldType::MULTI_SELECT) {
            return "(SELECT JSON_LENGTH(cells.{$valueColumn}) FROM cells WHERE cells.row_id = rows.id AND cells.field_id = {$fieldId} LIMIT 1)";
        }

        return $baseSubquery;
    }
}
