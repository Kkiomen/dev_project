<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmPipelineNode;

class ImageInputExecutor implements PipelineNodeExecutorInterface
{
    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        $config = $node->config ?? [];
        $source = $config['source'] ?? 'upload';

        $imagePath = match ($source) {
            'upload' => $config['image_path'] ?? null,
            'gallery' => $config['image_path'] ?? null,
            'url' => $config['image_url'] ?? null,
            default => null,
        };

        if (!$imagePath) {
            throw new \RuntimeException('Image input node has no image configured');
        }

        return ['image' => $imagePath];
    }
}
