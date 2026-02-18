<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import BaseNode from './BaseNode.vue';
import ImagePreviewOverlay from './ImagePreviewOverlay.vue';
import { usePipelinesStore } from '@/stores/pipelines';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const store = usePipelinesStore();

const previewImage = computed(() => store.nodePreviewData?.[props.id] || null);
</script>

<template>
    <div class="relative">
    <!-- Input handle with external label -->
    <Handle type="target" :position="Position.Left" id="image" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 50%; right: calc(100% + 4px); transform: translateY(-50%)">
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.image') }}</span>
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
        </svg>
    </div>

    <BaseNode
        :id="id"
        :data="data"
        node-type="image_analysis"
        accent-dot="bg-orange-500"
        icon-path="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z"
    >
        <!-- Image preview from last run -->
        <ImagePreviewOverlay v-if="previewImage" :src="previewImage" :alt="t('pipeline.nodeTypes.image_analysis')" />

        <!-- Input/output indicators -->
        <div v-else class="space-y-2">
            <div class="rounded-lg bg-orange-50 border border-orange-100/50 p-3 flex items-center justify-center">
                <svg class="w-8 h-8 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div class="flex items-center justify-between text-[10px] text-gray-400 px-0.5">
                <span class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400" />
                    {{ t('pipeline.nodeTypes.image_input') }}
                </span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
                <span class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400" />
                    {{ t('pipeline.nodeDescriptions.image_analysis') }}
                </span>
            </div>
        </div>
    </BaseNode>

    <!-- Output handles with external labels -->
    <Handle type="source" :position="Position.Right" id="analysis" style="top: 40%" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 40%; left: calc(100% + 4px); transform: translateY(-50%)">
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
        </svg>
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.analysis') }}</span>
    </div>

    <Handle type="source" :position="Position.Right" id="image" style="top: 65%" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 65%; left: calc(100% + 4px); transform: translateY(-50%)">
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
        </svg>
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.image') }}</span>
    </div>
    </div>
</template>
