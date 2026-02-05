<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevTaskTimeEntryResource;
use App\Models\DevTask;
use App\Models\DevTaskTimeEntry;
use Illuminate\Http\Request;

class DevTaskTimeEntryController extends Controller
{
    public function index(DevTask $task)
    {
        $entries = $task->timeEntries()
            ->with('user')
            ->orderByDesc('started_at')
            ->get();

        return DevTaskTimeEntryResource::collection($entries);
    }

    public function active()
    {
        $entry = DevTaskTimeEntry::with(['user', 'task'])
            ->forUser(auth()->id())
            ->running()
            ->first();

        if (!$entry) {
            return response()->json(['data' => null]);
        }

        return new DevTaskTimeEntryResource($entry);
    }

    public function start(Request $request, DevTask $task)
    {
        $existingEntry = DevTaskTimeEntry::forUser(auth()->id())
            ->running()
            ->first();

        if ($existingEntry) {
            $existingEntry->stop();
        }

        $entry = $task->timeEntries()->create([
            'user_id' => auth()->id(),
            'started_at' => now(),
            'is_running' => true,
        ]);

        $task->logs()->create([
            'type' => 'timer_started',
            'content' => 'Timer started',
            'user_id' => auth()->id(),
            'metadata' => [
                'entry_id' => $entry->public_id,
            ],
        ]);

        $entry->load(['user', 'task']);

        return new DevTaskTimeEntryResource($entry);
    }

    public function stop(Request $request, DevTask $task, DevTaskTimeEntry $entry)
    {
        $this->ensureEntryBelongsToTask($task, $entry);

        if (!$entry->is_running) {
            return response()->json(['message' => 'Timer is not running'], 422);
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:1000',
        ]);

        $entry->stop();

        if (!empty($validated['description'])) {
            $entry->update(['description' => $validated['description']]);
        }

        $task->logs()->create([
            'type' => 'timer_stopped',
            'content' => "Timer stopped ({$entry->formatted_duration})",
            'user_id' => auth()->id(),
            'metadata' => [
                'entry_id' => $entry->public_id,
                'duration_minutes' => $entry->duration_minutes,
            ],
        ]);

        $entry->load(['user', 'task']);

        return new DevTaskTimeEntryResource($entry);
    }

    public function update(Request $request, DevTask $task, DevTaskTimeEntry $entry)
    {
        $this->ensureEntryBelongsToTask($task, $entry);

        $validated = $request->validate([
            'description' => 'nullable|string|max:1000',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['duration_minutes']) && !$entry->is_running) {
            $entry->duration_minutes = $validated['duration_minutes'];
        }

        if (array_key_exists('description', $validated)) {
            $entry->description = $validated['description'];
        }

        $entry->save();
        $entry->load(['user', 'task']);

        return new DevTaskTimeEntryResource($entry);
    }

    public function destroy(DevTask $task, DevTaskTimeEntry $entry)
    {
        $this->ensureEntryBelongsToTask($task, $entry);

        $duration = $entry->formatted_duration;
        $entry->delete();

        $task->logs()->create([
            'type' => 'timer_deleted',
            'content' => "Time entry deleted ({$duration})",
            'user_id' => auth()->id(),
        ]);

        return response()->json(['message' => 'Time entry deleted']);
    }

    public function stats(DevTask $task)
    {
        $totalMinutes = $task->timeEntries()
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');

        $todayMinutes = $task->timeEntries()
            ->today()
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');

        $userMinutes = $task->timeEntries()
            ->forUser(auth()->id())
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');

        return response()->json([
            'total_minutes' => $totalMinutes,
            'today_minutes' => $todayMinutes,
            'user_minutes' => $userMinutes,
            'total_formatted' => $this->formatDuration($totalMinutes),
            'today_formatted' => $this->formatDuration($todayMinutes),
            'user_formatted' => $this->formatDuration($userMinutes),
        ]);
    }

    protected function formatDuration(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $mins);
        }

        return sprintf('%dm', $mins);
    }

    protected function ensureEntryBelongsToTask(DevTask $task, DevTaskTimeEntry $entry): void
    {
        if ($entry->dev_task_id !== $task->id) {
            abort(404, 'Time entry not found for this task');
        }
    }
}
