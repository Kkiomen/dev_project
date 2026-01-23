<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Cell;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function __construct(
        private AttachmentService $attachmentService
    ) {}

    public function store(StoreAttachmentRequest $request, Cell $cell): AttachmentResource
    {
        $this->authorize('update', $cell->row->table->base);

        $attachment = $this->attachmentService->upload(
            $request->file('file'),
            $cell
        );

        // Add ID to cell's value_json
        $currentIds = $cell->value_json ?? [];
        $currentIds[] = $attachment->public_id;
        $cell->update(['value_json' => $currentIds]);

        return new AttachmentResource($attachment);
    }

    public function destroy(Attachment $attachment)
    {
        $this->authorize('delete', $attachment->cell->row->table->base);

        // Remove ID from cell's value_json
        $cell = $attachment->cell;
        $currentIds = collect($cell->value_json ?? [])
            ->reject(fn($id) => $id === $attachment->public_id)
            ->values()
            ->toArray();
        $cell->update(['value_json' => $currentIds ?: null]);

        $attachment->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Attachment $attachment)
    {
        $this->authorize('update', $attachment->cell->row->table->base);

        $request->validate(['position' => 'required|integer|min:0']);

        $attachment->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }
}
