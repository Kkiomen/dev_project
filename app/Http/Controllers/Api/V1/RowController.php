<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FilterRowsRequest;
use App\Http\Requests\Api\StoreRowRequest;
use App\Http\Requests\Api\UpdateRowRequest;
use App\Http\Resources\RowResource;
use App\Models\Table;
use App\Models\Row;
use App\Services\RowFilterService;
use App\Services\RowSortService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RowController extends Controller
{
    public function __construct(
        protected RowFilterService $filterService,
        protected RowSortService $sortService,
    ) {}

    public function index(FilterRowsRequest $request, Table $table): AnonymousResourceCollection
    {
        $this->authorize('view', $table->base);

        // Eager load table fields (needed for new columns without cells)
        $table->load('fields');

        $query = $table->rows()->with(['cells.field']);

        // Apply filters
        $filters = $request->getFilters();
        if (!empty($filters)) {
            $query = $this->filterService->apply($query, $table, $filters);
        }

        // Apply sorting
        $sort = $request->getSort();
        $query = $this->sortService->apply($query, $table, $sort);

        $rows = $query->paginate($request->getPerPage());

        // Set table relationship with preloaded fields on each row
        foreach ($rows as $row) {
            $row->setRelation('table', $table);
        }

        return RowResource::collection($rows);
    }

    public function store(StoreRowRequest $request, Table $table): RowResource
    {
        $this->authorize('update', $table->base);

        // Ensure table has all fields loaded
        $table->load('fields');

        $row = $table->rows()->create();

        // Set cell values
        foreach ($request->validated('values', []) as $fieldId => $value) {
            $row->setCellValue($fieldId, $value);
        }

        // Load cells and set table relationship directly (with preloaded fields)
        $row->load(['cells.field']);
        $row->setRelation('table', $table);

        return new RowResource($row);
    }

    public function show(Row $row): RowResource
    {
        $this->authorize('view', $row->table->base);

        // Load table with all fields
        $table = $row->table;
        $table->load('fields');

        $row->load(['cells.field', 'cells.attachments']);
        $row->setRelation('table', $table);

        return new RowResource($row);
    }

    public function update(UpdateRowRequest $request, Row $row): RowResource
    {
        $this->authorize('update', $row->table->base);

        // Load table with all fields
        $table = $row->table;
        $table->load('fields');

        // Update position if provided
        if ($request->has('position')) {
            $row->update(['position' => $request->position]);
        }

        // Update cell values (use getValues() to preserve boolean false)
        foreach ($request->getValues() as $fieldId => $value) {
            $row->setCellValue($fieldId, $value);
        }

        $row = $row->fresh();
        $row->load(['cells.field']);
        $row->setRelation('table', $table);

        return new RowResource($row);
    }

    public function destroy(Row $row)
    {
        $this->authorize('delete', $row->table->base);

        $row->delete();

        return response()->noContent();
    }

    public function bulkCreate(Request $request, Table $table): AnonymousResourceCollection
    {
        $this->authorize('update', $table->base);

        $request->validate([
            'rows' => 'required|array|min:1|max:100',
            'rows.*.values' => 'nullable|array',
        ]);

        // Eager load table fields for new rows
        $table->load('fields');

        $rows = collect($request->rows)->map(function ($rowData) use ($table) {
            $row = $table->rows()->create();

            foreach ($rowData['values'] ?? [] as $fieldId => $value) {
                $row->setCellValue($fieldId, $value);
            }

            // Load cells and set table relationship directly (with preloaded fields)
            $row->load(['cells.field']);
            $row->setRelation('table', $table);

            return $row;
        });

        return RowResource::collection($rows);
    }

    public function bulkDelete(Request $request, Table $table)
    {
        $this->authorize('delete', $table->base);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $table->rows()
            ->whereIn('public_id', $request->ids)
            ->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Row $row)
    {
        $this->authorize('update', $row->table->base);

        $request->validate(['position' => 'required|integer|min:0']);

        $row->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }
}
