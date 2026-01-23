<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Cell;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class AttachmentService
{
    private string $disk = 'public';
    private int $thumbnailWidth = 200;
    private int $thumbnailHeight = 200;

    public function upload(UploadedFile $file, Cell $cell): Attachment
    {
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate unique path
        $baseId = $cell->row->table->base_id;
        $tableId = $cell->row->table_id;
        $basePath = "attachments/{$baseId}/{$tableId}";
        $uniqueName = Str::ulid() . '.' . $extension;
        $path = "{$basePath}/{$uniqueName}";

        // Store file
        Storage::disk($this->disk)->put($path, file_get_contents($file));

        $attachmentData = [
            'filename' => $filename,
            'path' => $path,
            'disk' => $this->disk,
            'mime_type' => $mimeType,
            'size' => $size,
        ];

        // For images - generate thumbnail and get dimensions
        if (str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'svg')) {
            $imageData = $this->processImage($file, $basePath, $uniqueName);
            $attachmentData = array_merge($attachmentData, $imageData);
        }

        return $cell->attachments()->create($attachmentData);
    }

    private function processImage(UploadedFile $file, string $basePath, string $filename): array
    {
        $image = Image::read($file);

        $data = [
            'width' => $image->width(),
            'height' => $image->height(),
        ];

        // Generate thumbnail
        $thumbnail = $image->cover($this->thumbnailWidth, $this->thumbnailHeight);
        $thumbnailName = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg';
        $thumbnailPath = "{$basePath}/thumbs/{$thumbnailName}";

        Storage::disk($this->disk)->put($thumbnailPath, $thumbnail->toJpeg(80));
        $data['thumbnail_path'] = $thumbnailPath;

        return $data;
    }

    public function delete(Attachment $attachment): bool
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        if ($attachment->thumbnail_path) {
            Storage::disk($attachment->disk)->delete($attachment->thumbnail_path);
        }

        return $attachment->delete();
    }

    public function duplicate(Attachment $source, Cell $targetCell): Attachment
    {
        $baseId = $targetCell->row->table->base_id;
        $tableId = $targetCell->row->table_id;
        $basePath = "attachments/{$baseId}/{$tableId}";

        $extension = pathinfo($source->filename, PATHINFO_EXTENSION);
        $uniqueName = Str::ulid() . '.' . $extension;
        $newPath = "{$basePath}/{$uniqueName}";

        // Copy main file
        Storage::disk($this->disk)->copy($source->path, $newPath);

        $data = [
            'filename' => $source->filename,
            'path' => $newPath,
            'disk' => $this->disk,
            'mime_type' => $source->mime_type,
            'size' => $source->size,
            'width' => $source->width,
            'height' => $source->height,
            'metadata' => $source->metadata,
        ];

        // Copy thumbnail if exists
        if ($source->thumbnail_path) {
            $thumbName = pathinfo($uniqueName, PATHINFO_FILENAME) . '_thumb.jpg';
            $newThumbPath = "{$basePath}/thumbs/{$thumbName}";
            Storage::disk($this->disk)->copy($source->thumbnail_path, $newThumbPath);
            $data['thumbnail_path'] = $newThumbPath;
        }

        return $targetCell->attachments()->create($data);
    }
}
