<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevTaskLogResource;
use App\Models\DevTask;
use Illuminate\Http\Request;

class DevTaskLogController extends Controller
{
    public function index(Request $request, DevTask $task)
    {
        $query = $task->logs()->with('user');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $logs = $query->latest()->paginate($request->input('per_page', 20));

        return DevTaskLogResource::collection($logs);
    }

    public function store(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'type' => 'required|in:comment',
            'content' => 'required|string',
        ]);

        $log = $task->logs()->create([
            'type' => $validated['type'],
            'content' => $validated['content'],
            'user_id' => auth()->id(),
        ]);

        $log->load('user');

        return new DevTaskLogResource($log);
    }
}
