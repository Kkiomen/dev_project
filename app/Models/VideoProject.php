<?php

namespace App\Models;

use App\Enums\VideoProjectStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class VideoProject extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id',
        'brand_id',
        'title',
        'status',
        'original_filename',
        'video_path',
        'output_path',
        'language',
        'language_probability',
        'duration',
        'width',
        'height',
        'caption_style',
        'caption_settings',
        'transcription',
        'video_metadata',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'status' => VideoProjectStatus::class,
        'caption_settings' => 'array',
        'transcription' => 'array',
        'video_metadata' => 'array',
        'language_probability' => 'float',
        'duration' => 'float',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Scopes

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForBrand(Builder $query, Brand $brand): Builder
    {
        return $query->where('brand_id', $brand->id);
    }

    public function scopeWithStatus(Builder $query, VideoProjectStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    // Helpers

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canExport(): bool
    {
        return $this->status->canExport();
    }

    public function markAs(VideoProjectStatus $status): self
    {
        $this->status = $status;
        if ($status === VideoProjectStatus::Completed) {
            $this->completed_at = now();
        }
        $this->save();

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->status = VideoProjectStatus::Failed;
        $this->error_message = $error;
        $this->save();

        return $this;
    }

    public function getSegments(): array
    {
        return $this->transcription['segments'] ?? [];
    }

    public function hasTranscription(): bool
    {
        return !empty($this->transcription['segments']);
    }
}
