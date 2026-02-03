<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBoardRequest;
use App\Http\Requests\Api\UpdateBoardSettingsRequest;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $brand = $request->user()->getCurrentBrand();

        if (!$brand) {
            return BoardResource::collection(collect());
        }

        $boards = $brand->boards()
            ->withCount(['columns', 'cards'])
            ->latest()
            ->paginate($request->get('per_page', 20));

        return BoardResource::collection($boards);
    }

    public function store(StoreBoardRequest $request): BoardResource
    {
        $brand = $request->user()->getCurrentBrand();

        abort_unless($brand, 400, 'No active brand selected.');
        abort_unless($brand->canUserEdit($request->user()), 403);

        $board = $brand->boards()->create($request->validated());

        // Create default columns
        $board->columns()->createMany([
            ['name' => 'To Do', 'color' => '#6B7280', 'position' => 0],
            ['name' => 'In Progress', 'color' => '#F59E0B', 'position' => 1],
            ['name' => 'Done', 'color' => '#10B981', 'position' => 2],
        ]);

        return new BoardResource($board->load('columns.cards'));
    }

    public function show(Request $request, Board $board): BoardResource
    {
        $this->authorize('view', $board);

        $board->load(['columns.cards.creator']);

        return new BoardResource($board);
    }

    public function update(UpdateBoardSettingsRequest $request, Board $board): BoardResource
    {
        $this->authorize('update', $board);

        $board->update($request->validated());

        return new BoardResource($board);
    }

    public function destroy(Board $board)
    {
        $this->authorize('delete', $board);

        $board->delete();

        return response()->noContent();
    }
}
