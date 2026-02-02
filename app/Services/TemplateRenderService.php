<?php

namespace App\Services;

use App\Enums\LayerType;
use App\Enums\SemanticTag;
use App\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class TemplateRenderService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.template_renderer.url', 'http://template-renderer:3336');
        $this->timeout = config('services.template_renderer.timeout', 60);
    }

    /**
     * Render a template with substituted data as PNG.
     *
     * @param Template $template The template to render
     * @param array $data Data to substitute (keyed by semantic tag)
     * @param int|null $outputWidth Optional output width for resizing
     * @param int|null $outputHeight Optional output height for resizing
     * @return string Raw PNG binary
     * @throws Exception
     */
    public function render(Template $template, array $data = [], ?int $outputWidth = null, ?int $outputHeight = null): string
    {
        // Prepare template data with substitutions
        $templateData = $this->prepareTemplateData($template, $data);

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/render", [
                'template' => $templateData,
                'width' => $template->width,
                'height' => $template->height,
                'scale' => 2,
                'outputWidth' => $outputWidth,
                'outputHeight' => $outputHeight,
            ]);

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception(__('services.template_render.render_failed', [
                'message' => $error['message'] ?? $response->body(),
            ]));
        }

        return $response->body();
    }

    /**
     * Render a template with layer modifications as PNG.
     * Modifications can be keyed by: layer_key, public_id, or layer name.
     *
     * @param Template $template The template to render
     * @param array $modifications Layer modifications keyed by layer_key/id/name
     * @param int $scale Device scale factor (1-4)
     * @param string $format Output format (png, jpeg, webp)
     * @param int $quality Quality for jpeg/webp (1-100)
     * @return string Raw image binary
     * @throws Exception
     */
    public function renderWithModifications(
        Template $template,
        array $modifications = [],
        int $scale = 2,
        string $format = 'png',
        int $quality = 100
    ): string {
        $templateData = $this->prepareTemplateDataWithModifications($template, $modifications);

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/render", [
                'template' => $templateData,
                'width' => $template->width,
                'height' => $template->height,
                'scale' => $scale,
            ]);

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception(__('services.template_render.render_failed', [
                'message' => $error['message'] ?? $response->body(),
            ]));
        }

        $imageData = $response->body();

        // Convert format if needed
        if ($format !== 'png') {
            $imageData = $this->convertImageFormat($imageData, $format, $quality);
        }

        return $imageData;
    }

    /**
     * Render template with modifications and save to storage.
     *
     * @param Template $template
     * @param array $modifications
     * @param int $scale
     * @param string $format
     * @param int $quality
     * @return array{path: string, url: string}
     * @throws Exception
     */
    public function renderWithModificationsAndStore(
        Template $template,
        array $modifications = [],
        int $scale = 2,
        string $format = 'png',
        int $quality = 100
    ): array {
        $imageData = $this->renderWithModifications($template, $modifications, $scale, $format, $quality);

        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $path = 'generated/' . $template->public_id . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('public')->put($path, $imageData);

        return [
            'path' => $path,
            'url' => url('/storage/' . $path),
        ];
    }

    /**
     * Convert image format using GD.
     */
    protected function convertImageFormat(string $imageData, string $format, int $quality): string
    {
        $image = imagecreatefromstring($imageData);
        if (!$image) {
            throw new Exception('Failed to create image from data');
        }

        ob_start();

        switch ($format) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, null, $quality);
                break;
            case 'webp':
                imagewebp($image, null, $quality);
                break;
            default:
                imagepng($image, null, 9);
        }

        $output = ob_get_clean();
        imagedestroy($image);

        return $output;
    }

    /**
     * Prepare template data with layer modifications.
     * Modifications can be keyed by: layer_key (preferred), public_id, or name.
     */
    protected function prepareTemplateDataWithModifications(Template $template, array $modifications): array
    {
        $template->load('layers');

        $layers = $template->layers->map(function ($layer) use ($modifications) {
            $properties = $layer->properties ?? [];

            // Convert images to base64 data URLs to avoid CORS issues in renderer
            $properties = $this->convertImagesToBase64($properties);

            $layerData = [
                'id' => $layer->public_id,
                'type' => $layer->type->value,
                'name' => $layer->name,
                'x' => $layer->x,
                'y' => $layer->y,
                'width' => $layer->width,
                'height' => $layer->height,
                'rotation' => $layer->rotation,
                'scale_x' => $layer->scale_x,
                'scale_y' => $layer->scale_y,
                'opacity' => $layer->opacity,
                'visible' => $layer->visible,
                'locked' => $layer->locked,
                'position' => $layer->position,
                'properties' => $properties,
            ];

            // Try to find modifications by layer_key, then public_id, then name
            $layerModifications = null;
            if ($layer->layer_key && isset($modifications[$layer->layer_key])) {
                $layerModifications = $modifications[$layer->layer_key];
            } elseif (isset($modifications[$layer->public_id])) {
                $layerModifications = $modifications[$layer->public_id];
            } elseif (isset($modifications[$layer->name])) {
                $layerModifications = $modifications[$layer->name];
            }

            if ($layerModifications) {
                foreach ($layerModifications as $key => $value) {
                    // Handle properties that go into the properties array
                    if (in_array($key, ['text', 'src', 'fill', 'fillImage', 'fillFit', 'stroke', 'fontFamily', 'fontSize', 'textColor', 'padding', 'cornerRadius'])) {
                        $layerData['properties'][$key] = $value;
                    } else {
                        // Top-level layer properties
                        $layerData[$key] = $value;
                    }
                }
            }

            return $layerData;
        })->toArray();

        return [
            'id' => $template->public_id,
            'name' => $template->name,
            'width' => $template->width,
            'height' => $template->height,
            'backgroundColor' => $template->background_color,
            'layers' => $layers,
        ];
    }

    /**
     * Render a template using Vue EditorCanvas (accurate rendering).
     * Uses the /render-vue endpoint which loads the actual Vue component via Laravel.
     *
     * @param Template $template The template to render
     * @param int $scale Device scale factor (default 2 for retina)
     * @return string Raw PNG binary
     * @throws Exception
     */
    public function renderVue(Template $template, int $scale = 2): string
    {
        $templateData = $this->prepareTemplateData($template, []);

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/render-vue", [
                'template' => $templateData,
                'width' => $template->width,
                'height' => $template->height,
                'scale' => $scale,
            ]);

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception(__('services.template_render.render_failed', [
                'message' => $error['message'] ?? $response->body(),
            ]));
        }

        return $response->body();
    }

    /**
     * Render a template as thumbnail using Vue EditorCanvas for accurate rendering.
     *
     * @param Template $template The template to render
     * @param int $width Thumbnail width
     * @return string Raw PNG binary
     * @throws Exception
     */
    public function renderThumbnail(Template $template, int $width = 400): string
    {
        // Use Vue rendering for accurate thumbnail (matches EditorCanvas)
        $imageData = $this->renderVue($template, scale: 1);

        // Resize using GD
        $image = imagecreatefromstring($imageData);
        if (!$image) {
            throw new Exception('Failed to create image from render data');
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        $aspectRatio = $originalHeight / $originalWidth;
        $height = (int) round($width * $aspectRatio);

        // Create resized image
        $thumbnail = imagecreatetruecolor($width, $height);

        // Preserve transparency
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        // High-quality resize
        imagecopyresampled(
            $thumbnail,
            $image,
            0, 0, 0, 0,
            $width, $height,
            $originalWidth, $originalHeight
        );

        // Output to string
        ob_start();
        imagepng($thumbnail, null, 9);
        $thumbnailData = ob_get_clean();

        return $thumbnailData;
    }

    /**
     * Render a template and save to storage.
     *
     * @param Template $template The template to render
     * @param array $data Data to substitute
     * @param string|null $path Storage path (auto-generated if null)
     * @return string The storage path
     * @throws Exception
     */
    public function renderAndStore(Template $template, array $data = [], ?string $path = null): string
    {
        $imageData = $this->render($template, $data);

        if (!$path) {
            $path = 'tmp/previews/' . Str::uuid() . '.png';
        }

        Storage::disk('public')->put($path, $imageData);

        return $path;
    }

    /**
     * Render multiple templates with the same data.
     *
     * @param array<Template> $templates Templates to render
     * @param array $data Data to substitute
     * @return array<array{id: string, success: bool, path?: string, error?: string}>
     */
    public function renderBatch(array $templates, array $data = []): array
    {
        $results = [];

        foreach ($templates as $template) {
            try {
                $path = $this->renderAndStore($template, $data);
                $results[] = [
                    'id' => $template->public_id,
                    'success' => true,
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                ];
            } catch (Exception $e) {
                $results[] = [
                    'id' => $template->public_id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Prepare template data with semantic tag substitutions.
     *
     * @param Template $template
     * @param array $data Data keyed by semantic tag value
     * @return array
     */
    protected function prepareTemplateData(Template $template, array $data): array
    {
        $template->load('layers');

        $layers = $template->layers->map(function ($layer) use ($data) {
            $properties = $layer->properties ?? [];

            // Convert images to base64 data URLs to avoid CORS issues in renderer
            $properties = $this->convertImagesToBase64($properties);

            $layerData = [
                'id' => $layer->public_id,
                'type' => $layer->type->value,
                'name' => $layer->name,
                'x' => $layer->x,
                'y' => $layer->y,
                'width' => $layer->width,
                'height' => $layer->height,
                'rotation' => $layer->rotation,
                'scale_x' => $layer->scale_x,
                'scale_y' => $layer->scale_y,
                'opacity' => $layer->opacity,
                'visible' => $layer->visible,
                'locked' => $layer->locked,
                'position' => $layer->position,
                'properties' => $properties,
            ];

            // Apply semantic tag substitutions
            // Support both old format (semanticTag) and new format (semanticTags array)
            $semanticTags = $layer->properties['semanticTags'] ?? [];
            $legacyTag = $layer->properties['semanticTag'] ?? null;

            // If no semanticTags but has legacy semanticTag, use that
            if (empty($semanticTags) && $legacyTag) {
                $semanticTags = [$legacyTag];
            }

            // Apply all semantic tags (content tags first, then style tags)
            $contentApplied = false;
            foreach ($semanticTags as $tagValue) {
                $tag = SemanticTag::tryFrom($tagValue);
                if (!$tag) {
                    continue;
                }

                $inputKey = $tag->inputKey();

                // Only apply substitution if a value is explicitly provided in data
                // If no value provided, keep original layer content visible
                if (!array_key_exists($inputKey, $data)) {
                    continue;
                }

                $value = $data[$inputKey];

                // For content tags, if value is explicitly empty, hide the layer
                if ($tag->category() === 'content') {
                    if (empty($value)) {
                        $layerData['visible'] = false;
                        break; // Don't process further if content is missing
                    }
                    $contentApplied = true;
                }

                // Apply substitution (pass layer type for context-aware substitution)
                $layerData['properties'] = $this->applySubstitution(
                    $layerData['properties'],
                    $tag,
                    $value,
                    $layer->type
                );
            }

            return $layerData;
        })->toArray();

        return [
            'id' => $template->public_id,
            'name' => $template->name,
            'width' => $template->width,
            'height' => $template->height,
            'backgroundColor' => $template->background_color,
            'layers' => $layers,
        ];
    }

    /**
     * Convert relative image paths in properties to absolute URLs.
     *
     * @param array $properties
     * @param string $baseUrl
     * @return array
     */
    protected function convertImagePaths(array $properties, string $baseUrl): array
    {
        $imageKeys = ['src', 'maskSrc', 'smartObjectSource'];

        foreach ($imageKeys as $key) {
            if (isset($properties[$key]) && is_string($properties[$key])) {
                $path = $properties[$key];
                // Convert relative paths starting with / to absolute URLs
                if (str_starts_with($path, '/') && !str_starts_with($path, '//')) {
                    $properties[$key] = $baseUrl . $path;
                }
            }
        }

        return $properties;
    }

    /**
     * Convert image paths to base64 data URLs for reliable rendering.
     *
     * @param array $properties
     * @return array
     */
    protected function convertImagesToBase64(array $properties): array
    {
        $imageKeys = ['src', 'maskSrc', 'smartObjectSource'];

        foreach ($imageKeys as $key) {
            if (isset($properties[$key]) && is_string($properties[$key])) {
                $path = $properties[$key];

                // Skip if already a data URL
                if (str_starts_with($path, 'data:')) {
                    continue;
                }

                // Convert /storage/ paths to actual file paths
                if (str_starts_with($path, '/storage/')) {
                    $relativePath = str_replace('/storage/', '', $path);
                    $fullPath = Storage::disk('public')->path($relativePath);

                    if (file_exists($fullPath)) {
                        $imageData = file_get_contents($fullPath);
                        $mimeType = mime_content_type($fullPath) ?: 'image/png';
                        $properties[$key] = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    }
                }
            }
        }

        return $properties;
    }

    /**
     * Prepare template data with images as base64 for reliable rendering.
     *
     * @param Template $template
     * @return array
     */
    protected function prepareTemplateDataWithBase64Images(Template $template): array
    {
        $template->load('layers');

        $layers = $template->layers->map(function ($layer) {
            $properties = $layer->properties ?? [];

            // Convert images to base64 data URLs
            $properties = $this->convertImagesToBase64($properties);

            return [
                'id' => $layer->public_id,
                'type' => $layer->type->value,
                'name' => $layer->name,
                'x' => $layer->x,
                'y' => $layer->y,
                'width' => $layer->width,
                'height' => $layer->height,
                'rotation' => $layer->rotation,
                'scale_x' => $layer->scale_x,
                'scale_y' => $layer->scale_y,
                'opacity' => $layer->opacity,
                'visible' => $layer->visible,
                'locked' => $layer->locked,
                'position' => $layer->position,
                'properties' => $properties,
            ];
        })->toArray();

        return [
            'id' => $template->public_id,
            'name' => $template->name,
            'width' => $template->width,
            'height' => $template->height,
            'backgroundColor' => $template->background_color,
            'layers' => $layers,
        ];
    }

    /**
     * Apply substitution based on semantic tag.
     *
     * @param array $properties Layer properties
     * @param SemanticTag $tag The semantic tag
     * @param mixed $value The value to substitute
     * @param LayerType|null $layerType The layer type for context-aware substitution
     * @return array Modified properties
     */
    protected function applySubstitution(array $properties, SemanticTag $tag, mixed $value, ?LayerType $layerType = null): array
    {
        $propertyKey = $tag->affectsProperty();

        // For color tags on image layers, use tintColor instead of fill
        // This allows recoloring of icon/shape images that were rasterized from PSD
        if ($propertyKey === 'fill' && $layerType === LayerType::IMAGE) {
            $properties['tintColor'] = $value;
            return $properties;
        }

        $properties[$propertyKey] = $value;

        return $properties;
    }

    /**
     * Check if the template renderer service is healthy.
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful() && $response->json('status') === 'ok';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get available semantic tags.
     *
     * @return array
     */
    public static function availableSemanticTags(): array
    {
        return SemanticTag::options();
    }

    /**
     * Clean up old preview files.
     *
     * @param int $olderThanMinutes Delete files older than this many minutes
     * @return int Number of files deleted
     */
    public function cleanupOldPreviews(int $olderThanMinutes = 60): int
    {
        $disk = Storage::disk('public');
        $directory = 'tmp/previews';

        if (!$disk->exists($directory)) {
            return 0;
        }

        $files = $disk->files($directory);
        $deleted = 0;
        $cutoffTime = now()->subMinutes($olderThanMinutes)->timestamp;

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);
            if ($lastModified < $cutoffTime) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
