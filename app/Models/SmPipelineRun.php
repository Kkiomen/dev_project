<?php

namespace App\Models;

use App\Enums\PipelineRunStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmPipelineRun extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_pipeline_runs';

    protected $fillable = [
        'pipeline_id',
        'status',
        'input_data',
        'node_results',
        'output_data',
        'output_path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => PipelineRunStatus::class,
        'input_data' => 'array',
        'node_results' => 'array',
        'output_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(SmPipeline::class, 'pipeline_id');
    }

    // Helpers
    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function getOutputUrl(): ?string
    {
        return $this->output_path ? asset('storage/' . $this->output_path) : null;
    }
}
