<script setup>
import { ref, watch } from 'vue';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { usePipelinesStore } from '@/stores/pipelines';

import ImageInputNode from './nodes/ImageInputNode.vue';
import TextInputNode from './nodes/TextInputNode.vue';
import TemplateNode from './nodes/TemplateNode.vue';
import AiImageGeneratorNode from './nodes/AiImageGeneratorNode.vue';
import ImageAnalysisNode from './nodes/ImageAnalysisNode.vue';
import TemplateRenderNode from './nodes/TemplateRenderNode.vue';
import OutputNode from './nodes/OutputNode.vue';

const props = defineProps({
    onDragOver: { type: Function, required: true },
    onDrop: { type: Function, required: true },
    isValidConnection: { type: Function, required: true },
    onNodeClick: { type: Function, required: true },
    onPaneClick: { type: Function, required: true },
    interactionMode: { type: String, default: 'select' },
});

const emit = defineEmits(['viewport-change']);

const store = usePipelinesStore();
const vueFlowRef = ref(null);
const { addEdges, zoomIn, zoomOut, fitView } = useVueFlow();

const nodeTypes = {
    image_input: ImageInputNode,
    text_input: TextInputNode,
    template: TemplateNode,
    ai_image_generator: AiImageGeneratorNode,
    image_analysis: ImageAnalysisNode,
    template_render: TemplateRenderNode,
    output: OutputNode,
};

const defaultEdgeOptions = {
    type: 'default',
    style: { stroke: '#94a3b8', strokeWidth: 1.5 },
};

const handleDrop = (event) => {
    if (vueFlowRef.value) {
        props.onDrop(event, vueFlowRef.value);
    }
};

const handleConnect = (params) => {
    if (!props.isValidConnection(params)) return;
    const newEdge = {
        id: `e_${params.source}_${params.sourceHandle || 'default'}_${params.target}_${params.targetHandle || 'default'}`,
        source: params.source,
        target: params.target,
        sourceHandle: params.sourceHandle,
        targetHandle: params.targetHandle,
        type: 'default',
        style: { stroke: '#94a3b8', strokeWidth: 1.5 },
    };
    // Add to VueFlow internal state (visual rendering)
    addEdges([newEdge]);
    // Also push to Pinia store directly (for saveCanvas persistence)
    if (!store.edges.find(e => e.id === newEdge.id)) {
        store.edges.push(newEdge);
    }
    store.isDirty = true;
};

const onViewportChange = (viewport) => {
    emit('viewport-change', viewport);
};

const onNodesChange = (changes) => {
    changes.forEach(change => {
        if (change.type === 'position' && change.position) {
            const node = store.nodes.find(n => n.id === change.id);
            if (node) {
                node.position = change.position;
                store.isDirty = true;
            }
        }
    });
};

// Track edge deletions for dirty flag
const prevEdgeCount = ref(store.edges.length);
watch(() => store.edges.length, (newLen) => {
    if (newLen < prevEdgeCount.value) {
        store.isDirty = true;
    }
    prevEdgeCount.value = newLen;
});

defineExpose({ vueFlowRef, zoomIn, zoomOut, fitView });
</script>

<template>
    <div
        class="w-full h-full"
        @dragover="onDragOver"
        @drop="handleDrop"
    >
        <VueFlow
            ref="vueFlowRef"
            v-model:nodes="store.nodes"
            v-model:edges="store.edges"
            :node-types="nodeTypes"
            :is-valid-connection="isValidConnection"
            :default-viewport="{ zoom: 1, x: 0, y: 0 }"
            :default-edge-options="defaultEdgeOptions"
            :pan-on-drag="interactionMode === 'pan'"
            :snap-to-grid="true"
            :snap-grid="[16, 16]"
            :min-zoom="0.2"
            :max-zoom="2"
            fit-view-on-init
            :delete-key-code="['Delete', 'Backspace']"
            @connect="handleConnect"
            @node-click="onNodeClick"
            @pane-click="onPaneClick"
            @nodes-change="onNodesChange"
            @viewport-change="onViewportChange"
            class="pipeline-flow"
        >
            <Background variant="dots" :gap="20" :size="1" color="#d1d5db" />
        </VueFlow>
    </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
.pipeline-flow {
    background-color: #ffffff;
}

/* Edges */
.pipeline-flow .vue-flow__edge-path {
    stroke: #94a3b8;
    stroke-width: 1.5;
    cursor: pointer;
}

.pipeline-flow .vue-flow__edge.selected .vue-flow__edge-path {
    stroke: #ef4444;
    stroke-width: 3;
}

/* Connection line */
.pipeline-flow .vue-flow__connection-line {
    stroke: #94a3b8;
    stroke-width: 1.5;
    stroke-dasharray: 5;
}

/* Input handles (left) — invisible, node labels serve as visual indicator */
.pipeline-flow .vue-flow__handle {
    width: 8px;
    height: 8px;
    border: none;
    background-color: transparent;
    transition: all 0.15s ease;
}

.pipeline-flow .vue-flow__handle:hover {
    border: 1.5px solid #3b82f6;
    background-color: #3b82f6;
    width: 10px;
    height: 10px;
}

.pipeline-flow .vue-flow__handle.connecting {
    background-color: #3b82f6;
    border: 1.5px solid #3b82f6;
}

/* Output handles (right) — subtle gray dot */
.pipeline-flow .vue-flow__handle.vue-flow__handle-right {
    width: 6px;
    height: 6px;
    background-color: #d1d5db;
    border: none;
    border-radius: 50%;
}

.pipeline-flow .vue-flow__handle.vue-flow__handle-right:hover {
    background-color: #3b82f6;
    border: none;
    width: 8px;
    height: 8px;
}

/* Selection box */
.pipeline-flow .vue-flow__selection {
    background: rgba(59, 130, 246, 0.05);
    border: 1px solid rgba(59, 130, 246, 0.3);
}

/* Pane */
.pipeline-flow .vue-flow__pane {
    cursor: grab;
}

.pipeline-flow .vue-flow__pane:active {
    cursor: grabbing;
}

/* Generating animation — pulsing blue border glow */
@keyframes border-pulse {
    0%, 100% {
        border-color: #3b82f6;
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.3), 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    50% {
        border-color: #93c5fd;
        box-shadow: 0 0 16px 4px rgba(59, 130, 246, 0.2), 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
}

.animate-border-pulse {
    animation: border-pulse 1.5s ease-in-out infinite;
}
</style>
