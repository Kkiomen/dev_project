<?php

namespace App\Services\Pipeline\Executors;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Models\Brand;
use App\Models\SmDesignTemplate;
use App\Models\SmPipelineNode;

class TemplateExecutor implements PipelineNodeExecutorInterface
{
    public function execute(SmPipelineNode $node, array $inputs, Brand $brand): array
    {
        $config = $node->config ?? [];
        $templateId = $config['template_id'] ?? null;

        if (!$templateId) {
            throw new \RuntimeException('Template node has no template selected');
        }

        $template = SmDesignTemplate::findByPublicId($templateId);
        if (!$template) {
            throw new \RuntimeException("Template not found: {$templateId}");
        }

        // Embed width/height into canvas data so downstream nodes can use them
        $canvasData = $template->canvas_json ?? [];
        $canvasData['width'] = $template->width;
        $canvasData['height'] = $template->height;

        return ['template' => $canvasData];
    }
}
