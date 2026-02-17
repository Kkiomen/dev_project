<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmPipelineEdge extends Model
{
    use HasFactory;

    protected $table = 'sm_pipeline_edges';

    protected $fillable = [
        'pipeline_id',
        'edge_id',
        'source_node_id',
        'source_handle',
        'target_node_id',
        'target_handle',
    ];

    // Relationships
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(SmPipeline::class, 'pipeline_id');
    }
}
