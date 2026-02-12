<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmGeneratedAsset extends Model
{
    use HasFactory, HasPublicId;

    protected $table = 'sm_generated_assets';

    protected $fillable = [
        'brand_id',
        'social_post_id',
        'sm_design_template_id',
        'type',
        'file_path',
        'thumbnail_path',
        'disk',
        'width',
        'height',
        'mime_type',
        'file_size',
        'generation_prompt',
        'ai_provider',
        'ai_model',
        'generation_params',
        'status',
        'error_message',
        'position',
    ];

    protected $casts = [
        'generation_params' => 'array',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'position' => 'integer',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function designTemplate(): BelongsTo
    {
        return $this->belongsTo(SmDesignTemplate::class, 'sm_design_template_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Helpers
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getUrl(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null;
    }

    public function markAsGenerating(): self
    {
        $this->status = 'generating';
        $this->save();

        return $this;
    }

    public function markAsCompleted(string $filePath, int $width = null, int $height = null): self
    {
        $this->status = 'completed';
        $this->file_path = $filePath;
        $this->width = $width;
        $this->height = $height;
        $this->save();

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->status = 'failed';
        $this->error_message = $error;
        $this->save();

        return $this;
    }
}
