<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import BaseNode from './BaseNode.vue';
import ImagePreviewOverlay from './ImagePreviewOverlay.vue';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const config = computed(() => props.data?.config || {});
const templateName = computed(() => config.value.template_name || '');
const thumbnailUrl = computed(() => config.value.template_thumbnail_url || '');
const hasTemplate = computed(() => !!config.value.template_id);
</script>

<template>
    <div class="relative">
    <BaseNode
        :id="id"
        :data="data"
        node-type="template"
        accent-dot="bg-purple-500"
        icon-path="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z"
    >
        <!-- Template preview -->
        <div v-if="hasTemplate" class="space-y-1.5">
            <ImagePreviewOverlay v-if="thumbnailUrl" :src="thumbnailUrl" :alt="templateName" />
            <div v-else class="aspect-[4/3] rounded-lg overflow-hidden bg-gray-50">
                <div class="w-full h-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 font-medium truncate">{{ templateName }}</p>
        </div>

        <!-- Empty state -->
        <div v-else class="aspect-[4/3] rounded-lg border-2 border-dashed border-gray-200 flex flex-col items-center justify-center gap-1.5 bg-gray-50/50">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
            </svg>
            <span class="text-[10px] text-gray-400">{{ t('pipeline.config.selectTemplate') }}</span>
        </div>
    </BaseNode>

    <!-- Output handle -->
    <Handle type="source" :position="Position.Right" id="template" />
    </div>
</template>
