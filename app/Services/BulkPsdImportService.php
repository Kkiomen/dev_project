<?php

namespace App\Services;

use App\Models\Layer;
use App\Models\PsdImport;
use App\Models\Template;
use App\Models\TemplateGroup;
use App\Models\User;
use App\Services\AI\TemplateNamingService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkPsdImportService
{
    protected PsdImportService $psdImportService;
    protected ?TemplateNamingService $namingService = null;

    public function __construct(PsdImportService $psdImportService)
    {
        $this->psdImportService = $psdImportService;

        // Try to inject naming service (may fail if OpenAI not configured)
        try {
            $this->namingService = app(TemplateNamingService::class);
        } catch (Exception $e) {
            Log::info('BulkPsdImportService: AI naming service not available, using fallback names');
        }
    }

    /**
     * Import a PSD file from a PsdImport record.
     *
     * @param PsdImport $import
     * @param User $user
     * @return array{templates: Template[], group: TemplateGroup|null}
     * @throws Exception
     */
    public function importFromPath(PsdImport $import, User $user): array
    {
        $filePath = $import->file_path;

        if (!Storage::disk('local')->exists($filePath)) {
            throw new Exception(__('bulk_import.messages.file_not_found'));
        }

        // Create a temporary UploadedFile from the storage path
        $fullPath = Storage::disk('local')->path($filePath);
        $uploadedFile = new UploadedFile(
            $fullPath,
            $import->filename,
            'application/octet-stream',
            null,
            true
        );

        // Parse the PSD file
        $parsedData = $this->psdImportService->parseWithPythonService($uploadedFile);

        // Detect variant groups in root-level layers
        $variantGroups = $this->detectVariantGroups($parsedData['layers']);

        Log::info('PSD Bulk Import: Variant detection', [
            'filename' => $import->filename,
            'variant_count' => count($variantGroups),
            'variants' => array_keys($variantGroups),
        ]);

        return DB::transaction(function () use ($parsedData, $user, $import, $variantGroups) {
            if (count($variantGroups) > 1) {
                // Multiple variants found - create a group
                return $this->importVariantGroup($parsedData, $user, $import, $variantGroups);
            }

            // Single template or no variant pattern detected
            $template = $this->importSingleTemplate($parsedData, $user, $import);

            return [
                'templates' => [$template],
                'group' => null,
            ];
        });
    }

    /**
     * Detect variant groups from root-level layers.
     * Looks for patterns like "Post 1", "Post 2", "Slide 1", etc.
     *
     * @param array $layers
     * @return array<int, array> Map of variant number to layer data
     */
    protected function detectVariantGroups(array $layers): array
    {
        $variants = [];
        $pattern = '/^(post|slide|slajd|wariant|variant)\s*(\d+)$/iu';

        foreach ($layers as $layer) {
            if ($layer['type'] !== 'group') {
                continue;
            }

            if (preg_match($pattern, trim($layer['name']), $matches)) {
                $variantNumber = (int) $matches[2];
                $variants[$variantNumber] = $layer;
            }
        }

        // Sort by variant number
        ksort($variants);

        return $variants;
    }

    /**
     * Import a single template (no variant detection).
     *
     * @param array $parsedData
     * @param User $user
     * @param PsdImport $import
     * @return Template
     */
    protected function importSingleTemplate(array $parsedData, User $user, PsdImport $import): Template
    {
        // Generate AI-powered name or fallback to filename-based name
        $templateName = $this->generateAITemplateName($parsedData);

        $template = Template::create([
            'user_id' => $user->id,
            'psd_import_id' => $import->id,
            'name' => $templateName,
            'width' => $parsedData['width'],
            'height' => $parsedData['height'],
            'background_color' => $parsedData['background_color'] ?? '#FFFFFF',
            'is_library' => true,
            'settings' => [
                'imported_from' => 'psd_bulk',
                'source_filename' => $import->filename,
                'warnings' => $parsedData['warnings'] ?? [],
            ],
        ]);

        $this->createLayers(
            $template,
            $parsedData['layers'],
            $parsedData['images'] ?? [],
            $parsedData['masks'] ?? [],
            $parsedData['smart_object_sources'] ?? []
        );

        $this->createFonts($template, $parsedData['fonts'] ?? []);

        Log::info('PSD Bulk Import: Single template created', [
            'template_id' => $template->id,
            'template_name' => $template->name,
        ]);

        return $template->load('layers.parent', 'fonts');
    }

    /**
     * Import multiple variants as a template group.
     *
     * @param array $parsedData
     * @param User $user
     * @param PsdImport $import
     * @param array $variantGroups
     * @return array{templates: Template[], group: TemplateGroup}
     */
    protected function importVariantGroup(array $parsedData, User $user, PsdImport $import, array $variantGroups): array
    {
        // Generate AI-powered names for all variants at once
        $variantNumbers = array_keys($variantGroups);
        $variantNames = $this->generateAIVariantNames($parsedData, $variantNumbers);

        // Use first generated name as group name (or generate separate)
        $groupName = $this->generateAIGroupName($parsedData);

        // Create the template group
        $group = TemplateGroup::create([
            'user_id' => $user->id,
            'name' => $groupName,
            'source_filename' => $import->filename,
            'template_count' => count($variantGroups),
        ]);

        Log::info('PSD Bulk Import: Template group created', [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'variant_count' => count($variantGroups),
        ]);

        $templates = [];

        foreach ($variantGroups as $variantNumber => $variantLayer) {
            $templateName = $variantNames[$variantNumber] ?? $this->generateFallbackVariantName($parsedData, $variantNumber);

            $template = Template::create([
                'user_id' => $user->id,
                'template_group_id' => $group->id,
                'variant_order' => $variantNumber,
                'psd_import_id' => $import->id,
                'name' => $templateName,
                'width' => $parsedData['width'],
                'height' => $parsedData['height'],
                'background_color' => $parsedData['background_color'] ?? '#FFFFFF',
                'is_library' => true,
                'settings' => [
                    'imported_from' => 'psd_bulk',
                    'source_filename' => $import->filename,
                    'variant_number' => $variantNumber,
                    'warnings' => $parsedData['warnings'] ?? [],
                ],
            ]);

            // Import only layers from this variant group's children
            // and any background/common layers
            $layersToImport = $this->extractVariantLayers(
                $parsedData['layers'],
                $variantLayer
            );

            $this->createLayers(
                $template,
                $layersToImport,
                $parsedData['images'] ?? [],
                $parsedData['masks'] ?? [],
                $parsedData['smart_object_sources'] ?? []
            );

            $this->createFonts($template, $parsedData['fonts'] ?? []);

            Log::info('PSD Bulk Import: Variant template created', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'variant_number' => $variantNumber,
                'group_id' => $group->id,
            ]);

            $templates[] = $template->load('layers.parent', 'fonts');
        }

        return [
            'templates' => $templates,
            'group' => $group,
        ];
    }

    /**
     * Extract layers for a specific variant, including background layers.
     *
     * @param array $allLayers
     * @param array $variantLayer
     * @return array
     */
    protected function extractVariantLayers(array $allLayers, array $variantLayer): array
    {
        $result = [];
        $variantPattern = '/^(post|slide|slajd|wariant|variant)\s*\d+$/iu';

        // First, add any layers that are NOT variant groups (background, common elements)
        foreach ($allLayers as $layer) {
            if ($layer['type'] === 'group' && preg_match($variantPattern, trim($layer['name']))) {
                continue; // Skip other variant groups
            }
            $result[] = $layer;
        }

        // Then add the children of the specific variant group
        if (!empty($variantLayer['children'])) {
            foreach ($variantLayer['children'] as $child) {
                $result[] = $child;
            }
        }

        return $result;
    }

    /**
     * Create layers for a template (delegating to existing logic).
     */
    protected function createLayers(Template $template, array $layers, array $images, array $masks, array $smartObjectSources): void
    {
        // Use reflection or re-implement the layer creation logic
        // For now, we'll use a simplified version that matches PsdImportService

        $imagesByLayerIndex = collect($images)->keyBy('layer_index')->all();
        $masksByLayerIndex = collect($masks)->keyBy('layer_index')->all();

        $smartObjectSourcePaths = [];
        foreach ($smartObjectSources as $source) {
            $uniqueId = $source['unique_id'] ?? null;
            if ($uniqueId && isset($source['data'])) {
                $savedPath = $this->saveImage($source['data'], $template->id, "so-{$uniqueId}");
                if ($savedPath) {
                    $smartObjectSourcePaths[$uniqueId] = $savedPath;
                }
            }
        }

        $this->createLayersRecursive($template, $layers, $imagesByLayerIndex, $masksByLayerIndex, $smartObjectSourcePaths, null);
    }

    /**
     * Recursively create layers.
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
            $layerType = $this->mapLayerType($layerData['type']);
            if (!$layerType) {
                continue;
            }

            $properties = $layerData['properties'] ?? [];

            // Handle images
            if ($layerType->value === 'image' && isset($layerData['image_id'])) {
                $layerIndex = $layerData['position'];
                if (isset($imagesByLayerIndex[$layerIndex])) {
                    $imageData = $imagesByLayerIndex[$layerIndex];
                    $savedPath = $this->saveImage($imageData['data'], $template->id);
                    if ($savedPath) {
                        $properties['src'] = $savedPath;
                    }
                }

                // Handle masks
                if (isset($layerData['mask_id']) && isset($masksByLayerIndex[$layerIndex])) {
                    $maskData = $masksByLayerIndex[$layerIndex];
                    $maskPath = $this->saveImage($maskData['data'], $template->id, "mask-{$layerIndex}");
                    if ($maskPath) {
                        $properties['maskSrc'] = $maskPath;
                        $properties['maskWidth'] = $maskData['width'] ?? 0;
                        $properties['maskHeight'] = $maskData['height'] ?? 0;
                        $properties['maskOffsetX'] = $maskData['offset_x'] ?? 0;
                        $properties['maskOffsetY'] = $maskData['offset_y'] ?? 0;
                    }
                }

                // Handle smart objects
                $smartObjectSourceId = $properties['smartObjectSourceId'] ?? null;
                if ($smartObjectSourceId && isset($smartObjectSourcePaths[$smartObjectSourceId])) {
                    $properties['smartObjectSource'] = $smartObjectSourcePaths[$smartObjectSourceId];
                }
            }

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
                'opacity' => $layerData['opacity'] ?? 1,
                'properties' => $properties,
            ]);

            // Recursively create children for groups
            if ($layerType->value === 'group' && !empty($layerData['children'])) {
                $this->createLayersRecursive($template, $layerData['children'], $imagesByLayerIndex, $masksByLayerIndex, $smartObjectSourcePaths, $layer->id);
            }
        }
    }

    /**
     * Create font records for a template.
     */
    protected function createFonts(Template $template, array $fonts): void
    {
        foreach ($fonts as $fontData) {
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
    }

    /**
     * Save a base64 image to storage.
     */
    protected function saveImage(string $base64Data, int $templateId, ?string $filenamePrefix = null): ?string
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

            $fileId = $filenamePrefix ? "{$filenamePrefix}-" . Str::uuid() : Str::uuid();
            $filename = 'psd-imports/' . $templateId . '/' . $fileId . '.png';

            Storage::disk('public')->put($filename, $imageData);

            return '/storage/' . $filename;
        } catch (Exception $e) {
            Log::error('Failed to save PSD image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Map PSD layer type string to LayerType enum.
     */
    protected function mapLayerType(string $type): ?\App\Enums\LayerType
    {
        return match ($type) {
            'text' => \App\Enums\LayerType::TEXT,
            'image' => \App\Enums\LayerType::IMAGE,
            'rectangle' => \App\Enums\LayerType::RECTANGLE,
            'ellipse' => \App\Enums\LayerType::ELLIPSE,
            'group' => \App\Enums\LayerType::GROUP,
            default => null,
        };
    }

    /**
     * Generate AI-powered template name.
     */
    protected function generateAITemplateName(array $parsedData): string
    {
        if ($this->namingService) {
            try {
                return $this->namingService->generateName($parsedData);
            } catch (Exception $e) {
                Log::warning('AI template naming failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->generateFallbackName($parsedData);
    }

    /**
     * Generate AI-powered names for all variants.
     */
    protected function generateAIVariantNames(array $parsedData, array $variantNumbers): array
    {
        if ($this->namingService) {
            try {
                return $this->namingService->generateNamesForVariants($parsedData, $variantNumbers);
            } catch (Exception $e) {
                Log::warning('AI variant naming failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback names
        $names = [];
        foreach ($variantNumbers as $num) {
            $names[$num] = $this->generateFallbackVariantName($parsedData, $num);
        }
        return $names;
    }

    /**
     * Generate AI-powered group name.
     */
    protected function generateAIGroupName(array $parsedData): string
    {
        if ($this->namingService) {
            try {
                return $this->namingService->generateName($parsedData) . ' Collection';
            } catch (Exception $e) {
                Log::warning('AI group naming failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->generateFallbackName($parsedData) . ' Collection';
    }

    /**
     * Generate fallback name based on template dimensions.
     */
    protected function generateFallbackName(array $parsedData): string
    {
        $width = $parsedData['width'] ?? 1080;
        $height = $parsedData['height'] ?? 1080;

        return $this->detectFormatName($width, $height);
    }

    /**
     * Generate fallback variant name.
     */
    protected function generateFallbackVariantName(array $parsedData, int $variantNumber): string
    {
        $baseName = $this->generateFallbackName($parsedData);
        return "{$baseName} - Style {$variantNumber}";
    }

    /**
     * Detect format name based on dimensions.
     */
    protected function detectFormatName(int $width, int $height): string
    {
        $ratio = $width / $height;

        if (abs($ratio - 1) < 0.1) {
            return 'Square Post';
        }

        if ($ratio < 0.7) {
            if ($width == 1080 && $height == 1920) {
                return 'Story Template';
            }
            return 'Vertical Post';
        }

        if ($ratio > 1.5) {
            if ($width == 1920 && $height == 1080) {
                return 'Video Thumbnail';
            }
            return 'Horizontal Banner';
        }

        if (abs($ratio - 0.8) < 0.1) {
            return 'Portrait Post';
        }

        return 'Social Media Post';
    }

    /**
     * Generate template name from filename (legacy fallback).
     */
    protected function generateTemplateName(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return Str::title(str_replace(['_', '-'], ' ', $name));
    }

    /**
     * Generate group name from filename (legacy fallback).
     */
    protected function generateGroupName(string $filename): string
    {
        return $this->generateTemplateName($filename);
    }

    /**
     * Generate variant template name (legacy fallback).
     */
    protected function generateVariantTemplateName(string $filename, int $variantNumber): string
    {
        $baseName = $this->generateTemplateName($filename);
        return "{$baseName} - " . __('bulk_import.template_groups.variant') . " {$variantNumber}";
    }
}
