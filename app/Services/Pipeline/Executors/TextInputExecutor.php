<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmPipelineNode;

class TextInputExecutor implements PipelineNodeExecutorInterface
{
    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        $config = $node->config ?? [];
        $text = $config['text'] ?? '';

        if (empty($text)) {
            throw new \RuntimeException('Text input node has no text configured');
        }

        return ['text' => $text];
    }
}
