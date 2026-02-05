<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DevTaskAttachment extends Model
{
    use HasPublicId;

    protected $fillable = [
        'dev_task_id',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'thumbnail_path',
        'uploaded_by',
        'position',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
    ];

    protected $appends = ['url', 'thumbnail_url', 'is_image'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DevTask::class, 'dev_task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

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

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    protected static function booted(): void
    {
        static::deleting(function (DevTaskAttachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);

            if ($attachment->thumbnail_path) {
                Storage::disk($attachment->disk)->delete($attachment->thumbnail_path);
            }
        });
    }
}
