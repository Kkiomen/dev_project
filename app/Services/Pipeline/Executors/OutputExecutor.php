<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmPipelineNode;

class OutputExecutor implements PipelineNodeExecutorInterface
{
    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        // Pass-through: collect all inputs as the final output
        return $inputs;
    }
}
