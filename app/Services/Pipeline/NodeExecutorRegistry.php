<?php

namespace App\Services\Pipeline;

use App\Contracts\PipelineNodeExecutorInterface;
use App\Enums\PipelineNodeType;
use App\Services\Pipeline\Executors\AiImageGeneratorExecutor;
use App\Services\Pipeline\Executors\ImageAnalysisExecutor;
use App\Services\Pipeline\Executors\ImageInputExecutor;
use App\Services\Pipeline\Executors\OutputExecutor;
use App\Services\Pipeline\Executors\TemplateExecutor;
use App\Services\Pipeline\Executors\TemplateRenderExecutor;
use App\Services\Pipeline\Executors\TextInputExecutor;

class NodeExecutorRegistry
{
    private array $executors = [];

    public function __construct(
        ImageInputExecutor $imageInput,
        TextInputExecutor $textInput,
        TemplateExecutor $template,
        AiImageGeneratorExecutor $aiImageGenerator,
        ImageAnalysisExecutor $imageAnalysis,
        TemplateRenderExecutor $templateRender,
        OutputExecutor $output,
    ) {
        $this->executors = [
            PipelineNodeType::ImageInput->value => $imageInput,
            PipelineNodeType::TextInput->value => $textInput,
            PipelineNodeType::Template->value => $template,
            PipelineNodeType::AiImageGenerator->value => $aiImageGenerator,
            PipelineNodeType::ImageAnalysis->value => $imageAnalysis,
            PipelineNodeType::TemplateRender->value => $templateRender,
            PipelineNodeType::Output->value => $output,
        ];
    }

    public function get(PipelineNodeType $type): PipelineNodeExecutorInterface
    {
        $executor = $this->executors[$type->value] ?? null;

        if (!$executor) {
            throw new \RuntimeException("No executor registered for node type: {$type->value}");
        }

        return $executor;
    }
}
