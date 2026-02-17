<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmPipelineNode;
use App\Services\AI\ImageAnalysisService;

class ImageAnalysisExecutor implements PipelineNodeExecutorInterface
{
    public function __construct(
        private ImageAnalysisService $analysisService,
    ) {}

    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        $imagePath = $inputs['image'] ?? null;

        if (!$imagePath) {
            throw new \RuntimeException('Image Analysis requires an image input');
        }

        $analysis = $this->analysisService->analyze($imagePath);

        return [
            'analysis' => $analysis,
            'image' => $imagePath,
        ];
    }
}
