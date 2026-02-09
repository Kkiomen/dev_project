<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UploadedImage extends Model
{
    use HasPublicId;

    protected $fillable = [
        'user_id',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getBase64Attribute(): string
    {
        $contents = Storage::disk($this->disk)->get($this->path);

        return 'data:' . $this->mime_type . ';base64,' . base64_encode($contents);
    }
}
