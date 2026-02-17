<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmPipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'canvas_state' => $this->canvas_state,
            'status' => $this->status,
            'thumbnail_url' => $this->getThumbnailUrl(),
            'nodes' => $this->whenLoaded('nodes', fn () => $this->nodes->map(fn ($node) => [
                'id' => $node->public_id,
                'node_id' => $node->node_id,
                'type' => $node->type->value,
                'label' => $node->label,
                'position' => $node->position,
                'config' => $node->config,
                'data' => $node->data,
            ])),
            'edges' => $this->whenLoaded('edges', fn () => $this->edges->map(fn ($edge) => [
                'id' => $edge->id,
                'edge_id' => $edge->edge_id,
                'source_node_id' => $edge->source_node_id,
                'source_handle' => $edge->source_handle,
                'target_node_id' => $edge->target_node_id,
                'target_handle' => $edge->target_handle,
            ])),
            'last_run' => $this->whenLoaded('runs', function () {
                $lastRun = $this->runs->sortByDesc('created_at')->first();
                return $lastRun ? new SmPipelineRunResource($lastRun) : null;
            }),
            'runs_count' => $this->whenCounted('runs'),
            'nodes_count' => $this->whenCounted('nodes'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
