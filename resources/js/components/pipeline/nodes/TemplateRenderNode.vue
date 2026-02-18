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

const connectedInputs = computed(() => {
    const inputs = [];
    store.edges.forEach(edge => {
        if (edge.target === props.id) {
            inputs.push(edge.targetHandle || 'unknown');
        }
    });
    return inputs;
});
</script>

<template>
    <div class="relative">
    <!-- Input handles with external labels -->
    <Handle type="target" :position="Position.Left" id="template" style="top: 25%" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 25%; right: calc(100% + 4px); transform: translateY(-50%)">
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.template') }}</span>
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Z" />
        </svg>
    </div>

    <Handle type="target" :position="Position.Left" id="image" style="top: 42%" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 42%; right: calc(100% + 4px); transform: translateY(-50%)">
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.image') }}</span>
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
        </svg>
    </div>

    <Handle type="target" :position="Position.Left" id="text" style="top: 59%" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 59%; right: calc(100% + 4px); transform: translateY(-50%)">
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.text') }}</span>
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
        </svg>
    </div>

    <Handle type="target" :position="Position.Left" id="analysis" style="top: 76%" />
    <div class="absolute flex items-center gap-1 pointer-events-none whitespace-nowrap" style="top: 76%; right: calc(100% + 4px); transform: translateY(-50%)">
        <span class="text-[9px] text-gray-500 font-medium">{{ t('pipeline.handleLabels.analysis') }}</span>
        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
        </svg>
    </div>

    <BaseNode
        :id="id"
        :data="data"
        node-type="template_render"
        accent-dot="bg-indigo-500"
        icon-path="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42"
    >
        <!-- Rendered output preview -->
        <ImagePreviewOverlay v-if="previewImage" :src="previewImage" :alt="t('pipeline.nodeTypes.template_render')" />

        <!-- Input indicators -->
        <div v-else class="space-y-2">
            <div class="rounded-lg bg-indigo-50 border border-indigo-100/50 p-3 flex items-center justify-center">
                <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" />
                </svg>
            </div>
            <div class="grid grid-cols-2 gap-1.5">
                <div
                    v-for="handle in ['template', 'image', 'text', 'analysis']"
                    :key="handle"
                    :class="[
                        'flex items-center gap-1 px-1.5 py-1 rounded text-[10px]',
                        connectedInputs.includes(handle) ? 'bg-green-50 text-green-600' : 'bg-gray-50 text-gray-400',
                    ]"
                >
                    <span
                        :class="[
                            'w-1.5 h-1.5 rounded-full',
                            connectedInputs.includes(handle) ? 'bg-green-400' : 'bg-gray-300',
                        ]"
                    />
                    {{ handle }}
                </div>
            </div>
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
