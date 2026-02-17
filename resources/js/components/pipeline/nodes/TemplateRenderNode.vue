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
    <!-- Input handles -->
    <Handle type="target" :position="Position.Left" id="template" style="top: 25%" />
    <Handle type="target" :position="Position.Left" id="image" style="top: 42%" />
    <Handle type="target" :position="Position.Left" id="text" style="top: 59%" />
    <Handle type="target" :position="Position.Left" id="analysis" style="top: 76%" />

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

    <!-- Output handle -->
    <Handle type="source" :position="Position.Right" id="image" />
    </div>
</template>
