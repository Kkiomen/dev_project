<?php

namespace App\Models;

use App\Enums\PipelineNodeType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmPipelineNode extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_pipeline_nodes';

    protected $fillable = [
        'pipeline_id',
        'node_id',
        'type',
        'label',
        'position',
        'config',
        'data',
    ];

    protected $casts = [
        'type' => PipelineNodeType::class,
        'position' => 'array',
        'config' => 'array',
        'data' => 'array',
    ];

    // Relationships
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(SmPipeline::class, 'pipeline_id');
    }
}
