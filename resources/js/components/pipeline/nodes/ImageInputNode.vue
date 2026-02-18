<script setup>
import { ref, computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import BaseNode from './BaseNode.vue';
import ImagePreviewOverlay from './ImagePreviewOverlay.vue';
import { usePipelinesStore } from '@/stores/pipelines';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const store = usePipelinesStore();
const toast = useToast();

const fileInputRef = ref(null);
const isUploading = ref(false);

const config = computed(() => props.data?.config || {});
const imageUrl = computed(() => {
    if (config.value.image_url) return config.value.image_url;
    if (config.value.image_path) return `/storage/${config.value.image_path}`;
    return null;
});
const hasImage = computed(() => !!imageUrl.value);

const triggerUpload = () => {
    fileInputRef.value?.click();
};

const handleFileChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const pipelineId = store.currentPipeline?.id;
    if (!pipelineId) return;

    isUploading.value = true;
    try {
        const result = await store.uploadNodeImage(pipelineId, file);
        store.updateNodeData(props.id, {
            config: {
                ...config.value,
                image_path: result.image_path,
                image_url: result.image_url,
                source: 'upload',
            },
        });
        // Auto-save so image survives F5 refresh
        await store.saveCanvas(pipelineId);
        toast.success(t('pipeline.imageInput.uploaded'));
    } catch (error) {
        toast.error(t('pipeline.imageInput.uploadError'));
    } finally {
        isUploading.value = false;
        if (fileInputRef.value) fileInputRef.value.value = '';
    }
};

const removeImage = () => {
    store.updateNodeData(props.id, {
        config: {
            ...config.value,
            image_path: null,
            image_url: null,
        },
    });
};

const handleToolbarAction = (action) => {
    if (action === 'edit') {
        triggerUpload();
    } else if (action === 'delete') {
        store.removeNode(props.id);
    }
};
</script>

<template>
    <div class="relative">
    <BaseNode
        :id="id"
        :data="data"
        node-type="image_input"
        accent-dot="bg-blue-500"
        icon-path="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"
        @toolbar-action="handleToolbarAction"
    >
        <!-- Hidden file input -->
        <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileChange"
        />

        <!-- Image preview -->
        <div v-if="hasImage" class="space-y-1.5 relative">
            <ImagePreviewOverlay :src="imageUrl" :alt="t('pipeline.nodeTypes.image_input')" />
            <button
                @click.stop="removeImage"
                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 flex items-center justify-center text-white hover:bg-red-500 transition"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Upload placeholder -->
        <div
            v-else
            @click.stop="triggerUpload"
            class="aspect-[4/3] rounded-lg border-2 border-dashed border-gray-200 flex flex-col items-center justify-center gap-1.5 bg-gray-50/50 cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-colors"
        >
            <!-- Uploading spinner -->
            <template v-if="isUploading">
                <svg class="w-6 h-6 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span class="text-[10px] text-blue-400 font-medium">{{ t('pipeline.imageInput.uploading') }}</span>
            </template>

            <!-- Upload icon -->
            <template v-else>
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                <span class="text-[10px] text-gray-400">{{ t('pipeline.imageInput.clickToUpload') }}</span>
            </template>
        </div>

    </BaseNode>

    <!-- Output handle with external label -->
    <Handle type="source" :position="Position.Right" id="image" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 50%; left: calc(100% + 4px); transform: translateY(-50%)">
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
        </svg>
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.image') }}</span>
    </div>
    </div>
</template>
