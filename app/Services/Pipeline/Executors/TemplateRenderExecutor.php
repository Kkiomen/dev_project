<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmPipelineNode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TemplateRenderExecutor implements PipelineNodeExecutorInterface
{
    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        $canvasData = $inputs['template'] ?? null;

        if (!$canvasData || !is_array($canvasData)) {
            throw new \RuntimeException('Template Render requires a template input');
        }

        // Apply modifications from connected inputs to template layers
        if (isset($canvasData['layers']) && is_array($canvasData['layers'])) {
            $canvasData['layers'] = $this->applyModifications($canvasData['layers'], $inputs);
        }

        $width = $canvasData['width'] ?? 1080;
        $height = $canvasData['height'] ?? 1080;

        // Render via template-renderer microservice
        $rendererUrl = config('services.template_renderer.url', 'http://template-renderer:3336');

        $response = Http::timeout(60)->post("{$rendererUrl}/render-vue", [
            'template' => $canvasData,
            'width' => $width,
            'height' => $height,
            'scale' => 2,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Template rendering failed: ' . $response->body());
        }

        $filename = 'pipelines/' . $brand->id . '/' . uniqid('render_') . '.png';
        Storage::disk('public')->put($filename, $response->body());

        return ['image' => $filename];
    }

    /**
     * Apply input modifications to template layers.
     * Replaces the first image layer with input image, first text layer with input text.
     */
    private function applyModifications(array $layers, array $inputs): array
    {
        $imageReplaced = false;
        $textReplaced = false;

        foreach ($layers as &$layer) {
            $type = $layer['type'] ?? '';

            // Replace first image layer with input image
            if (!$imageReplaced && $type === 'image' && isset($inputs['image'])) {
                $base64 = $this->imageToBase64($inputs['image']);
                if ($base64) {
                    $layer['properties']['src'] = $base64;
                }
                $imageReplaced = true;
            }

            // Replace first text layer with input text
            if (!$textReplaced && $type === 'text' && isset($inputs['text'])) {
                $layer['properties']['text'] = $inputs['text'];
                $textReplaced = true;
            }
        }
        unset($layer);

        return $layers;
    }

    /**
     * Convert a storage image path to base64 data URL for the renderer.
     */
    private function imageToBase64(string $storagePath): ?string
    {
        // Handle paths that already have /storage/ prefix
        $relativePath = str_starts_with($storagePath, '/storage/')
            ? str_replace('/storage/', '', $storagePath)
            : $storagePath;

        $fullPath = Storage::disk('public')->path($relativePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        $imageData = file_get_contents($fullPath);
        $mimeType = mime_content_type($fullPath) ?: 'image/png';

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }
}
