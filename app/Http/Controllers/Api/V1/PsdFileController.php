<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LayerType;
use App\Enums\SemanticTag;
use App\Http\Controllers\Controller;
use App\Models\Layer;
use App\Models\PsdLayerTag;
use App\Models\Template;
use App\Models\User;
use App\Services\Helpers\SemanticTagSubstitution;
use App\Services\PsdImportService;
use App\Services\TemplateRenderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PsdFileController extends Controller
{
    protected string $psdDirectory = 'psd';

    public function __construct(
        protected PsdImportService $psdImportService,
        protected TemplateRenderService $renderService
    ) {}

    /**
     * List all PSD files in storage.
     *
     * GET /api/v1/psd-files
     */
    public function index(): JsonResponse
    {
        $disk = Storage::disk('local');
        $files = $disk->files($this->psdDirectory);

        $psdFiles = collect($files)
            ->filter(fn($file) => Str::endsWith(strtolower($file), '.psd'))
            ->map(function ($file) use ($disk) {
                $filename = basename($file);
                return [
                    'name' => $filename,
                    'path' => $file,
                    'size' => $disk->size($file),
                    'modified_at' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                    'has_tags' => PsdLayerTag::forFile($filename)->exists(),
                ];
            })
            ->values();

        return response()->json([
            'files' => $psdFiles,
        ]);
    }

    /**
     * Get a PSD file (binary download).
     *
     * GET /api/v1/psd-files/{name}
     */
    public function show(string $name): Response|JsonResponse
    {
        $path = $this->psdDirectory . '/' . $name;
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            return response()->json([
                'message' => __('psd_editor.errors.file_not_found'),
            ], 404);
        }

        return response($disk->get($path), 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"');
    }

    /**
     * Save a PSD file.
     *
     * PUT /api/v1/psd-files/{name}
     */
    public function update(Request $request, string $name): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $path = $this->psdDirectory . '/' . $name;
        $disk = Storage::disk('local');

        $file = $request->file('file');
        $disk->put($path, file_get_contents($file->getRealPath()));

        return response()->json([
            'message' => __('psd_editor.messages.file_saved'),
            'name' => $name,
        ]);
    }

    /**
     * Parse a PSD file and return layer structure.
     *
     * POST /api/v1/psd-files/{name}/parse
     */
    public function parse(string $name): JsonResponse
    {
        $path = $this->psdDirectory . '/' . $name;
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            return response()->json([
                'message' => __('psd_editor.errors.file_not_found'),
            ], 404);
        }

        try {
            // Create a temporary file for the PSD parser
            $tempPath = sys_get_temp_dir() . '/' . $name;
            file_put_contents($tempPath, $disk->get($path));

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $name,
                'application/octet-stream',
                null,
                true
            );

            $parsedData = $this->psdImportService->parseWithPythonService($uploadedFile);

            // Clean up temp file
            @unlink($tempPath);

            // Add existing tags to parsed layers
            $tags = PsdLayerTag::getTagsForFile($name);
            $parsedData['layers'] = $this->attachTagsToLayers($parsedData['layers'], $tags);

            return response()->json($parsedData);
        } catch (Exception $e) {
            Log::error('PSD parse failed', [
                'file' => $name,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('psd_editor.errors.parse_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tags for a PSD file.
     *
     * GET /api/v1/psd-files/{name}/tags
     */
    public function getTags(string $name): JsonResponse
    {
        $tags = PsdLayerTag::forFile($name)->get();

        return response()->json([
            'tags' => $tags->map(fn($tag) => [
                'layer_path' => $tag->layer_path,
                'semantic_tag' => $tag->semantic_tag,
                'is_variant' => $tag->is_variant,
            ]),
        ]);
    }

    /**
     * Save tags for a PSD file (bulk update).
     *
     * PUT /api/v1/psd-files/{name}/tags
     */
    public function saveTags(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'tags' => 'required|array',
            'tags.*.layer_path' => 'required|string|max:500',
            'tags.*.semantic_tag' => 'nullable|string|max:50',
            'tags.*.is_variant' => 'nullable|boolean',
        ]);

        PsdLayerTag::bulkUpdate($name, $validated['tags']);

        return response()->json([
            'message' => __('psd_editor.messages.tags_saved'),
        ]);
    }

    /**
     * Import variants as separate templates.
     *
     * POST /api/v1/psd-files/{name}/import
     */
    public function import(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'variants' => 'required|array|min:1',
            'variants.*' => 'required|string|max:500',
            'add_to_library' => 'nullable|boolean',
        ]);

        $path = $this->psdDirectory . '/' . $name;
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            return response()->json([
                'message' => __('psd_editor.errors.file_not_found'),
            ], 404);
        }

        try {
            // Parse the PSD
            $tempPath = sys_get_temp_dir() . '/' . $name;
            file_put_contents($tempPath, $disk->get($path));

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $name,
                'application/octet-stream',
                null,
                true
            );

            $parsedData = $this->psdImportService->parseWithPythonService($uploadedFile);
            @unlink($tempPath);

            // Get tags for this PSD
            $tags = PsdLayerTag::getTagsForFile($name);

            $user = $request->user();
            $addToLibrary = $validated['add_to_library'] ?? false;
            $createdTemplates = [];

            DB::transaction(function () use ($parsedData, $validated, $tags, $user, $addToLibrary, &$createdTemplates, $name) {
                foreach ($validated['variants'] as $variantPath) {
                    // Find the variant group in parsed data
                    $variantLayer = $this->findLayerByPath($parsedData['layers'], $variantPath);

                    if (!$variantLayer || ($variantLayer['type'] ?? '') !== 'group') {
                        Log::warning('Variant not found or not a group', [
                            'variant_path' => $variantPath,
                        ]);
                        continue;
                    }

                    // Create template from this variant
                    $template = $this->createTemplateFromVariant(
                        $parsedData,
                        $variantLayer,
                        $variantPath,
                        $tags,
                        $user,
                        $addToLibrary,
                        $name
                    );

                    $createdTemplates[] = [
                        'id' => $template->public_id,
                        'name' => $template->name,
                        'variant_path' => $variantPath,
                    ];
                }
            });

            return response()->json([
                'message' => __('psd_editor.messages.import_success', ['count' => count($createdTemplates)]),
                'templates' => $createdTemplates,
            ]);
        } catch (Exception $e) {
            Log::error('PSD import failed', [
                'file' => $name,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('psd_editor.errors.import_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate preview for a variant.
     *
     * POST /api/v1/psd-files/{name}/preview
     */
    public function preview(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'variant' => 'required|string|max:500',
            'data' => 'nullable|array',
            'data.header' => 'nullable|string|max:500',
            'data.subtitle' => 'nullable|string|max:500',
            'data.paragraph' => 'nullable|string|max:2000',
            'data.primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'data.secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'data.social_handle' => 'nullable|string|max:100',
            'data.main_image' => 'nullable|string',
        ]);

        $path = $this->psdDirectory . '/' . $name;
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            return response()->json([
                'message' => __('psd_editor.errors.file_not_found'),
            ], 404);
        }

        try {
            // Parse the PSD
            $tempPath = sys_get_temp_dir() . '/' . $name;
            file_put_contents($tempPath, $disk->get($path));

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $name,
                'application/octet-stream',
                null,
                true
            );

            $parsedData = $this->psdImportService->parseWithPythonService($uploadedFile);
            @unlink($tempPath);

            // Get tags for this PSD
            $tags = PsdLayerTag::getTagsForFile($name);

            // Find the variant layer
            $variantLayer = $this->findLayerByPath($parsedData['layers'], $validated['variant']);

            if (!$variantLayer) {
                return response()->json([
                    'message' => __('psd_editor.errors.variant_not_found'),
                ], 404);
            }

            // Render preview using native PSD composite (1:1 accuracy)
            $previewUrl = $this->renderVariantPreview(
                $path,
                $validated['variant'],
                $tags,
                $validated['data'] ?? []
            );

            return response()->json([
                'preview_url' => $previewUrl,
                'width' => $parsedData['width'],
                'height' => $parsedData['height'],
            ]);
        } catch (Exception $e) {
            Log::error('PSD preview failed', [
                'file' => $name,
                'variant' => $validated['variant'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('psd_editor.errors.preview_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate previews for all variants using native PSD composite (1:1 accuracy).
     *
     * POST /api/v1/psd-files/{name}/preview-all
     */
    public function previewAllVariants(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
            'data.header' => 'nullable|string|max:500',
            'data.subtitle' => 'nullable|string|max:500',
            'data.paragraph' => 'nullable|string|max:2000',
            'data.primary_color' => 'nullable|string',
            'data.secondary_color' => 'nullable|string',
            'data.social_handle' => 'nullable|string|max:100',
            'data.main_image' => 'nullable|string',
            'data.url' => 'nullable|string|max:500',
            'data.cta' => 'nullable|string|max:200',
            'data.logo' => 'nullable|string',
        ]);

        // Handle both "psd/102.psd" and "102.psd" formats
        $path = Str::contains($name, '/') ? $name : $this->psdDirectory . '/' . $name;
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            return response()->json([
                'error' => __('psd_editor.errors.file_not_found'),
            ], 404);
        }

        // Use basename for tag lookups (tags are stored by filename only)
        $tagFilename = basename($name);

        try {
            // Get variant paths from saved tags
            $tags = PsdLayerTag::getTagsForFile($tagFilename);
            $variantPaths = PsdLayerTag::getVariantPaths($tagFilename);

            if (empty($variantPaths)) {
                return response()->json([
                    'previews' => [],
                    'error' => __('psd_editor.variant_preview.no_variants_marked'),
                ]);
            }

            // Read PSD file content once
            $psdContent = $disk->get($path);

            $psdParserUrl = config('services.psd_parser.url', 'http://psd-parser:3335');
            $timeout = config('services.psd_parser.timeout', 120);
            $previews = [];

            foreach ($variantPaths as $variantPath) {
                try {
                    // Call psd-parser /render-with-substitution endpoint (native composite, 1:1 accuracy)
                    $response = Http::timeout($timeout)
                        ->attach('file', $psdContent, basename($name))
                        ->post("{$psdParserUrl}/render-with-substitution", [
                            'variant_path' => $variantPath,
                            'tags' => json_encode($tags),
                            'data' => json_encode($validated['data'] ?? []),
                        ]);

                    if ($response->successful()) {
                        // Save PNG to storage
                        $filename = 'psd-previews/' . Str::uuid() . '.png';
                        Storage::disk('public')->put($filename, $response->body());

                        $previews[] = [
                            'id' => $variantPath,
                            'name' => basename($variantPath),
                            'preview_url' => '/storage/' . $filename,
                            'width' => 1080, // Default, could be parsed from PSD
                            'height' => 1080,
                        ];
                    } else {
                        Log::warning('PSD variant render failed', [
                            'variant' => $variantPath,
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        $previews[] = [
                            'id' => $variantPath,
                            'name' => basename($variantPath),
                            'error' => __('psd_editor.variant_preview.render_failed'),
                        ];
                    }
                } catch (Exception $e) {
                    Log::error('PSD variant preview exception', [
                        'variant' => $variantPath,
                        'error' => $e->getMessage(),
                    ]);
                    $previews[] = [
                        'id' => $variantPath,
                        'name' => basename($variantPath),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json(['previews' => $previews]);

        } catch (Exception $e) {
            Log::error('PSD preview-all failed', [
                'file' => $name,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => __('psd_editor.errors.preview_failed') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build tags array relative to variant path for substitution.
     * Converts absolute paths to paths relative to the variant.
     */
    protected function buildTagsForVariant(array $tags, string $variantPath): array
    {
        $result = [];
        $prefix = $variantPath . '/';

        foreach ($tags as $layerPath => $tagData) {
            // Only include tags that are within this variant
            if (str_starts_with($layerPath, $prefix)) {
                // Convert to path relative to variant (remove variant prefix)
                $relativePath = substr($layerPath, strlen($prefix));
                $result[$relativePath] = $tagData;
            }
        }

        return $result;
    }

    /**
     * Attach saved tags to parsed layers.
     */
    protected function attachTagsToLayers(array $layers, array $tags, string $parentPath = ''): array
    {
        foreach ($layers as &$layer) {
            $layerPath = $parentPath ? $parentPath . '/' . $layer['name'] : $layer['name'];
            $layer['_path'] = $layerPath;

            if (isset($tags[$layerPath])) {
                $layer['_semantic_tag'] = $tags[$layerPath]['semantic_tag'];
                $layer['_is_variant'] = $tags[$layerPath]['is_variant'];
            }

            if (!empty($layer['children'])) {
                $layer['children'] = $this->attachTagsToLayers($layer['children'], $tags, $layerPath);
            }
        }

        return $layers;
    }

    /**
     * Find a layer by its path.
     */
    protected function findLayerByPath(array $layers, string $path, string $currentPath = ''): ?array
    {
        foreach ($layers as $layer) {
            $layerPath = $currentPath ? $currentPath . '/' . $layer['name'] : $layer['name'];

            if ($layerPath === $path) {
                return $layer;
            }

            if (!empty($layer['children'])) {
                $found = $this->findLayerByPath($layer['children'], $path, $layerPath);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Create a template from a variant group.
     */
    protected function createTemplateFromVariant(
        array $parsedData,
        array $variantLayer,
        string $variantPath,
        array $tags,
        User $user,
        bool $addToLibrary,
        string $sourcePsd
    ): Template {
        // Calculate bounding box for the variant
        $bounds = $this->calculateVariantBounds($variantLayer);

        // Create template
        $template = Template::create([
            'user_id' => $user->id,
            'name' => $variantLayer['name'],
            'width' => $bounds['width'] ?: $parsedData['width'],
            'height' => $bounds['height'] ?: $parsedData['height'],
            'background_color' => $parsedData['background_color'] ?? '#FFFFFF',
            'is_library' => $addToLibrary,
            'settings' => [
                'imported_from' => 'psd_editor',
                'source_psd' => $sourcePsd,
                'variant_path' => $variantPath,
            ],
        ]);

        // Create layers from variant children
        $this->createLayersFromVariant(
            $template,
            $variantLayer['children'] ?? [],
            $parsedData['images'] ?? [],
            $tags,
            $variantPath,
            null,
            $bounds['offsetX'] ?? 0,
            $bounds['offsetY'] ?? 0
        );

        // Copy fonts
        foreach ($parsedData['fonts'] ?? [] as $fontData) {
            $exists = $template->fonts()
                ->where('font_family', $fontData['fontFamily'])
                ->where('font_weight', $fontData['fontWeight'] ?? 'normal')
                ->where('font_style', $fontData['fontStyle'] ?? 'normal')
                ->exists();

            if (!$exists) {
                $template->fonts()->create([
                    'font_family' => $fontData['fontFamily'],
                    'font_file' => '',
                    'font_weight' => $fontData['fontWeight'] ?? 'normal',
                    'font_style' => $fontData['fontStyle'] ?? 'normal',
                ]);
            }
        }

        return $template->load('layers.parent', 'fonts');
    }

    /**
     * Calculate the bounding box for a variant layer.
     */
    protected function calculateVariantBounds(array $layer): array
    {
        if (empty($layer['children'])) {
            return [
                'width' => $layer['width'] ?? 0,
                'height' => $layer['height'] ?? 0,
                'offsetX' => $layer['x'] ?? 0,
                'offsetY' => $layer['y'] ?? 0,
            ];
        }

        $minX = PHP_INT_MAX;
        $minY = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $maxY = PHP_INT_MIN;

        $this->calculateBoundsRecursive($layer['children'], $minX, $minY, $maxX, $maxY);

        if ($minX === PHP_INT_MAX) {
            return ['width' => 0, 'height' => 0, 'offsetX' => 0, 'offsetY' => 0];
        }

        return [
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
            'offsetX' => $minX,
            'offsetY' => $minY,
        ];
    }

    /**
     * Recursively calculate bounds.
     */
    protected function calculateBoundsRecursive(array $layers, int &$minX, int &$minY, int &$maxX, int &$maxY): void
    {
        foreach ($layers as $layer) {
            if (($layer['visible'] ?? true) && ($layer['type'] ?? '') !== 'group') {
                $x = $layer['x'] ?? 0;
                $y = $layer['y'] ?? 0;
                $width = $layer['width'] ?? 0;
                $height = $layer['height'] ?? 0;

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x + $width);
                $maxY = max($maxY, $y + $height);
            }

            if (!empty($layer['children'])) {
                $this->calculateBoundsRecursive($layer['children'], $minX, $minY, $maxX, $maxY);
            }
        }
    }

    /**
     * Create layers from a variant.
     */
    protected function createLayersFromVariant(
        Template $template,
        array $layers,
        array $images,
        array $tags,
        string $variantPath,
        ?int $parentId,
        float $offsetX = 0,
        float $offsetY = 0
    ): void {
        $imagesByIndex = collect($images)->keyBy('layer_index')->all();

        foreach ($layers as $layerData) {
            $layerType = $this->mapLayerType($layerData['type'] ?? '');
            if (!$layerType) {
                continue;
            }

            $properties = $layerData['properties'] ?? [];

            // Get semantic tag from saved tags
            $layerPath = $variantPath . '/' . $layerData['name'];
            if (isset($tags[$layerPath]) && $tags[$layerPath]['semantic_tag']) {
                $properties['semanticTags'] = [$tags[$layerPath]['semantic_tag']];
            }

            // Handle image layers
            if ($layerType === LayerType::IMAGE && isset($layerData['image_id'])) {
                $layerIndex = $layerData['position'];
                if (isset($imagesByIndex[$layerIndex])) {
                    $imageData = $imagesByIndex[$layerIndex];
                    $savedPath = $this->saveImage($imageData['data'], $template->id);
                    if ($savedPath) {
                        $properties['src'] = $savedPath;
                    }
                }
            }

            // Create layer with offset adjustment
            $layer = $template->layers()->create([
                'parent_id' => $parentId,
                'name' => $layerData['name'],
                'type' => $layerType,
                'position' => $layerData['position'],
                'visible' => $layerData['visible'] ?? true,
                'locked' => $layerData['locked'] ?? false,
                'x' => ($layerData['x'] ?? 0) - $offsetX,
                'y' => ($layerData['y'] ?? 0) - $offsetY,
                'width' => $layerData['width'] ?? 100,
                'height' => $layerData['height'] ?? 100,
                'rotation' => $layerData['rotation'] ?? 0,
                'scale_x' => $layerData['scale_x'] ?? 1,
                'scale_y' => $layerData['scale_y'] ?? 1,
                'opacity' => $layerData['opacity'] ?? 1,
                'properties' => $properties,
            ]);

            // Recursively handle children
            if ($layerType === LayerType::GROUP && !empty($layerData['children'])) {
                $this->createLayersFromVariant(
                    $template,
                    $layerData['children'],
                    $images,
                    $tags,
                    $layerPath,
                    $layer->id,
                    $offsetX,
                    $offsetY
                );
            }
        }
    }

    /**
     * Build layers JSON for variant preview.
     */
    protected function buildLayersJsonForVariant(
        array $variantLayer,
        array $images,
        array $tags,
        string $variantPath
    ): array {
        $layers = [];
        $imagesByIndex = collect($images)->keyBy('layer_index')->all();

        $this->buildLayersJsonRecursive(
            $variantLayer['children'] ?? [],
            $imagesByIndex,
            $tags,
            $variantPath,
            $layers
        );

        return $layers;
    }

    /**
     * Recursively build layers JSON.
     */
    protected function buildLayersJsonRecursive(
        array $layers,
        array $imagesByIndex,
        array $tags,
        string $parentPath,
        array &$result
    ): void {
        foreach ($layers as $layerData) {
            $layerPath = $parentPath . '/' . $layerData['name'];
            $properties = $layerData['properties'] ?? [];

            // Add semantic tag
            if (isset($tags[$layerPath]) && $tags[$layerPath]['semantic_tag']) {
                $properties['semanticTags'] = [$tags[$layerPath]['semantic_tag']];
            }

            // Handle image src
            if (($layerData['type'] ?? '') === 'image' && isset($layerData['image_id'])) {
                $layerIndex = $layerData['position'];
                if (isset($imagesByIndex[$layerIndex])) {
                    $properties['src'] = 'data:image/png;base64,' . $imagesByIndex[$layerIndex]['data'];
                }
            }

            $result[] = [
                'name' => $layerData['name'],
                'type' => $layerData['type'],
                'visible' => $layerData['visible'] ?? true,
                'x' => $layerData['x'] ?? 0,
                'y' => $layerData['y'] ?? 0,
                'width' => $layerData['width'] ?? 100,
                'height' => $layerData['height'] ?? 100,
                'rotation' => $layerData['rotation'] ?? 0,
                'opacity' => $layerData['opacity'] ?? 1,
                'properties' => $properties,
            ];

            if (!empty($layerData['children'])) {
                $this->buildLayersJsonRecursive(
                    $layerData['children'],
                    $imagesByIndex,
                    $tags,
                    $layerPath,
                    $result
                );
            }
        }
    }

    /**
     * Render a variant preview using native PSD composite (1:1 accuracy).
     * Uses /render-with-substitution endpoint which preserves original PSD look.
     */
    protected function renderVariantPreview(
        string $psdPath,
        string $variantPath,
        array $tags,
        array $substitutionData
    ): string {
        $psdParserUrl = config('services.psd_parser.url', 'http://psd-parser:3335');
        $timeout = config('services.psd_parser.timeout', 120);

        // Read PSD file
        $disk = Storage::disk('local');
        $psdContent = $disk->get($psdPath);

        // Call psd-parser /render-with-substitution endpoint
        $response = Http::timeout($timeout)
            ->attach('file', $psdContent, basename($psdPath))
            ->post("{$psdParserUrl}/render-with-substitution", [
                'variant_path' => $variantPath,
                'tags' => json_encode($tags),
                'data' => json_encode($substitutionData),
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to render preview: ' . $response->body());
        }

        // Save the rendered image
        $filename = 'psd-previews/' . Str::uuid() . '.png';
        Storage::disk('public')->put($filename, $response->body());

        return '/storage/' . $filename;
    }

    /**
     * Apply semantic tag substitutions to layers.
     */
    protected function applySubstitutionsToLayers(array $layers, array $data): array
    {
        return array_map(function ($layer) use ($data) {
            $properties = $layer['properties'] ?? [];
            $semanticTags = $properties['semanticTags'] ?? [];
            $layerType = $layer['type'] ?? '';

            // Apply substitutions based on semantic tags
            foreach ($semanticTags as $tagValue) {
                $value = $data[$tagValue] ?? null;

                // If content tag and empty value, hide the layer
                if (in_array($tagValue, ['header', 'subtitle', 'paragraph', 'social_handle', 'url', 'cta'])) {
                    if (empty($value)) {
                        $layer['visible'] = false;
                        continue;
                    }
                    // Apply text content
                    $properties['text'] = $value;
                }

                // Apply image
                if (in_array($tagValue, ['main_image', 'logo']) && !empty($value)) {
                    $properties['src'] = $value;
                }

                // Apply colors - use tintColor for images, fill for shapes/text
                if (in_array($tagValue, ['primary_color', 'secondary_color']) && !empty($value)) {
                    if ($layerType === 'image') {
                        // For images, use tintColor to recolor icons/shapes
                        $properties['tintColor'] = $value;
                    } else {
                        // For shapes and text, use fill
                        $properties['fill'] = $value;
                    }
                }
            }

            $layer['properties'] = $properties;
            return $layer;
        }, $layers);
    }

    /**
     * Convert relative image paths in layers to base64 data URLs.
     * This avoids CORS issues when rendering with Puppeteer/Konva.
     */
    protected function convertImagePathsInLayers(array $layers, string $baseUrl): array
    {
        return array_map(function ($layer) use ($baseUrl) {
            $properties = $layer['properties'] ?? [];

            foreach (['src', 'maskSrc', 'smartObjectSource'] as $key) {
                if (isset($properties[$key]) && is_string($properties[$key])) {
                    $path = $properties[$key];

                    // Skip if already a data URL
                    if (str_starts_with($path, 'data:')) {
                        continue;
                    }

                    // Convert /storage/ paths to base64 to avoid CORS issues
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

            $layer['properties'] = $properties;
            return $layer;
        }, $layers);
    }

    /**
     * Map PSD layer type to LayerType enum.
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
     * Save a base64 image.
     */
    protected function saveImage(string $base64Data, int $templateId): ?string
    {
        try {
            $data = $base64Data;
            if (str_contains($base64Data, ',')) {
                $data = explode(',', $base64Data, 2)[1];
            }

            $imageData = base64_decode($data);
            if ($imageData === false) {
                return null;
            }

            $filename = 'psd-imports/' . $templateId . '/' . Str::uuid() . '.png';
            Storage::disk('public')->put($filename, $imageData);

            return '/storage/' . $filename;
        } catch (Exception $e) {
            Log::error('Failed to save image', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
