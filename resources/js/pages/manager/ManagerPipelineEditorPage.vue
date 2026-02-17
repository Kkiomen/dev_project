<script setup>
import { onMounted, onUnmounted, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';
import { useBrandsStore } from '@/stores/brands';
import { usePipelineEditor } from '@/composables/usePipelineEditor';
import { useToast } from '@/composables/useToast';
import PipelineCanvas from '@/components/pipeline/PipelineCanvas.vue';
import PipelineNodeLibrary from '@/components/pipeline/PipelineNodeLibrary.vue';
import PipelineToolbar from '@/components/pipeline/PipelineToolbar.vue';
import PipelineNodeConfig from '@/components/pipeline/PipelineNodeConfig.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const props = defineProps({
    pipelineId: { type: String, required: true },
});

const { t } = useI18n();
const store = usePipelinesStore();
const brandsStore = useBrandsStore();
const toast = useToast();

const pipelineIdRef = toRef(props, 'pipelineId');
const {
    onDragOver,
    onDrop,
    isValidConnection,
    onNodeClick,
    onPaneClick,
    lastSavedAt,
} = usePipelineEditor(pipelineIdRef);

const canvasRef = ref(null);
const zoomLevel = ref(100);
const interactionMode = ref('select');

const onModeChange = (mode) => {
    interactionMode.value = mode;
};

const onViewportChange = (viewport) => {
    zoomLevel.value = Math.round(viewport.zoom * 100);
};

let loadingInProgress = false;
const loadPipeline = async () => {
    if (!store.currentBrandId || loadingInProgress) return;
    loadingInProgress = true;
    try {
        await store.fetchNodeTypes();
        await store.fetchPipeline(props.pipelineId);
    } finally {
        loadingInProgress = false;
    }
};

onMounted(() => {
    loadPipeline();
});

// Retry when brand becomes available (on F5 refresh, brand loads after page mounts)
watch(() => brandsStore.currentBrand, (brand) => {
    if (brand && !store.currentPipeline && !store.currentPipelineLoading) {
        loadPipeline();
    }
});

onUnmounted(() => {
    store.resetEditor();
});

const handleExecute = async () => {
    try {
        if (store.isDirty) {
            await store.saveCanvas(props.pipelineId);
        }
        const run = await store.executePipeline(props.pipelineId);
        toast.success(t('pipeline.toast.executionStarted'));

        if (run && !run.is_processing) return;
        pollRunStatus(run.id);
    } catch (error) {
        toast.error(error?.response?.data?.message || t('pipeline.errors.executeFailed'));
    }
};

let pollInterval = null;
const pollRunStatus = (runId) => {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(async () => {
        try {
            const run = await store.fetchRunStatus(props.pipelineId, runId);
            if (run && !run.is_processing) {
                clearInterval(pollInterval);
                pollInterval = null;
                if (run.status === 'completed') {
                    toast.success(t('pipeline.toast.executionCompleted'));
                    store.populatePreviewsFromRun(run);
                } else if (run.status === 'failed') {
                    toast.error(t('pipeline.toast.executionFailed'));
                }
            }
        } catch {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }, 2000);
};

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="h-full flex flex-col bg-gray-50">
        <!-- Loading -->
        <div v-if="store.currentPipelineLoading" class="flex-1 flex items-center justify-center">
            <LoadingSpinner />
        </div>

        <template v-else-if="store.currentPipeline">
            <!-- Toolbar -->
            <PipelineToolbar
                :pipeline-id="pipelineId"
                :last-saved-at="lastSavedAt"
                @execute="handleExecute"
            />

            <!-- Full-screen canvas with floating overlays -->
            <div class="flex-1 min-h-0 relative">
                <!-- Canvas (full area) -->
                <PipelineCanvas
                    ref="canvasRef"
                    :on-drag-over="onDragOver"
                    :on-drop="onDrop"
                    :is-valid-connection="isValidConnection"
                    :on-node-click="onNodeClick"
                    :on-pane-click="onPaneClick"
                    :interaction-mode="interactionMode"
                    @viewport-change="onViewportChange"
                />

                <!-- Floating Node Library (left edge) -->
                <PipelineNodeLibrary
                    :interaction-mode="interactionMode"
                    @mode-change="onModeChange"
                />

                <!-- Floating Node Config (right side) -->
                <PipelineNodeConfig />

                <!-- Full-width bottom bar -->
                <div class="absolute bottom-0 left-0 right-0 h-9 z-10 flex items-center justify-between px-4 bg-white border-t border-gray-200">
                    <!-- Left: pipeline icon -->
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>
                    <!-- Right: zoom controls -->
                    <div class="flex items-center gap-1">
                        <!-- Zoom out -->
                        <button
                            @click="canvasRef?.zoomOut()"
                            class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
                            :title="t('pipeline.toolbar.zoomOut')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                            </svg>
                        </button>
                        <!-- Zoom level -->
                        <span class="text-[11px] text-gray-500 font-medium w-10 text-center select-none">{{ zoomLevel }}%</span>
                        <!-- Zoom in -->
                        <button
                            @click="canvasRef?.zoomIn()"
                            class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
                            :title="t('pipeline.toolbar.zoomIn')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                        <!-- Fit view -->
                        <button
                            @click="canvasRef?.fitView()"
                            class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition ml-1"
                            :title="t('pipeline.toolbar.fitToScreen')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
