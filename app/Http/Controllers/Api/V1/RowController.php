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

        $query = $table->rows()->with('cells.field');

        // Apply filters
        $filters = $request->getFilters();
        if (!empty($filters)) {
            $query = $this->filterService->apply($query, $table, $filters);
        }

        // Apply sorting
        $sort = $request->getSort();
        $query = $this->sortService->apply($query, $table, $sort);

        $rows = $query->paginate($request->getPerPage());

        return RowResource::collection($rows);
    }

    public function store(StoreRowRequest $request, Table $table): RowResource
    {
        $this->authorize('update', $table->base);

        $row = $table->rows()->create();

        // Set cell values
        foreach ($request->validated('values', []) as $fieldId => $value) {
            $row->setCellValue($fieldId, $value);
        }

        return new RowResource($row->load('cells.field'));
    }

    public function show(Row $row): RowResource
    {
        $this->authorize('view', $row->table->base);

        return new RowResource($row->load('cells.field', 'cells.attachments'));
    }

    public function update(UpdateRowRequest $request, Row $row): RowResource
    {
        $this->authorize('update', $row->table->base);

        // Update position if provided
        if ($request->has('position')) {
            $row->update(['position' => $request->position]);
        }

        // Update cell values
        foreach ($request->validated('values', []) as $fieldId => $value) {
            $row->setCellValue($fieldId, $value);
        }

        return new RowResource($row->fresh()->load('cells.field'));
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

        $rows = collect($request->rows)->map(function ($rowData) use ($table) {
            $row = $table->rows()->create();

            foreach ($rowData['values'] ?? [] as $fieldId => $value) {
                $row->setCellValue($fieldId, $value);
            }

            return $row->load('cells.field');
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
