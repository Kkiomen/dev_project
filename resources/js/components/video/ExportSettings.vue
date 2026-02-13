<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    project: { type: Object, required: true },
    rendering: { type: Boolean, default: false },
});

const emit = defineEmits(['render', 'download']);

const { t } = useI18n();

const canRender = computed(() => {
    return props.project.can_export && !props.rendering && !props.project.is_processing;
});

const hasOutput = computed(() => {
    return props.project.status === 'completed' && props.project.output_path;
});

const isRendering = computed(() => {
    return props.project.status === 'rendering' || props.rendering;
});
</script>

<template>
    <div class="p-4 space-y-6">
        <!-- Project Info -->
        <div class="bg-gray-50 rounded-xl p-4 space-y-2">
            <h3 class="text-sm font-medium text-gray-700">{{ t('videoEditor.export.projectInfo') }}</h3>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <span class="text-gray-500">{{ t('videoEditor.export.resolution') }}:</span>
                    <span class="ml-1 text-gray-900" v-if="project.width">{{ project.width }}x{{ project.height }}</span>
                    <span class="ml-1 text-gray-400" v-else>--</span>
                </div>
                <div>
                    <span class="text-gray-500">{{ t('videoEditor.export.duration') }}:</span>
                    <span class="ml-1 text-gray-900" v-if="project.duration">
                        {{ Math.floor(project.duration / 60) }}:{{ String(Math.floor(project.duration % 60)).padStart(2, '0') }}
                    </span>
                    <span class="ml-1 text-gray-400" v-else>--</span>
                </div>
                <div>
                    <span class="text-gray-500">{{ t('videoEditor.export.language') }}:</span>
                    <span class="ml-1 text-gray-900">{{ project.language || '--' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">{{ t('videoEditor.export.captionStyle') }}:</span>
                    <span class="ml-1 text-gray-900 capitalize">{{ project.caption_style }}</span>
                </div>
            </div>
        </div>

        <!-- Render Button -->
        <div class="space-y-3">
            <button
                @click="$emit('render')"
                :disabled="!canRender"
                class="w-full px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 font-medium"
            >
                <div v-if="isRendering" class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8.132v3.736a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664l-3.197-2.736z" clip-rule="evenodd" />
                </svg>
                {{ isRendering ? t('videoEditor.export.rendering') : t('videoEditor.export.renderVideo') }}
            </button>

            <p v-if="!project.has_transcription" class="text-xs text-amber-600 text-center">
                {{ t('videoEditor.export.waitForTranscription') }}
            </p>
        </div>

        <!-- Download -->
        <div v-if="hasOutput" class="border-t border-gray-200 pt-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">{{ t('videoEditor.export.readyTitle') }}</h3>
            <button
                @click="$emit('download')"
                class="w-full px-4 py-3 border-2 border-green-500 text-green-700 rounded-xl hover:bg-green-50 transition-colors flex items-center justify-center gap-2 font-medium"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                {{ t('videoEditor.export.download') }}
            </button>
        </div>

        <!-- Error State -->
        <div v-if="project.status === 'failed' && project.error_message" class="bg-red-50 border border-red-200 rounded-xl p-4">
            <h4 class="text-sm font-medium text-red-700 mb-1">{{ t('videoEditor.export.errorTitle') }}</h4>
            <p class="text-xs text-red-600">{{ project.error_message }}</p>
        </div>
    </div>
</template>
