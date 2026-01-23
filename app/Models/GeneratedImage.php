<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GeneratedImage extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'modifications',
        'file_path',
        'file_size',
        'generated_at',
    ];

    protected $casts = [
        'modifications' => 'array',
        'file_size' => 'integer',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    // Accessors
    public function getUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    // Boot method for cleanup
    protected static function booted(): void
    {
        static::deleting(function (GeneratedImage $image) {
            if ($image->file_path) {
                Storage::delete($image->file_path);
            }
        });
    }
}
