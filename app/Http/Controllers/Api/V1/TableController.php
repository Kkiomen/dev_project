<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTableRequest;
use App\Http\Requests\Api\UpdateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Base;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TableController extends Controller
{
    public function index(Request $request, Base $base): AnonymousResourceCollection
    {
        $this->authorize('view', $base);

        $tables = $base->tables()
            ->withCount(['fields', 'rows'])
            ->ordered()
            ->get();

        return TableResource::collection($tables);
    }

    public function store(StoreTableRequest $request, Base $base): TableResource
    {
        $this->authorize('update', $base);

        $table = $base->createTable(
            $request->validated('name'),
            $request->validated('description')
        );

        return new TableResource($table->load('fields'));
    }

    public function show(Request $request, Table $table): TableResource
    {
        $this->authorize('view', $table->base);

        $table->load(['fields', 'rows.cells.field']);

        return new TableResource($table);
    }

    public function update(UpdateTableRequest $request, Table $table): TableResource
    {
        $this->authorize('update', $table->base);

        $table->update($request->validated());

        return new TableResource($table);
    }

    public function destroy(Table $table)
    {
        $this->authorize('delete', $table->base);

        $table->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Table $table)
    {
        $this->authorize('update', $table->base);

        $request->validate(['position' => 'required|integer|min:0']);

        $table->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }
}
