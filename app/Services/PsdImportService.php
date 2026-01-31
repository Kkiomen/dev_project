<?php

namespace App\Services;

use App\Enums\LayerType;
use App\Models\Layer;
use App\Models\Template;
use App\Models\TemplateFont;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PsdImportService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.psd_parser.url', 'http://psd-parser:3335');
        $this->timeout = config('services.psd_parser.timeout', 120);
    }

    /**
     * Import a PSD file and create a template with layers.
     *
     * @param UploadedFile $file The PSD file
     * @param User $user The user who owns the template
     * @param string|null $name Optional template name
     * @param bool $addToLibrary Whether to add the template to the library
     * @return Template
     * @throws Exception
     */
    public function import(UploadedFile $file, User $user, ?string $name = null, bool $addToLibrary = false): Template
    {
        // Parse the PSD file using Python service
        $parsedData = $this->parseWithPythonService($file);

        // Use transaction for atomic creation
        return DB::transaction(function () use ($parsedData, $user, $name, $addToLibrary, $file) {
            // Create the template
            $template = $this->createTemplate($parsedData, $user, $name ?? $this->generateTemplateName($file));

            // Set library flag if requested
            if ($addToLibrary) {
                $template->is_library = true;
                $template->save();
            }

            // Create layers (with smart object sources for linked assets)
            // NOTE: psd-tools iterates layers from BOTTOM to TOP of Photoshop panel
            // This means first layer (index 0) is at bottom, last layer is at top
            // Konva renders in the same order (lower position = bottom), so no reversal needed
            $this->createLayers(
                $template,
                $parsedData['layers'],
                $parsedData['images'] ?? [],
                $parsedData['masks'] ?? [],
                $parsedData['smart_object_sources'] ?? []
            );

            // Create fonts
            $this->createFonts($template, $parsedData['fonts'] ?? []);

            return $template->load('layers.parent', 'fonts');
        });
    }

    /**
     * Send PSD file to Python parser service.
     *
     * @param UploadedFile $file
     * @return array
     * @throws Exception
     */
    public function parseWithPythonService(UploadedFile $file): array
    {
        $response = Http::timeout($this->timeout)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post("{$this->baseUrl}/parse");

        if ($response->failed()) {
            $error = $response->json();
            $message = $error['error'] ?? $response->body();
            Log::error('PSD parsing failed', ['error' => $message]);
            throw new Exception(__('graphics.psd.errors.parseFailed') . ': ' . $message);
        }

        $data = $response->json();

        // Log warnings if any
        if (!empty($data['warnings'])) {
            Log::info('PSD import warnings', ['warnings' => $data['warnings']]);
        }

        return $data;
    }

    /**
     * Create a template from parsed PSD data.
     *
     * @param array $data
     * @param User $user
     * @param string $name
     * @return Template
     */
    protected function createTemplate(array $data, User $user, string $name): Template
    {
        return Template::create([
            'user_id' => $user->id,
            'name' => $name,
            'width' => $data['width'],
            'height' => $data['height'],
            'background_color' => $data['background_color'] ?? '#FFFFFF',
            'settings' => [
                'imported_from' => 'psd',
                'warnings' => $data['warnings'] ?? [],
            ],
        ]);
    }

    /**
     * Create layers for a template (with hierarchy support).
     *
     * @param Template $template
     * @param array $layers
     * @param array $images
     * @param array $masks Layer mask images (raster masks)
     * @param array $smartObjectSources Smart object source images (linked assets)
     */
    protected function createLayers(Template $template, array $layers, array $images, array $masks = [], array $smartObjectSources = []): void
    {
        // Index images by layer_index for quick lookup
        $imagesByLayerIndex = collect($images)->keyBy('layer_index')->all();

        // Index masks by layer_index for quick lookup
        $masksByLayerIndex = collect($masks)->keyBy('layer_index')->all();

        // Process smart object sources - save each only once and create lookup map
        $smartObjectSourcePaths = [];
        foreach ($smartObjectSources as $source) {
            $uniqueId = $source['unique_id'] ?? null;
            if ($uniqueId && isset($source['data'])) {
                $savedPath = $this->saveImage($source['data'], $template->id, "so-{$uniqueId}");
                if ($savedPath) {
                    $smartObjectSourcePaths[$uniqueId] = $savedPath;
                    Log::info('PSD Import: Saved smart object source', [
                        'unique_id' => $uniqueId,
                        'path' => $savedPath,
                        'width' => $source['width'] ?? 0,
                        'height' => $source['height'] ?? 0,
                    ]);
                }
            }
        }

        Log::info('PSD Import: Creating layers', [
            'template_id' => $template->id,
            'layer_count' => count($layers),
            'image_count' => count($images),
            'mask_count' => count($masks),
            'smart_object_sources_count' => count($smartObjectSourcePaths),
        ]);

        // Recursively create layers with parent-child relationships
        $this->createLayersRecursive($template, $layers, $imagesByLayerIndex, $masksByLayerIndex, $smartObjectSourcePaths, null);
    }

    /**
     * Recursively create layers with hierarchy.
     *
     * @param Template $template
     * @param array $layers
     * @param array $imagesByLayerIndex
     * @param array $masksByLayerIndex Layer mask images indexed by layer_index
     * @param array $smartObjectSourcePaths Map of unique_id => saved path for shared sources
     * @param int|null $parentId
     */
    protected function createLayersRecursive(
        Template $template,
        array $layers,
        array $imagesByLayerIndex,
        array $masksByLayerIndex,
        array $smartObjectSourcePaths,
        ?int $parentId
    ): void {
        foreach ($layers as $layerData) {
            // Map type string to enum
            $layerType = $this->mapLayerType($layerData['type']);
            if (!$layerType) {
                continue;
            }

            // Handle image layers - save image and update src
            $properties = $layerData['properties'] ?? [];

            // Debug: Log if clipPath is present
            if ($layerType === LayerType::IMAGE) {
                Log::info('PSD Import: Image layer properties', [
                    'layer_name' => $layerData['name'],
                    'has_clipPath' => isset($properties['clipPath']),
                    'clipPath_preview' => isset($properties['clipPath']) ? substr($properties['clipPath'], 0, 100) : null,
                    'has_mask' => $layerData['mask_id'] ?? false,
                    'properties_keys' => array_keys($properties),
                    'smartObjectSourceId' => $properties['smartObjectSourceId'] ?? null,
                    'opacity' => $layerData['opacity'] ?? 1,
                    'scale_x' => $layerData['scale_x'] ?? 1,
                    'scale_y' => $layerData['scale_y'] ?? 1,
                ]);
            }

            if ($layerType === LayerType::IMAGE && isset($layerData['image_id'])) {
                $layerIndex = $layerData['position'];
                if (isset($imagesByLayerIndex[$layerIndex])) {
                    $imageData = $imagesByLayerIndex[$layerIndex];
                    $savedPath = $this->saveImage($imageData['data'], $template->id);
                    if ($savedPath) {
                        $properties['src'] = $savedPath;
                    }
                }

                // Save and link layer mask (raster mask) if present
                if (isset($layerData['mask_id']) && isset($masksByLayerIndex[$layerIndex])) {
                    $maskData = $masksByLayerIndex[$layerIndex];
                    $maskPath = $this->saveImage($maskData['data'], $template->id, "mask-{$layerIndex}");
                    if ($maskPath) {
                        $properties['maskSrc'] = $maskPath;
                        // Include mask dimensions and offset for proper alignment
                        $properties['maskWidth'] = $maskData['width'] ?? 0;
                        $properties['maskHeight'] = $maskData['height'] ?? 0;
                        $properties['maskOffsetX'] = $maskData['offset_x'] ?? 0;
                        $properties['maskOffsetY'] = $maskData['offset_y'] ?? 0;
                        Log::info('PSD Import: Saved layer mask', [
                            'layer_name' => $layerData['name'],
                            'mask_path' => $maskPath,
                            'mask_width' => $maskData['width'] ?? 0,
                            'mask_height' => $maskData['height'] ?? 0,
                            'mask_offset_x' => $maskData['offset_x'] ?? 0,
                            'mask_offset_y' => $maskData['offset_y'] ?? 0,
                        ]);
                    }
                }

                // Add smart object source reference if this layer has a linked smart object
                $smartObjectSourceId = $properties['smartObjectSourceId'] ?? null;
                if ($smartObjectSourceId && isset($smartObjectSourcePaths[$smartObjectSourceId])) {
                    $properties['smartObjectSource'] = $smartObjectSourcePaths[$smartObjectSourceId];
                    Log::info('PSD Import: Linked smart object source', [
                        'layer_name' => $layerData['name'],
                        'smartObjectSourceId' => $smartObjectSourceId,
                        'smartObjectSource' => $properties['smartObjectSource'],
                    ]);
                }
            }

            // Create the layer with opacity and potential flip (negative scale)
            $layer = $template->layers()->create([
                'parent_id' => $parentId,
                'name' => $layerData['name'],
                'type' => $layerType,
                'position' => $layerData['position'],
                'visible' => $layerData['visible'] ?? true,
                'locked' => $layerData['locked'] ?? false,
                'x' => $layerData['x'] ?? 0,
                'y' => $layerData['y'] ?? 0,
                'width' => $layerData['width'] ?? 100,
                'height' => $layerData['height'] ?? 100,
                'rotation' => $layerData['rotation'] ?? 0,
                'scale_x' => $layerData['scale_x'] ?? 1,
                'scale_y' => $layerData['scale_y'] ?? 1,
                'opacity' => $layerData['opacity'] ?? 1,  // Layer opacity (0.0 - 1.0)
                'properties' => $properties,
            ]);

            // If this is a group, recursively create children
            if ($layerType === LayerType::GROUP && !empty($layerData['children'])) {
                $this->createLayersRecursive($template, $layerData['children'], $imagesByLayerIndex, $masksByLayerIndex, $smartObjectSourcePaths, $layer->id);
            }
        }
    }

    /**
     * Create font records for a template.
     *
     * @param Template $template
     * @param array $fonts
     */
    protected function createFonts(Template $template, array $fonts): void
    {
        foreach ($fonts as $fontData) {
            // Check if font already exists for this template
            $exists = $template->fonts()
                ->where('font_family', $fontData['fontFamily'])
                ->where('font_weight', $fontData['fontWeight'] ?? 'normal')
                ->where('font_style', $fontData['fontStyle'] ?? 'normal')
                ->exists();

            if (!$exists) {
                $template->fonts()->create([
                    'font_family' => $fontData['fontFamily'],
                    'font_file' => '', // Empty for Google Fonts - loaded via URL
                    'font_weight' => $fontData['fontWeight'] ?? 'normal',
                    'font_style' => $fontData['fontStyle'] ?? 'normal',
                ]);
            }
        }
    }

    /**
     * Save a base64 image to storage.
     *
     * @param string $base64Data
     * @param int $templateId
     * @param string|null $filenamePrefix Optional prefix for the filename (e.g., "so-{unique_id}")
     * @return string|null The storage path or null on failure
     */
    protected function saveImage(string $base64Data, int $templateId, ?string $filenamePrefix = null): ?string
    {
        try {
            // Extract actual base64 data (remove data:image/png;base64, prefix)
            $data = $base64Data;
            if (str_contains($base64Data, ',')) {
                $data = explode(',', $base64Data, 2)[1];
            }

            $imageData = base64_decode($data);
            if ($imageData === false) {
                return null;
            }

            // Generate filename with optional prefix for identification
            $fileId = $filenamePrefix ? "{$filenamePrefix}-" . Str::uuid() : Str::uuid();
            $filename = 'psd-imports/' . $templateId . '/' . $fileId . '.png';

            // Save to storage
            Storage::disk('public')->put($filename, $imageData);

            return '/storage/' . $filename;
        } catch (Exception $e) {
            Log::error('Failed to save PSD image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Map PSD layer type string to LayerType enum.
     *
     * @param string $type
     * @return LayerType|null
     */
    protected function mapLayerType(string $type): ?LayerType
    {
        return match ($type) {
            'text' => LayerType::TEXT,
            'image' => LayerType::IMAGE,
            'rectangle' => LayerType::RECTANGLE,
            'ellipse' => LayerType::ELLIPSE,
            'group' => LayerType::GROUP,
            default => null,
        };
    }

    /**
     * Generate template name from file.
     *
     * @param UploadedFile $file
     * @return string
     */
    protected function generateTemplateName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        return Str::title(str_replace(['_', '-'], ' ', $name));
    }

    /**
     * Check if the PSD parser service is healthy.
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
     * Analyze a PSD file structure without importing.
     *
     * @param UploadedFile $file
     * @return array
     * @throws Exception
     */
    public function analyze(UploadedFile $file): array
    {
        $response = Http::timeout($this->timeout)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post("{$this->baseUrl}/analyze");

        if ($response->failed()) {
            $error = $response->json();
            $message = $error['error'] ?? $response->body();
            throw new Exception(__('graphics.psd.errors.analyzeFailed') . ': ' . $message);
        }

        return $response->json();
    }
}
