<?php

namespace App\Services;

use App\Models\UploadedImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageUploadService
{
    protected string $disk = 'public';
    protected string $basePath = 'uploaded-images';

    public function uploadBase64(string $base64Data, User $user): UploadedImage
    {
        $imageData = $this->decodeBase64($base64Data);
        $extension = $this->getExtensionFromMime($imageData['mime_type']);
        $filename = Str::ulid() . '.' . $extension;
        $path = "{$this->basePath}/{$user->id}/{$filename}";

        Storage::disk($this->disk)->put($path, $imageData['content']);

        $image = Image::read($imageData['content']);
        $width = $image->width();
        $height = $image->height();

        return UploadedImage::create([
            'user_id' => $user->id,
            'filename' => $filename,
            'path' => $path,
            'disk' => $this->disk,
            'mime_type' => $imageData['mime_type'],
            'size' => strlen($imageData['content']),
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function delete(UploadedImage $image): void
    {
        Storage::disk($image->disk)->delete($image->path);
        $image->delete();
    }

    protected function decodeBase64(string $base64Data): array
    {
        if (preg_match('/^data:(image\/[a-zA-Z+]+);base64,(.+)$/', $base64Data, $matches)) {
            return [
                'mime_type' => $matches[1],
                'content' => base64_decode($matches[2]),
            ];
        }

        $content = base64_decode($base64Data);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);

        return [
            'mime_type' => $mimeType,
            'content' => $content,
        ];
    }

    protected function getExtensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }
}
