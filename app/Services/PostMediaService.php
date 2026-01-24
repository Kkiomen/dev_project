<?php

namespace App\Services;

use App\Models\PostMedia;
use App\Models\SocialPost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class PostMediaService
{
    protected string $disk = 'public';
    protected string $basePath = 'post-media';
    protected int $thumbnailWidth = 400;
    protected int $thumbnailHeight = 400;

    public function upload(UploadedFile $file, SocialPost $post): PostMedia
    {
        $type = $this->getMediaType($file);
        $filename = $this->generateFilename($file);
        $path = $this->getStoragePath($post, $filename);

        // Store the file
        $file->storeAs(dirname($path), basename($path), $this->disk);

        // Get dimensions for images/videos
        $dimensions = $this->getDimensions($file, $type);

        // Generate thumbnail for images
        $thumbnailPath = null;
        if ($type === 'image') {
            $thumbnailPath = $this->generateThumbnail($file, $post, $filename);
        }

        return PostMedia::create([
            'social_post_id' => $post->id,
            'type' => $type,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $this->disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'thumbnail_path' => $thumbnailPath,
        ]);
    }

    public function reorder(SocialPost $post, array $mediaIds): void
    {
        foreach ($mediaIds as $position => $mediaId) {
            PostMedia::where('social_post_id', $post->id)
                ->where('public_id', $mediaId)
                ->update(['position' => $position]);
        }
    }

    public function delete(PostMedia $media): void
    {
        // Delete files from storage
        if ($media->path) {
            Storage::disk($media->disk)->delete($media->path);
        }

        if ($media->thumbnail_path) {
            Storage::disk($media->disk)->delete($media->thumbnail_path);
        }

        $media->delete();
    }

    protected function getMediaType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'file';
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return Str::ulid() . '.' . $extension;
    }

    protected function getStoragePath(SocialPost $post, string $filename): string
    {
        $userId = $post->user_id;
        $postId = $post->public_id;

        return "{$this->basePath}/{$userId}/{$postId}/{$filename}";
    }

    protected function getDimensions(UploadedFile $file, string $type): array
    {
        if ($type !== 'image') {
            return ['width' => null, 'height' => null];
        }

        try {
            $image = Image::read($file->getPathname());
            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            return ['width' => null, 'height' => null];
        }
    }

    protected function generateThumbnail(UploadedFile $file, SocialPost $post, string $filename): ?string
    {
        try {
            $image = Image::read($file->getPathname());

            // Cover resize (maintain aspect ratio, crop to fit)
            $image->cover($this->thumbnailWidth, $this->thumbnailHeight);

            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $this->getStoragePath($post, $thumbnailFilename);

            // Ensure directory exists
            $directory = dirname($thumbnailPath);
            Storage::disk($this->disk)->makeDirectory($directory);

            // Save thumbnail
            $fullPath = Storage::disk($this->disk)->path($thumbnailPath);
            $image->save($fullPath);

            return $thumbnailPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function validateMediaForPlatform(PostMedia $media, string $platform): array
    {
        $errors = [];

        $constraints = $this->getPlatformConstraints($platform);

        // Check file size
        if ($media->size > $constraints['max_size']) {
            $maxSizeMb = $constraints['max_size'] / (1024 * 1024);
            $errors[] = "File size exceeds {$maxSizeMb}MB limit for {$platform}";
        }

        // Check dimensions for images
        if ($media->isImage() && isset($constraints['min_width'])) {
            if ($media->width && $media->width < $constraints['min_width']) {
                $errors[] = "Image width must be at least {$constraints['min_width']}px for {$platform}";
            }
        }

        // Check aspect ratio
        if ($media->isImage() && isset($constraints['aspect_ratios'])) {
            $ratio = $media->getAspectRatio();
            if ($ratio) {
                $valid = false;
                foreach ($constraints['aspect_ratios'] as $allowedRatio) {
                    if (abs($ratio - $allowedRatio) < 0.01) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid) {
                    $errors[] = "Image aspect ratio is not optimal for {$platform}";
                }
            }
        }

        return $errors;
    }

    protected function getPlatformConstraints(string $platform): array
    {
        return match ($platform) {
            'instagram' => [
                'max_size' => 8 * 1024 * 1024, // 8MB
                'min_width' => 320,
                'max_width' => 1080,
                'aspect_ratios' => [1.0, 1.91, 0.8], // Square, Landscape, Portrait
            ],
            'facebook' => [
                'max_size' => 4 * 1024 * 1024, // 4MB
                'min_width' => 200,
            ],
            'youtube' => [
                'max_size' => 128 * 1024 * 1024, // 128MB for thumbnails
                'min_width' => 1280,
                'aspect_ratios' => [1.78], // 16:9
            ],
            default => [
                'max_size' => 10 * 1024 * 1024,
            ],
        };
    }
}
