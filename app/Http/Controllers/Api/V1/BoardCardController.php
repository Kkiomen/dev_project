<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MoveBoardCardRequest;
use App\Http\Requests\Api\StoreBoardCardRequest;
use App\Http\Requests\Api\UpdateBoardCardRequest;
use App\Http\Resources\BoardCardResource;
use App\Models\BoardCard;
use App\Models\BoardColumn;
use Illuminate\Http\Request;

class BoardCardController extends Controller
{
    public function store(StoreBoardCardRequest $request, BoardColumn $column): BoardCardResource
    {
        $this->authorize('update', $column->board);

        $card = $column->cards()->create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return new BoardCardResource($card->load('creator'));
    }

    public function update(UpdateBoardCardRequest $request, BoardCard $card): BoardCardResource
    {
        $this->authorize('update', $card->column->board);

        $card->update($request->validated());

        return new BoardCardResource($card->load('creator'));
    }

    public function destroy(BoardCard $card)
    {
        $this->authorize('update', $card->column->board);

        $card->delete();

        return response()->noContent();
    }

    public function move(MoveBoardCardRequest $request, BoardCard $card)
    {
        $this->authorize('update', $card->column->board);

        $targetColumn = BoardColumn::findByPublicIdOrFail($request->column_id);

        // Verify target column belongs to the same board
        abort_unless(
            $targetColumn->board_id === $card->column->board_id,
            422,
            'Target column must belong to the same board.'
        );

        $oldColumnId = $card->column_id;
        $newPosition = $request->position;

        if ($targetColumn->id === $oldColumnId) {
            // Same column - just reorder
            $card->moveToPosition($newPosition);
        } else {
            // Different column - move card
            // Shift positions in old column
            BoardCard::where('column_id', $oldColumnId)
                ->where('position', '>', $card->position)
                ->decrement('position');

            // Shift positions in new column
            BoardCard::where('column_id', $targetColumn->id)
                ->where('position', '>=', $newPosition)
                ->increment('position');

            $card->update([
                'column_id' => $targetColumn->id,
                'position' => $newPosition,
            ]);
        }

        return new BoardCardResource($card->fresh()->load('creator'));
    }

    public function reorder(Request $request, BoardCard $card)
    {
        $this->authorize('update', $card->column->board);

        $request->validate(['position' => 'required|integer|min:0']);

        $card->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }
}
