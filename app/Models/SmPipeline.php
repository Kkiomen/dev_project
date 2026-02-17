<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmPipeline extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $table = 'sm_pipelines';

    protected $fillable = [
        'brand_id',
        'name',
        'description',
        'canvas_state',
        'status',
        'thumbnail_path',
    ];

    protected $casts = [
        'canvas_state' => 'array',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(SmPipelineNode::class, 'pipeline_id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(SmPipelineEdge::class, 'pipeline_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SmPipelineRun::class, 'pipeline_id');
    }

    // Scopes
    public function scopeForBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    // Helpers
    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null;
    }

    public function lastRun(): ?SmPipelineRun
    {
        return $this->runs()->latest()->first();
    }

    public function hasOutputNode(): bool
    {
        return $this->nodes()->where('type', 'output')->exists();
    }
}
