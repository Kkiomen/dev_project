<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostMedia extends Model
{
    use HasFactory, HasPublicId, HasPosition;

    protected $table = 'post_media';

    protected $fillable = [
        'social_post_id',
        'type',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'thumbnail_path',
        'position',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function getPositionGroupColumn(): string
    {
        return 'social_post_id';
    }

    // Relationships
    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    // Accessors
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->thumbnail_path);
    }

    public function getFileSizeForHumansAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Helper methods
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function getAspectRatio(): ?float
    {
        if (!$this->width || !$this->height) {
            return null;
        }

        return $this->width / $this->height;
    }

    public function delete(): bool
    {
        // Delete files from storage
        if ($this->path) {
            Storage::disk($this->disk)->delete($this->path);
        }

        if ($this->thumbnail_path) {
            Storage::disk($this->disk)->delete($this->thumbnail_path);
        }

        return parent::delete();
    }
}
