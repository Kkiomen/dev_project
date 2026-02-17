import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { usePipelinesStore } from '@/stores/pipelines';

export function usePipelineEditor(pipelineId) {
    const store = usePipelinesStore();
    const autoSaveTimer = ref(null);
    const lastSavedAt = ref(null);

    const onDragOver = (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    };

    const onDrop = (event, vueFlowInstance) => {
        event.preventDefault();
        const nodeType = event.dataTransfer.getData('application/pipeline-node-type');
        const nodeLabel = event.dataTransfer.getData('application/pipeline-node-label');
        if (!nodeType || !vueFlowInstance) return;

        const position = vueFlowInstance.screenToFlowCoordinate({
            x: event.clientX,
            y: event.clientY,
        });

        const nodeId = `${nodeType}_${Date.now()}`;
        const newNode = {
            id: nodeId,
            type: nodeType,
            position,
            data: {
                label: nodeLabel || nodeType,
                config: {},
            },
        };

        store.addNode(newNode);
        nextTick(() => store.setSelectedNode(nodeId));
    };

    // onConnect is now handled directly in PipelineCanvas via useVueFlow().addEdges

    const isValidConnection = (connection) => {
        // No self-loops
        if (connection.source === connection.target) return false;

        // No duplicate edges (same source+target pair)
        const exists = store.edges.some(
            e => e.source === connection.source &&
                 e.target === connection.target &&
                 e.sourceHandle === connection.sourceHandle &&
                 e.targetHandle === connection.targetHandle
        );
        if (exists) return false;

        // Nodes must exist
        const sourceNode = store.nodes.find(n => n.id === connection.source);
        const targetNode = store.nodes.find(n => n.id === connection.target);
        if (!sourceNode || !targetNode) return false;

        // Target must accept inputs (source nodes like image_input/text_input have no inputs)
        const targetInputs = getNodeInputHandles(targetNode.type);
        if (targetInputs.length === 0) return false;

        // Type compatibility: source output type must match one of target's accepted inputs
        const sourceOutputType = getDefaultOutputHandle(sourceNode.type);
        if (sourceOutputType && !targetInputs.includes(sourceOutputType)) return false;

        return true;
    };

    // All input handles a node type accepts
    const getNodeInputHandles = (nodeType) => {
        const inputs = {
            ai_image_generator: ['text', 'image', 'template'],
            image_analysis: ['image'],
            template_render: ['template', 'image', 'text', 'analysis'],
            output: ['image', 'text'],
        };
        return inputs[nodeType] || [];
    };

    const getDefaultOutputHandle = (nodeType) => {
        const defaults = {
            image_input: 'image',
            text_input: 'text',
            template: 'template',
            ai_image_generator: 'image',
            image_analysis: 'analysis',
            template_render: 'image',
        };
        return defaults[nodeType] || null;
    };

    const onNodeClick = ({ node }) => {
        store.setSelectedNode(node.id);
    };

    const onPaneClick = () => {
        store.setSelectedNode(null);
    };

    const onEdgeRemove = (edges) => {
        edges.forEach(edge => store.removeEdge(edge.id));
    };

    const onNodeRemove = (nodes) => {
        nodes.forEach(node => store.removeNode(node.id));
    };

    // Auto-save
    const startAutoSave = () => {
        autoSaveTimer.value = setInterval(async () => {
            if (store.isDirty && pipelineId.value) {
                try {
                    await store.saveCanvas(pipelineId.value);
                    lastSavedAt.value = new Date();
                } catch {
                    // Silent fail for auto-save
                }
            }
        }, 30000);
    };

    const stopAutoSave = () => {
        if (autoSaveTimer.value) {
            clearInterval(autoSaveTimer.value);
            autoSaveTimer.value = null;
        }
    };

    // Keyboard shortcuts
    const handleKeydown = (event) => {
        if ((event.key === 'Delete' || event.key === 'Backspace') && store.selectedNodeId) {
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') return;
            store.removeNode(store.selectedNodeId);
        }

        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            if (pipelineId.value) {
                store.saveCanvas(pipelineId.value);
            }
        }

        // Escape to deselect
        if (event.key === 'Escape' && store.selectedNodeId) {
            store.setSelectedNode(null);
        }
    };

    onMounted(() => {
        startAutoSave();
        document.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        stopAutoSave();
        document.removeEventListener('keydown', handleKeydown);
    });

    return {
        onDragOver,
        onDrop,
        isValidConnection,
        onNodeClick,
        onPaneClick,
        onEdgeRemove,
        onNodeRemove,
        lastSavedAt,
    };
}
