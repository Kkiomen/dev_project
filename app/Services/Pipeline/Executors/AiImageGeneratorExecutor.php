<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmPipelineNode;
use App\Services\AI\DirectImageGeneratorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AiImageGeneratorExecutor implements PipelineNodeExecutorInterface
{
    public function __construct(
        private DirectImageGeneratorService $imageGenerator,
    ) {}

    private const T2I_TO_I2I_MAP = [
        'google/nano-banana/text-to-image' => 'google/nano-banana/edit',
        'google/nano-banana-pro/text-to-image' => 'google/nano-banana-pro/edit',
        'openai/gpt-image-1.5/text-to-image' => 'openai/gpt-image-1.5/edit',
        'openai/gpt-image-1/text-to-image' => 'openai/gpt-image-1-mini/edit',
        'alibaba/wan-2.6/text-to-image' => 'alibaba/wan-2.6/image-edit',
        'alibaba/wan-2.5/text-to-image' => 'alibaba/wan-2.5/image-edit',
        'wavespeed-ai/qwen-image/text-to-image' => 'wavespeed-ai/wan-2.2/image-to-image',
        'bytedance/dreamina-v3.0/text-to-image' => 'bytedance/dreamina-v3.0/edit',
    ];

    private const DEFAULT_I2I_MODEL = 'google/nano-banana/edit';

    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        $config = $node->config ?? [];
        $prompt = $inputs['text'] ?? $config['prompt'] ?? '';
        $inputImage = $inputs['image'] ?? null;

        if (empty($prompt)) {
            throw new \RuntimeException('AI Image Generator requires a text prompt');
        }

        if ($inputImage) {
            $result = $this->generateImageToImage($brand, $prompt, $inputImage, $config);
        } else {
            $result = $this->imageGenerator->generateFromPrompt($brand, $prompt, $config);
        }

        if (!$result['success']) {
            throw new \RuntimeException('AI image generation failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        $imagePath = $result['image_path'];

        // If template input exists, compose generated image into template
        if (isset($inputs['template']) && is_array($inputs['template'])) {
            $imagePath = $this->composeWithTemplate($imagePath, $inputs, $brand);
        }

        return ['image' => $imagePath];
    }

    private function generateImageToImage(Brand $brand, string $prompt, string $inputImage, array $config): array
    {
        $i2iModel = $this->resolveImageToImageModel($config['model'] ?? null);
        $i2iConfig = array_merge($config, ['model' => $i2iModel]);

        return $this->imageGenerator->generateFromImage($brand, $prompt, $inputImage, $i2iConfig);
    }

    private function resolveImageToImageModel(?string $t2iModel): string
    {
        if (!$t2iModel) {
            return self::DEFAULT_I2I_MODEL;
        }

        return self::T2I_TO_I2I_MAP[$t2iModel] ?? self::DEFAULT_I2I_MODEL;
    }

    /**
     * Compose the generated image into a template by replacing the first image layer.
     * Sends the modified canvas to the template-renderer microservice for rendering.
     */
    private function composeWithTemplate(string $generatedImagePath, array $inputs, Brand $brand): string
    {
        $canvasData = $inputs['template'];

        // Replace first image layer in template with the generated image
        if (isset($canvasData['layers']) && is_array($canvasData['layers'])) {
            $imageReplaced = false;

            foreach ($canvasData['layers'] as &$layer) {
                $type = $layer['type'] ?? '';

                // Replace first image layer with generated image
                if (!$imageReplaced && $type === 'image') {
                    $base64 = $this->imageToBase64($generatedImagePath);
                    if ($base64) {
                        $layer['properties']['src'] = $base64;
                    }
                    $imageReplaced = true;
                }
            }
            unset($layer);
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
            throw new \RuntimeException('Template composition failed: ' . $response->body());
        }

        $filename = 'pipelines/' . $brand->id . '/' . uniqid('compose_') . '.png';
        Storage::disk('public')->put($filename, $response->body());

        return $filename;
    }

    /**
     * Convert a storage image path to base64 data URL for the renderer.
     */
    private function imageToBase64(string $storagePath): ?string
    {
        $fullPath = Storage::disk('public')->path($storagePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        $imageData = file_get_contents($fullPath);
        $mimeType = mime_content_type($fullPath) ?: 'image/png';

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }
}
