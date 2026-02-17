<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';

const { t } = useI18n();
const store = usePipelinesStore();

const currentRun = computed(() => store.currentRun);
const hasOutput = computed(() => currentRun.value?.output_url || currentRun.value?.output_data?.image);

const outputImageUrl = computed(() => {
    if (currentRun.value?.output_url) return currentRun.value.output_url;
    const imagePath = currentRun.value?.output_data?.image;
    if (imagePath) return `/storage/${imagePath}`;
    return null;
});

const downloadOutput = () => {
    if (!outputImageUrl.value) return;
    const link = document.createElement('a');
    link.href = outputImageUrl.value;
    link.download = `pipeline-output-${Date.now()}.png`;
    link.click();
};
</script>

<template>
    <div class="p-4 space-y-4">
        <!-- Current Run -->
        <div v-if="currentRun" class="space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ t('pipeline.preview.runResult') }}
                </h4>
            </div>

            <!-- Output image -->
            <div v-if="hasOutput" class="space-y-2">
                <div class="aspect-square bg-gray-50 rounded-lg overflow-hidden border border-gray-200">
                    <img
                        :src="outputImageUrl"
                        :alt="t('pipeline.preview.outputImage')"
                        class="w-full h-full object-contain"
                    />
                </div>
                <button
                    @click="downloadOutput"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-500 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ t('pipeline.preview.download') }}
                </button>
            </div>

            <!-- Processing indicator -->
            <div v-else-if="currentRun.is_processing" class="flex flex-col items-center gap-3 py-8">
                <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                <span class="text-xs text-gray-400">{{ t('pipeline.executing') }}</span>
            </div>

            <!-- Error -->
            <div v-else-if="currentRun.status === 'failed'" class="p-3 bg-red-50 border border-red-100 rounded-lg">
                <p class="text-xs text-red-500">{{ currentRun.error_message }}</p>
            </div>
        </div>

        <!-- No preview -->
        <div v-else class="text-center py-8">
            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
            </svg>
            <p class="mt-2 text-xs text-gray-400">{{ t('pipeline.preview.noPreview') }}</p>
        </div>
    </div>
</template>
