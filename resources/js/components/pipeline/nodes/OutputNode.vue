<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';
import ImagePreviewOverlay from './ImagePreviewOverlay.vue';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const store = usePipelinesStore();

const isSelected = computed(() => store.selectedNodeId === props.id);
const label = computed(() => props.data?.label || t('pipeline.nodeTypes.output'));

const currentRun = computed(() => store.currentRun);
const outputImageUrl = computed(() => {
    if (currentRun.value?.output_url) return currentRun.value.output_url;
    const imagePath = currentRun.value?.output_data?.image;
    if (imagePath) return `/storage/${imagePath}`;
    return store.nodePreviewData?.[props.id] || null;
});
const isProcessing = computed(() => currentRun.value?.is_processing);
const isCompleted = computed(() => currentRun.value?.status === 'completed');
const dimensions = computed(() => {
    const w = currentRun.value?.output_data?.width;
    const h = currentRun.value?.output_data?.height;
    if (w && h) return `${w} x ${h}`;
    return null;
});

const downloadOutput = () => {
    if (!outputImageUrl.value) return;
    const link = document.createElement('a');
    link.href = outputImageUrl.value;
    link.download = `pipeline-output-${Date.now()}.png`;
    link.click();
};

const copyOutput = async () => {
    if (!outputImageUrl.value) return;
    try {
        const response = await fetch(outputImageUrl.value);
        const blob = await response.blob();
        await navigator.clipboard.write([new ClipboardItem({ [blob.type]: blob })]);
    } catch {
        await navigator.clipboard.writeText(outputImageUrl.value);
    }
};

const openExternal = () => {
    if (!outputImageUrl.value) return;
    window.open(outputImageUrl.value, '_blank');
};

const shareOutput = () => {
    if (!outputImageUrl.value) return;
    if (navigator.share) {
        navigator.share({ url: outputImageUrl.value });
    } else {
        navigator.clipboard.writeText(outputImageUrl.value);
    }
};

const addToGallery = () => {
    // Placeholder — emit or store action
};

const useAsInput = () => {
    // Placeholder — emit or store action
};
</script>

<template>
    <div class="relative">
    <!-- Input handles -->
    <Handle type="target" :position="Position.Left" id="image" style="top: 40%" />
    <Handle type="target" :position="Position.Left" id="text" style="top: 65%" />

    <div
        :class="[
            'relative min-w-[300px] max-w-[400px] rounded-xl bg-white transition-all duration-150',
            isSelected
                ? 'border border-indigo-500 shadow-lg ring-2 ring-indigo-500/20'
                : 'border border-transparent shadow-md hover:shadow-lg',
        ]"
    >
        <!-- Header -->
        <div class="flex items-center gap-2 px-3 py-2">
            <span class="w-2 h-2 rounded-full shrink-0 bg-gray-900" />
            <span class="text-xs font-medium text-gray-700 truncate flex-1">{{ label }}</span>
        </div>

        <!-- Dimensions row -->
        <div v-if="dimensions" class="px-3 pb-1">
            <span class="text-[10px] text-gray-400 font-mono">{{ dimensions }}</span>
        </div>

        <!-- Utility toolbar -->
        <div class="flex items-center gap-0.5 px-2 py-1 bg-gray-50 border-y border-gray-100">
            <button
                class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                :title="t('pipeline.output.previousResult')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>
            <button
                class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                :title="t('pipeline.output.nextResult')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
            <div class="flex-1" />
            <button
                class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                :title="t('pipeline.output.expand')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
            </button>
            <button
                class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                :title="t('pipeline.output.grid')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" />
                </svg>
            </button>
        </div>

        <!-- Content -->
        <div class="px-3 py-2.5">
            <!-- Processing spinner -->
            <div v-if="isProcessing" class="aspect-square rounded-lg bg-gray-50 flex flex-col items-center justify-center gap-2">
                <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                <span class="text-[10px] text-gray-400">{{ t('pipeline.executing') }}</span>
            </div>

            <!-- Output image preview -->
            <ImagePreviewOverlay v-else-if="outputImageUrl" :src="outputImageUrl" :alt="t('pipeline.preview.outputImage')" aspect-class="aspect-square" object-fit="object-contain" />

            <!-- Empty state -->
            <div v-else class="aspect-square rounded-lg border-2 border-dashed border-gray-200 flex flex-col items-center justify-center gap-2 bg-gray-50/50">
                <svg class="w-8 h-8 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                </svg>
                <span class="text-[10px] text-gray-400">{{ t('pipeline.preview.noPreview') }}</span>
            </div>
        </div>

        <!-- Action toolbar with 6 icon buttons -->
        <div v-if="outputImageUrl" class="flex items-center gap-1.5 px-3 py-2">
            <!-- Download -->
            <button
                @click.stop="downloadOutput"
                class="w-7 h-7 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition"
                :title="t('pipeline.preview.download')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
            </button>

            <!-- Copy -->
            <button
                @click.stop="copyOutput"
                class="w-7 h-7 flex items-center justify-center rounded-lg bg-green-50 text-green-600 hover:bg-green-100 transition"
                :title="t('pipeline.output.copy')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                </svg>
            </button>

            <!-- Open external -->
            <button
                @click.stop="openExternal"
                class="w-7 h-7 flex items-center justify-center rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-100 transition"
                :title="t('pipeline.output.openExternal')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </button>

            <!-- Share -->
            <button
                @click.stop="shareOutput"
                class="w-7 h-7 flex items-center justify-center rounded-lg bg-purple-50 text-purple-600 hover:bg-purple-100 transition"
                :title="t('pipeline.output.share')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                </svg>
            </button>

            <!-- Add to Gallery -->
            <button
                @click.stop="addToGallery"
                class="w-7 h-7 flex items-center justify-center rounded-lg bg-teal-50 text-teal-600 hover:bg-teal-100 transition"
                :title="t('pipeline.output.addToGallery')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21zM12 9h.008v.008H12V9z" />
                </svg>
            </button>

            <!-- Use as Input -->
            <button
                @click.stop="useAsInput"
                class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                :title="t('pipeline.output.useAsInput')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
            </button>
        </div>

        <!-- Completed status indicator -->
        <div
            v-if="isCompleted && outputImageUrl"
            class="absolute bottom-2.5 right-2.5 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center shadow-sm"
        >
            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>
    </div>
    </div>
</template>
