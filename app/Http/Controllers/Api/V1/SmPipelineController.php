<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PipelineNodeType;
use App\Enums\PipelineRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SmPipelineResource;
use App\Http\Resources\SmPipelineRunResource;
use App\Jobs\SmManager\SmExecutePipelineJob;
use App\Models\Brand;
use App\Models\SmPipeline;
use App\Models\SmPipelineRun;
use App\Services\Pipeline\NodeExecutorRegistry;
use App\Services\Pipeline\PipelineExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class SmPipelineController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = SmPipeline::forBrand($brand->id)
            ->withCount(['nodes', 'runs'])
            ->with(['runs' => fn ($q) => $q->latest()->limit(1)])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $pipelines = $query->paginate($request->input('per_page', 20));

        return SmPipelineResource::collection($pipelines);
    }

    public function store(Request $request, Brand $brand): SmPipelineResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $pipeline = $brand->pipelines()->create([
            ...$validated,
            'status' => 'draft',
        ]);

        $pipeline->loadCount(['nodes', 'runs']);

        return new SmPipelineResource($pipeline);
    }

    public function show(Request $request, Brand $brand, SmPipeline $smPipeline): SmPipelineResource
    {
        $this->authorize('view', $brand);

        $smPipeline->load(['nodes', 'edges', 'runs' => fn ($q) => $q->latest()->limit(1)]);
        $smPipeline->loadCount(['nodes', 'runs']);

        return new SmPipelineResource($smPipeline);
    }

    public function update(Request $request, Brand $brand, SmPipeline $smPipeline): SmPipelineResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:draft,active,archived'],
        ]);

        $smPipeline->update($validated);
        $smPipeline->loadCount(['nodes', 'runs']);

        return new SmPipelineResource($smPipeline);
    }

    public function destroy(Request $request, Brand $brand, SmPipeline $smPipeline): JsonResponse
    {
        $this->authorize('update', $brand);

        $smPipeline->delete();

        return response()->json(['message' => 'Pipeline deleted']);
    }

    public function saveCanvas(Request $request, Brand $brand, SmPipeline $smPipeline): SmPipelineResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'canvas_state' => ['nullable', 'array'],
            'nodes' => ['required', 'array'],
            'nodes.*.node_id' => ['required', 'string'],
            'nodes.*.type' => ['required', 'string'],
            'nodes.*.label' => ['nullable', 'string', 'max:255'],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.position.x' => ['required', 'numeric'],
            'nodes.*.position.y' => ['required', 'numeric'],
            'nodes.*.config' => ['nullable', 'array'],
            'nodes.*.data' => ['nullable', 'array'],
            'edges' => ['present', 'array'],
            'edges.*.edge_id' => ['required', 'string'],
            'edges.*.source_node_id' => ['required', 'string'],
            'edges.*.source_handle' => ['nullable', 'string'],
            'edges.*.target_node_id' => ['required', 'string'],
            'edges.*.target_handle' => ['nullable', 'string'],
        ]);

        // Update canvas viewport state
        $smPipeline->update(['canvas_state' => $validated['canvas_state'] ?? null]);

        // Sync nodes
        $smPipeline->nodes()->delete();
        foreach ($validated['nodes'] as $nodeData) {
            $smPipeline->nodes()->create($nodeData);
        }

        // Sync edges
        $smPipeline->edges()->delete();
        foreach ($validated['edges'] as $edgeData) {
            $smPipeline->edges()->create($edgeData);
        }

        $smPipeline->load(['nodes', 'edges']);
        $smPipeline->loadCount(['nodes', 'runs']);

        return new SmPipelineResource($smPipeline);
    }

    public function runs(Request $request, Brand $brand, SmPipeline $smPipeline): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $runs = $smPipeline->runs()->latest()->paginate($request->input('per_page', 10));

        return SmPipelineRunResource::collection($runs);
    }

    public function runStatus(Request $request, Brand $brand, SmPipeline $smPipeline, SmPipelineRun $run): SmPipelineRunResource
    {
        $this->authorize('view', $brand);

        return new SmPipelineRunResource($run);
    }

    public function execute(Request $request, Brand $brand, SmPipeline $smPipeline, PipelineExecutionService $executionService): SmPipelineRunResource|JsonResponse
    {
        $this->authorize('update', $brand);

        $inputData = $request->input('input_data');

        $smPipeline->load(['nodes', 'edges']);

        // Simple pipelines: execute synchronously
        if ($executionService->isSimplePipeline($smPipeline)) {
            $run = $executionService->execute($smPipeline, $brand, $inputData);
            return new SmPipelineRunResource($run);
        }

        // Complex pipelines: dispatch async job
        $run = $smPipeline->runs()->create([
            'status' => PipelineRunStatus::Pending,
            'input_data' => $inputData,
        ]);

        SmExecutePipelineJob::dispatch($smPipeline, $run);

        return new SmPipelineRunResource($run);
    }

    public function previewNode(Request $request, Brand $brand, SmPipeline $smPipeline, string $nodeId, PipelineExecutionService $executionService): JsonResponse
    {
        $this->authorize('view', $brand);

        $smPipeline->load(['nodes', 'edges']);

        $node = $smPipeline->nodes->firstWhere('node_id', $nodeId);
        if (!$node) {
            return response()->json(['message' => 'Node not found'], 404);
        }

        try {
            $result = $executionService->executeUpTo($smPipeline, $brand, $nodeId, $request->input('inputs', []));
            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function nodeTypes(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $types = collect(PipelineNodeType::cases())->map(fn ($type) => [
            'type' => $type->value,
            'label' => $type->label(),
            'color' => $type->color(),
            'icon' => $type->icon(),
            'inputs' => $type->inputs(),
            'outputs' => $type->outputs(),
            'required_inputs' => $type->requiredInputs(),
        ]);

        return response()->json(['data' => $types]);
    }

    public function uploadNodeImage(Request $request, Brand $brand, SmPipeline $smPipeline): JsonResponse
    {
        $this->authorize('update', $brand);

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $file = $request->file('image');
        $path = $file->store("pipelines/{$brand->id}/inputs", 'public');

        return response()->json([
            'data' => [
                'image_path' => $path,
                'image_url' => Storage::disk('public')->url($path),
            ],
        ]);
    }
}
