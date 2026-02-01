<?php

namespace App\Services;

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
                'properties' => $layer->properties ?? [],
            ];

            // Apply semantic tag substitutions
            $semanticTagValue = $layer->properties['semanticTag'] ?? null;
            if ($semanticTagValue) {
                $tag = SemanticTag::tryFrom($semanticTagValue);
                if ($tag) {
                    // Get the input key for this tag (some tags share input fields)
                    $inputKey = $tag->inputKey();
                    $value = $data[$inputKey] ?? null;

                    // If value is empty, hide the layer
                    if (empty($value)) {
                        $layerData['visible'] = false;
                    } else {
                        $layerData['properties'] = $this->applySubstitution(
                            $layerData['properties'],
                            $tag,
                            $value
                        );
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
     * Apply substitution based on semantic tag.
     *
     * @param array $properties Layer properties
     * @param SemanticTag $tag The semantic tag
     * @param mixed $value The value to substitute
     * @return array Modified properties
     */
    protected function applySubstitution(array $properties, SemanticTag $tag, mixed $value): array
    {
        $propertyKey = $tag->affectsProperty();
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
