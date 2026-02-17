<?php

namespace App\Contracts;

use App\Models\Brand;
use App\Models\SmPipelineNode;

interface PipelineNodeExecutorInterface
{
    /**
     * Execute a pipeline node with the given inputs.
     *
     * @param SmPipelineNode $node The node to execute
     * @param array $inputs Resolved inputs from upstream nodes (keyed by handle name)
     * @param Brand $brand The brand context
     * @return array Outputs keyed by handle name (e.g., ['image' => '/path/to/image.png'])
     */
    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array;
}
