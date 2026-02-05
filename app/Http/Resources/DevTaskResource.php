<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'identifier' => $this->identifier,
            'project' => $this->project,
            'sequence_number' => $this->sequence_number,
            'title' => $this->title,
            'pm_description' => $this->pm_description,
            'tech_description' => $this->tech_description,
            'implementation_plan' => $this->implementation_plan,
            'status' => $this->status,
            'position' => $this->position,
            'priority' => $this->priority,
            'labels' => $this->labels ?? [],
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'due_date' => $this->due_date?->toIso8601String(),
            'is_overdue' => $this->is_overdue,
            'is_due_soon' => $this->is_due_soon,
            'subtask_progress' => $this->subtask_progress,
            'total_time_spent' => $this->total_time_spent,
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'assigned_to' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null,
            'logs_count' => $this->whenCounted('logs'),
            'subtasks_count' => $this->whenCounted('subtasks'),
            'attachments_count' => $this->whenCounted('attachments'),
            'subtasks' => DevTaskSubtaskResource::collection($this->whenLoaded('subtasks')),
            'attachments' => DevTaskAttachmentResource::collection($this->whenLoaded('attachments')),
            'time_entries' => DevTaskTimeEntryResource::collection($this->whenLoaded('timeEntries')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
