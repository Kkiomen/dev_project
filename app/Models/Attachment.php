<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory, HasPublicId, HasPosition;

    protected $fillable = [
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'thumbnail_path',
        'metadata',
        'position',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = ['url', 'thumbnail_url', 'is_image'];

    protected function getPositionGroupColumn(): string
    {
        return 'cell_id';
    }

    // Relationships
    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
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

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getExtensionAttribute(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    // Methods
    public function deleteFiles(): void
    {
        Storage::disk($this->disk)->delete($this->path);

        if ($this->thumbnail_path) {
            Storage::disk($this->disk)->delete($this->thumbnail_path);
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            $attachment->deleteFiles();
        });
    }
}
