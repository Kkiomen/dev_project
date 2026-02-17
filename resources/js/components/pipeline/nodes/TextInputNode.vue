<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import BaseNode from './BaseNode.vue';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const config = computed(() => props.data?.config || {});
const text = computed(() => config.value.text || '');
const hasText = computed(() => text.value.length > 0);
const textPreview = computed(() => {
    if (text.value.length > 120) return text.value.substring(0, 120) + '...';
    return text.value;
});

const parameters = computed(() => config.value.parameters || {});
const hasParameters = computed(() => Object.keys(parameters.value).length > 0);
const parameterEntries = computed(() => Object.entries(parameters.value));
</script>

<template>
    <div class="relative">
    <BaseNode
        :id="id"
        :data="data"
        node-type="text_input"
        accent-dot="bg-green-500"
        icon-path="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
    >
        <!-- Parameters display -->
        <div v-if="hasParameters" class="space-y-1.5">
            <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">{{ t('pipeline.config.parameters') }}</span>
            <div class="space-y-1">
                <div
                    v-for="[key, value] in parameterEntries"
                    :key="key"
                    class="flex items-baseline gap-1.5 text-[11px]"
                >
                    <span class="font-medium text-gray-600 shrink-0">{{ key }}:</span>
                    <span class="text-gray-500 truncate">{{ value }}</span>
                </div>
            </div>
        </div>

        <!-- Text preview -->
        <div v-else-if="hasText">
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap break-words">{{ textPreview }}</p>
        </div>

        <!-- Empty state -->
        <div v-else class="rounded-lg border-2 border-dashed border-gray-200 flex flex-col items-center justify-center gap-1.5 py-5 bg-gray-50/50">
            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
            <span class="text-[10px] text-gray-400">{{ t('pipeline.config.textPlaceholder') }}</span>
        </div>
    </BaseNode>

    <!-- Output handle -->
    <Handle type="source" :position="Position.Right" id="text" />
    </div>
</template>
