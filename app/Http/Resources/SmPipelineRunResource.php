<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmPipelineRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'input_data' => $this->input_data,
            'node_results' => $this->node_results,
            'output_data' => $this->output_data,
            'output_url' => $this->getOutputUrl(),
            'error_message' => $this->error_message,
            'is_processing' => $this->isProcessing(),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
