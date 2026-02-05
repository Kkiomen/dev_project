<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevTaskProjectResource;
use App\Http\Resources\DevTaskResource;
use App\Models\DevTask;
use App\Models\DevTaskProject;
use App\Services\BotIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevTaskController extends Controller
{
    public function __construct(
        protected BotIntegrationService $botService
    ) {}

    public function index(Request $request)
    {
        $query = DevTask::query()
            ->with(['creator', 'assignee'])
            ->withCount('logs');

        if ($project = $request->input('project')) {
            $query->where('project', $project);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('identifier', 'like', "%{$search}%")
                    ->orWhere('pm_description', 'like', "%{$search}%");
            });
        }

        $tasks = $query->orderBy('status')
            ->orderBy('position')
            ->get();

        return DevTaskResource::collection($tasks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project' => 'required|string|exists:dev_task_projects,prefix',
            'title' => 'required|string|max:255',
            'pm_description' => 'nullable|string',
            'tech_description' => 'nullable|string',
            'implementation_plan' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DevTask::STATUSES),
            'priority' => 'nullable|in:' . implode(',', DevTask::PRIORITIES),
            'assigned_to' => 'nullable|exists:users,id',
            'labels' => 'nullable|array',
            'estimated_hours' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
        ]);

        $task = DevTask::create([
            ...$validated,
            'created_by' => auth()->id(),
            'status' => $validated['status'] ?? DevTask::STATUS_BACKLOG,
            'priority' => $validated['priority'] ?? DevTask::PRIORITY_MEDIUM,
        ]);

        $task->load(['creator', 'assignee']);

        return new DevTaskResource($task);
    }

    public function show(DevTask $task)
    {
        $task->load(['creator', 'assignee']);
        $task->loadCount('logs');

        return new DevTaskResource($task);
    }

    public function update(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'pm_description' => 'nullable|string',
            'tech_description' => 'nullable|string',
            'implementation_plan' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DevTask::STATUSES),
            'priority' => 'nullable|in:' . implode(',', DevTask::PRIORITIES),
            'assigned_to' => 'nullable|exists:users,id',
            'labels' => 'nullable|array',
            'estimated_hours' => 'nullable|integer|min:0',
            'actual_hours' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);
        $task->load(['creator', 'assignee']);
        $task->loadCount('logs');

        return new DevTaskResource($task);
    }

    public function destroy(DevTask $task)
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    public function move(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', DevTask::STATUSES),
            'position' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($task, $validated) {
            $oldStatus = $task->status;
            $newStatus = $validated['status'];

            if ($oldStatus !== $newStatus) {
                DevTask::where('status', $oldStatus)
                    ->where('position', '>', $task->position)
                    ->decrement('position');

                $newPosition = $validated['position']
                    ?? DevTask::where('status', $newStatus)->max('position') + 1;

                DevTask::where('status', $newStatus)
                    ->where('position', '>=', $newPosition)
                    ->increment('position');

                $task->update([
                    'status' => $newStatus,
                    'position' => $newPosition,
                ]);
            } elseif (isset($validated['position'])) {
                $task->moveToPosition($validated['position']);
            }
        });

        $task->load(['creator', 'assignee']);
        $task->loadCount('logs');

        return new DevTaskResource($task);
    }

    public function reorder(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'position' => 'required|integer|min:0',
        ]);

        $task->moveToPosition($validated['position']);
        $task->load(['creator', 'assignee']);

        return new DevTaskResource($task);
    }

    public function triggerBot(DevTask $task)
    {
        $task->logs()->create([
            'type' => 'bot_trigger',
            'content' => 'Bot triggered for task implementation',
            'user_id' => auth()->id(),
            'metadata' => [
                'triggered_at' => now()->toIso8601String(),
            ],
        ]);

        $result = $this->botService->triggerBot($task);

        $task->logs()->create([
            'type' => 'bot_response',
            'content' => $result['success'] ? 'Bot responded successfully' : 'Bot request failed',
            'user_id' => auth()->id(),
            'success' => $result['success'],
            'metadata' => [
                'status_code' => $result['status_code'],
                'response' => $result['body'],
            ],
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Bot triggered successfully' : 'Bot trigger failed',
            'data' => $result['body'],
        ]);
    }

    public function generatePlan(DevTask $task)
    {
        $task->logs()->create([
            'type' => 'plan_generation',
            'content' => 'Implementation plan generation started',
            'user_id' => auth()->id(),
            'metadata' => [
                'started_at' => now()->toIso8601String(),
            ],
        ]);

        $result = $this->botService->generatePlan($task);

        if ($result['success'] && isset($result['body']['plan'])) {
            $task->update([
                'implementation_plan' => $result['body']['plan'],
            ]);
        }

        $task->logs()->create([
            'type' => 'plan_generation',
            'content' => $result['success'] ? 'Plan generated successfully' : 'Plan generation failed',
            'user_id' => auth()->id(),
            'success' => $result['success'],
            'metadata' => [
                'status_code' => $result['status_code'],
                'response' => $result['body'],
            ],
        ]);

        $task->load(['creator', 'assignee']);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Plan generated successfully' : 'Plan generation failed',
            'task' => new DevTaskResource($task),
        ]);
    }

    public function projects()
    {
        $projects = DevTaskProject::withCount('tasks')->get();

        return DevTaskProjectResource::collection($projects);
    }

    public function createProject(Request $request)
    {
        $validated = $request->validate([
            'prefix' => 'required|string|max:10|unique:dev_task_projects,prefix|regex:/^[A-Z]+$/',
            'name' => 'required|string|max:255',
        ]);

        $project = DevTaskProject::create($validated);

        return new DevTaskProjectResource($project);
    }
}
