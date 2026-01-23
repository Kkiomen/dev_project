<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\FieldType;
use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function show(Request $request, Table $table)
    {
        $this->authorize('view', $table->base);

        $table->load(['base.tables', 'fields', 'rows.cells.field']);

        $fieldTypes = FieldType::options();

        // Format data for Alpine.js
        $tableData = [
            'id' => $table->public_id,
            'name' => $table->name,
            'base_id' => $table->base->public_id,
        ];

        $fieldsData = $table->fields->map(fn($field) => [
            'id' => $field->public_id,
            'name' => $field->name,
            'type' => $field->type->value,
            'type_label' => $field->type->label(),
            'type_icon' => $field->type->icon(),
            'options' => $field->options,
            'is_required' => $field->is_required,
            'is_primary' => $field->is_primary,
            'width' => $field->width,
        ])->values();

        $rowsData = $table->rows->map(function ($row) use ($table) {
            $values = [];
            $cells = $row->cells->keyBy('field_id');

            foreach ($table->fields as $field) {
                $cell = $cells->get($field->id);
                $values[$field->public_id] = $cell?->getValue() ?? $field->getDefaultValue();
            }

            return [
                'id' => $row->public_id,
                'values' => $values,
            ];
        })->values();

        return view('tables.show', compact('table', 'tableData', 'fieldsData', 'rowsData', 'fieldTypes'));
    }

    public function kanban(Request $request, Table $table)
    {
        $this->authorize('view', $table->base);

        $table->load(['base.tables', 'fields', 'rows.cells.field']);

        // Get select/multi_select fields for kanban grouping
        $selectFields = $table->fields->filter(fn($f) => in_array($f->type->value, ['select', 'multi_select']));

        // Default to first select field or null
        $groupByFieldId = $request->get('group_by') ?? $selectFields->first()?->public_id;
        $groupByField = $groupByFieldId
            ? $table->fields->firstWhere('public_id', $groupByFieldId)
            : null;

        $fieldTypes = FieldType::options();

        // Format data for Alpine.js
        $tableData = [
            'id' => $table->public_id,
            'name' => $table->name,
            'base_id' => $table->base->public_id,
        ];

        $fieldsData = $table->fields->map(fn($field) => [
            'id' => $field->public_id,
            'name' => $field->name,
            'type' => $field->type->value,
            'type_label' => $field->type->label(),
            'options' => $field->options,
            'is_primary' => $field->is_primary,
            'width' => $field->width,
        ])->values();

        $rowsData = $table->rows->map(function ($row) use ($table) {
            $values = [];
            $cells = $row->cells->keyBy('field_id');

            foreach ($table->fields as $field) {
                $cell = $cells->get($field->id);
                $values[$field->public_id] = $cell?->getRawValue() ?? null;
            }

            return [
                'id' => $row->public_id,
                'values' => $values,
            ];
        })->values();

        return view('tables.kanban', compact(
            'table',
            'tableData',
            'fieldsData',
            'rowsData',
            'fieldTypes',
            'selectFields',
            'groupByField'
        ));
    }
}
