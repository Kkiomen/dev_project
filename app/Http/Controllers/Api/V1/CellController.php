<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateCellRequest;
use App\Http\Resources\CellResource;
use App\Http\Resources\RowResource;
use App\Models\Row;
use App\Models\Field;
use Illuminate\Http\Request;

class CellController extends Controller
{
    public function update(UpdateCellRequest $request, Row $row, Field $field): CellResource
    {
        $this->authorize('update', $row->table->base);

        // Validate value for field type
        if (!$field->validateValue($request->value)) {
            abort(422, 'The value is not valid for this field type.');
        }

        $cell = $row->setCellValue($field->id, $request->value);

        return new CellResource($cell->load('field', 'attachments'));
    }

    public function bulkUpdate(Request $request, Row $row): RowResource
    {
        $this->authorize('update', $row->table->base);

        $request->validate([
            'values' => 'required|array',
        ]);

        foreach ($request->values as $fieldId => $value) {
            $row->setCellValue($fieldId, $value);
        }

        return new RowResource($row->fresh()->load('cells.field'));
    }
}
