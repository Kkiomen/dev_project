<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevTaskSubtaskResource;
use App\Models\DevTask;
use App\Models\DevTaskSubtask;
use Illuminate\Http\Request;

class DevTaskSubtaskController extends Controller
{
    public function index(DevTask $task)
    {
        $subtasks = $task->subtasks()
            ->with('completedByUser')
            ->ordered()
            ->get();

        return DevTaskSubtaskResource::collection($subtasks);
    }

    public function store(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $maxPosition = $task->subtasks()->max('position') ?? -1;

        $subtask = $task->subtasks()->create([
            'title' => $validated['title'],
            'position' => $maxPosition + 1,
        ]);

        $task->logs()->create([
            'type' => 'subtask_added',
            'content' => "Subtask added: {$subtask->title}",
            'user_id' => auth()->id(),
            'metadata' => [
                'subtask_id' => $subtask->public_id,
                'title' => $subtask->title,
            ],
        ]);

        return new DevTaskSubtaskResource($subtask);
    }

    public function update(Request $request, DevTask $task, DevTaskSubtask $subtask)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $subtask->update($validated);

        return new DevTaskSubtaskResource($subtask);
    }

    public function toggle(DevTask $task, DevTaskSubtask $subtask)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $subtask->toggle();

        $task->logs()->create([
            'type' => $subtask->is_completed ? 'subtask_completed' : 'subtask_uncompleted',
            'content' => $subtask->is_completed
                ? "Subtask completed: {$subtask->title}"
                : "Subtask uncompleted: {$subtask->title}",
            'user_id' => auth()->id(),
            'metadata' => [
                'subtask_id' => $subtask->public_id,
                'title' => $subtask->title,
                'is_completed' => $subtask->is_completed,
            ],
        ]);

        $subtask->load('completedByUser');

        return new DevTaskSubtaskResource($subtask);
    }

    public function destroy(DevTask $task, DevTaskSubtask $subtask)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $title = $subtask->title;
        $subtask->delete();

        $task->logs()->create([
            'type' => 'subtask_deleted',
            'content' => "Subtask deleted: {$title}",
            'user_id' => auth()->id(),
            'metadata' => [
                'title' => $title,
            ],
        ]);

        return response()->json(['message' => 'Subtask deleted']);
    }

    public function reorder(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'subtask_ids' => 'required|array',
            'subtask_ids.*' => 'required|string',
        ]);

        foreach ($validated['subtask_ids'] as $position => $publicId) {
            DevTaskSubtask::where('dev_task_id', $task->id)
                ->where('public_id', $publicId)
                ->update(['position' => $position]);
        }

        return DevTaskSubtaskResource::collection(
            $task->subtasks()->ordered()->get()
        );
    }

    protected function ensureSubtaskBelongsToTask(DevTask $task, DevTaskSubtask $subtask): void
    {
        if ($subtask->dev_task_id !== $task->id) {
            abort(404, 'Subtask not found for this task');
        }
    }
}
