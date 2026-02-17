<?php

use App\Enums\PipelineNodeType;
use App\Models\Brand;
use App\Models\SmPipeline;
use App\Models\SmPipelineNode;
use App\Models\User;
use App\Services\AI\DirectImageGeneratorService;
use App\Services\Pipeline\Executors\AiImageGeneratorExecutor;

beforeEach(function () {
    $this->mockService = Mockery::mock(DirectImageGeneratorService::class);
    $this->executor = new AiImageGeneratorExecutor($this->mockService);

    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
    $this->pipeline = SmPipeline::create([
        'brand_id' => $this->brand->id,
        'name' => 'Test Pipeline',
    ]);
});

describe('AiImageGeneratorExecutor', function () {

    it('calls generateFromPrompt when no image input', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-1',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => ['prompt' => 'A sunset'],
        ]);

        $this->mockService
            ->shouldReceive('generateFromPrompt')
            ->once()
            ->with($this->brand, 'A sunset', Mockery::type('array'))
            ->andReturn(['success' => true, 'image_path' => 'pipelines/1/img.jpg']);

        $result = $this->executor->execute($node, [], $this->brand);

        expect($result)->toHaveKey('image');
        expect($result['image'])->toBe('pipelines/1/img.jpg');
    });

    it('uses prompt from node config when no text input', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-2',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => ['prompt' => 'Config prompt value'],
        ]);

        $this->mockService
            ->shouldReceive('generateFromPrompt')
            ->once()
            ->withArgs(function ($brand, $prompt) {
                return $prompt === 'Config prompt value';
            })
            ->andReturn(['success' => true, 'image_path' => 'pipelines/1/img.jpg']);

        $this->executor->execute($node, [], $this->brand);
    });

    it('calls generateFromImage when image input present', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-3',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => [],
        ]);

        $this->mockService
            ->shouldReceive('generateFromImage')
            ->once()
            ->with(
                $this->brand,
                'Edit prompt',
                'pipelines/1/source.jpg',
                Mockery::type('array')
            )
            ->andReturn(['success' => true, 'image_path' => 'pipelines/1/result.jpg']);

        $result = $this->executor->execute($node, [
            'text' => 'Edit prompt',
            'image' => 'pipelines/1/source.jpg',
        ], $this->brand);

        expect($result['image'])->toBe('pipelines/1/result.jpg');
    });

    it('resolves nano-banana t2i model to edit model for i2i', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-4',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => ['model' => 'google/nano-banana/text-to-image'],
        ]);

        $this->mockService
            ->shouldReceive('generateFromImage')
            ->once()
            ->withArgs(function ($brand, $prompt, $path, $config) {
                return $config['model'] === 'google/nano-banana/edit';
            })
            ->andReturn(['success' => true, 'image_path' => 'pipelines/1/result.jpg']);

        $this->executor->execute($node, [
            'text' => 'Edit prompt',
            'image' => 'pipelines/1/source.jpg',
        ], $this->brand);
    });

    it('uses default i2i model when no model in config', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-5',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => [],
        ]);

        $this->mockService
            ->shouldReceive('generateFromImage')
            ->once()
            ->withArgs(function ($brand, $prompt, $path, $config) {
                return $config['model'] === 'google/nano-banana/edit';
            })
            ->andReturn(['success' => true, 'image_path' => 'pipelines/1/result.jpg']);

        $this->executor->execute($node, [
            'text' => 'Edit prompt',
            'image' => 'pipelines/1/source.jpg',
        ], $this->brand);
    });

    it('throws when prompt is empty', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-6',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => [],
        ]);

        $this->executor->execute($node, [], $this->brand);
    })->throws(RuntimeException::class, 'AI Image Generator requires a text prompt');

    it('throws when generation fails', function () {
        $node = SmPipelineNode::create([
            'pipeline_id' => $this->pipeline->id,
            'position' => ['x' => 0, 'y' => 0],
            'node_id' => 'node-7',
            'type' => PipelineNodeType::AiImageGenerator,
            'label' => 'AI Image',
            'config' => ['prompt' => 'A prompt'],
        ]);

        $this->mockService
            ->shouldReceive('generateFromPrompt')
            ->once()
            ->andReturn(['success' => false, 'error' => 'Rate limit exceeded']);

        $this->executor->execute($node, [], $this->brand);
    })->throws(RuntimeException::class, 'AI image generation failed: Rate limit exceeded');

});
