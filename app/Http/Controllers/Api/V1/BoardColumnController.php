<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBoardColumnRequest;
use App\Http\Requests\Api\UpdateBoardColumnRequest;
use App\Http\Resources\BoardColumnResource;
use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Http\Request;

class BoardColumnController extends Controller
{
    public function store(StoreBoardColumnRequest $request, Board $board): BoardColumnResource
    {
        $this->authorize('update', $board);

        $column = $board->columns()->create($request->validated());

        return new BoardColumnResource($column->load('cards'));
    }

    public function update(UpdateBoardColumnRequest $request, BoardColumn $column): BoardColumnResource
    {
        $this->authorize('update', $column->board);

        $column->update($request->validated());

        return new BoardColumnResource($column);
    }

    public function destroy(BoardColumn $column)
    {
        $this->authorize('update', $column->board);

        $column->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, BoardColumn $column)
    {
        $this->authorize('update', $column->board);

        $request->validate(['position' => 'required|integer|min:0']);

        $column->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }
}
