<?php

namespace App\Services\Pipeline;

use App\Enums\PipelineRunStatus;
use App\Models\Brand;
use App\Models\SmPipeline;
use App\Models\SmPipelineRun;
use Illuminate\Support\Facades\Log;

class PipelineExecutionService
{
    public function __construct(
        private NodeExecutorRegistry $registry,
    ) {}

    public function execute(SmPipeline $pipeline, Brand $brand, ?array $inputData = null): SmPipelineRun
    {
        $pipeline->load(['nodes', 'edges']);

        // Validate pipeline
        $this->validate($pipeline);

        // Create run
        $run = $pipeline->runs()->create([
            'status' => PipelineRunStatus::Running,
            'input_data' => $inputData,
            'started_at' => now(),
        ]);

        try {
            $nodeResults = $this->executeNodes($pipeline, $brand, $inputData ?? []);

            // Find output node results
            $outputNode = $pipeline->nodes->firstWhere('type', 'output');
            $outputData = $outputNode ? ($nodeResults[$outputNode->node_id] ?? null) : null;

            $run->update([
                'status' => PipelineRunStatus::Completed,
                'node_results' => $nodeResults,
                'output_data' => $outputData,
                'output_path' => $outputData['image'] ?? null,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Pipeline execution failed', [
                'pipeline_id' => $pipeline->id,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => PipelineRunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $run->fresh();
    }

    private function validate(SmPipeline $pipeline): void
    {
        if ($pipeline->nodes->isEmpty()) {
            throw new \RuntimeException('Pipeline has no nodes');
        }

        if (!$pipeline->nodes->contains('type', 'output')) {
            throw new \RuntimeException('Pipeline must have an output node');
        }

        // Check for cycles using topological sort
        $sorted = $this->topologicalSort($pipeline);
        if ($sorted === null) {
            throw new \RuntimeException('Pipeline contains a cycle');
        }
    }

    private function executeNodes(SmPipeline $pipeline, Brand $brand, array $inputData): array
    {
        $executionOrder = $this->topologicalSort($pipeline);
        $nodeResults = [];

        foreach ($executionOrder as $nodeId) {
            $node = $pipeline->nodes->firstWhere('node_id', $nodeId);
            if (!$node) continue;

            // Resolve inputs from connected upstream nodes
            $inputs = $this->resolveInputs($node->node_id, $pipeline, $nodeResults);

            // Merge any external input data
            if (isset($inputData[$node->node_id])) {
                $inputs = array_merge($inputs, $inputData[$node->node_id]);
            }

            // Execute the node
            $executor = $this->registry->get($node->type);
            $result = $executor->execute($node, $inputs, $brand);

            $nodeResults[$node->node_id] = $result;
        }

        return $nodeResults;
    }

    private function resolveInputs(string $targetNodeId, SmPipeline $pipeline, array $nodeResults): array
    {
        $inputs = [];

        $incomingEdges = $pipeline->edges->where('target_node_id', $targetNodeId);

        foreach ($incomingEdges as $edge) {
            $sourceResults = $nodeResults[$edge->source_node_id] ?? [];
            $sourceHandle = $edge->source_handle ?? array_key_first($sourceResults);
            $targetHandle = $edge->target_handle ?? $sourceHandle;

            if ($sourceHandle && isset($sourceResults[$sourceHandle])) {
                $inputs[$targetHandle] = $sourceResults[$sourceHandle];
            }
        }

        return $inputs;
    }

    /**
     * Topological sort using Kahn's algorithm.
     * Returns ordered list of node IDs or null if cycle detected.
     */
    private function topologicalSort(SmPipeline $pipeline): ?array
    {
        $nodes = $pipeline->nodes->pluck('node_id')->toArray();
        $edges = $pipeline->edges;

        // Build adjacency list and in-degree count
        $inDegree = array_fill_keys($nodes, 0);
        $adjacency = array_fill_keys($nodes, []);

        foreach ($edges as $edge) {
            if (isset($inDegree[$edge->target_node_id])) {
                $inDegree[$edge->target_node_id]++;
            }
            if (isset($adjacency[$edge->source_node_id])) {
                $adjacency[$edge->source_node_id][] = $edge->target_node_id;
            }
        }

        // Start with nodes that have no incoming edges
        $queue = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $queue[] = $nodeId;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $nodeId = array_shift($queue);
            $sorted[] = $nodeId;

            foreach ($adjacency[$nodeId] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // If sorted doesn't contain all nodes, there's a cycle
        return count($sorted) === count($nodes) ? $sorted : null;
    }

    /**
     * Execute all upstream nodes up to (and including) the target node.
     * Returns the result of the target node.
     */
    public function executeUpTo(SmPipeline $pipeline, Brand $brand, string $targetNodeId, array $manualInputs = []): array
    {
        $pipeline->loadMissing(['nodes', 'edges']);

        $executionOrder = $this->topologicalSort($pipeline);
        if ($executionOrder === null) {
            throw new \RuntimeException('Pipeline contains a cycle');
        }

        // Find which nodes are upstream of the target (including target itself)
        $upstreamIds = $this->findUpstream($targetNodeId, $pipeline);
        $upstreamIds[] = $targetNodeId;

        $nodeResults = [];

        foreach ($executionOrder as $nodeId) {
            if (!in_array($nodeId, $upstreamIds)) continue;

            $node = $pipeline->nodes->firstWhere('node_id', $nodeId);
            if (!$node) continue;

            $inputs = $this->resolveInputs($node->node_id, $pipeline, $nodeResults);

            // For the target node, merge manual inputs (override resolved)
            if ($nodeId === $targetNodeId && !empty($manualInputs)) {
                $inputs = array_merge($inputs, $manualInputs);
            }

            $executor = $this->registry->get($node->type);
            $result = $executor->execute($node, $inputs, $brand);
            $nodeResults[$node->node_id] = $result;
        }

        return $nodeResults[$targetNodeId] ?? [];
    }

    /**
     * Find all upstream node IDs for a given target (recursively).
     */
    private function findUpstream(string $targetNodeId, SmPipeline $pipeline): array
    {
        $upstream = [];
        $incomingEdges = $pipeline->edges->where('target_node_id', $targetNodeId);

        foreach ($incomingEdges as $edge) {
            $sourceId = $edge->source_node_id;
            if (!in_array($sourceId, $upstream)) {
                $upstream[] = $sourceId;
                $upstream = array_merge($upstream, $this->findUpstream($sourceId, $pipeline));
            }
        }

        return array_unique($upstream);
    }

    /**
     * Check if pipeline is simple enough for synchronous execution.
     */
    public function isSimplePipeline(SmPipeline $pipeline): bool
    {
        $nodeCount = $pipeline->nodes->count();
        $hasAiNode = $pipeline->nodes->contains(fn ($n) => $n->type->value === 'ai_image_generator');

        return $nodeCount < 4 && !$hasAiNode;
    }
}
