<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CaptionStylePicker from '@/components/video/CaptionStylePicker.vue';
import TranscriptEditor from '@/components/video/TranscriptEditor.vue';

const props = defineProps({
    project: { type: Object, default: null },
    selectedClip: { type: Object, default: null },
    activeTab: { type: String, default: 'properties' },
    captionStyle: { type: String, default: 'clean' },
    captionStyles: { type: Array, default: () => [] },
    segments: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    rendering: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:activeTab',
    'update:caption-style',
    'update:caption-settings',
    'save-transcript',
    'render',
    'download',
]);

const { t } = useI18n();

const tabs = computed(() => [
    { key: 'properties', label: t('videoEditor.inspector.properties') },
    { key: 'captions', label: t('videoEditor.inspector.captions') },
    { key: 'transcript', label: t('videoEditor.inspector.transcript') },
    { key: 'export', label: t('videoEditor.inspector.export') },
]);

function formatDuration(seconds) {
    if (!seconds) return '--';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div class="flex flex-col h-full bg-gray-900 border-l border-gray-800">
        <!-- Tab bar -->
        <div class="flex border-b border-gray-800 shrink-0 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="emit('update:activeTab', tab.key)"
                class="px-3 py-2 text-[11px] font-medium whitespace-nowrap transition-colors border-b-2"
                :class="activeTab === tab.key
                    ? 'text-violet-400 border-violet-500'
                    : 'text-gray-500 border-transparent hover:text-gray-300'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 min-h-0 overflow-y-auto p-3">
            <!-- Properties tab -->
            <div v-if="activeTab === 'properties'" class="space-y-3">
                <template v-if="selectedClip">
                    <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wider">
                        {{ t('videoEditor.inspector.clipProperties') }}
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.type') }}</span>
                            <span class="text-gray-300 capitalize">{{ selectedClip.type }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.timelineStart') }}</span>
                            <span class="text-gray-300 font-mono">{{ selectedClip.timelineStart.toFixed(2) }}s</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.sourceRange') }}</span>
                            <span class="text-gray-300 font-mono">{{ selectedClip.sourceIn.toFixed(2) }}s - {{ selectedClip.sourceOut.toFixed(2) }}s</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.duration') }}</span>
                            <span class="text-gray-300 font-mono">{{ (selectedClip.sourceOut - selectedClip.sourceIn).toFixed(2) }}s</span>
                        </div>
                    </div>

                    <div v-if="selectedClip.text" class="mt-3">
                        <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">
                            {{ t('videoEditor.inspector.text') }}
                        </h4>
                        <p class="text-xs text-gray-300 bg-gray-800 rounded p-2">{{ selectedClip.text }}</p>
                    </div>
                </template>

                <template v-else>
                    <div class="text-center py-8">
                        <p class="text-xs text-gray-600">{{ t('videoEditor.inspector.noSelection') }}</p>
                    </div>
                </template>

                <!-- Project info -->
                <div v-if="project" class="mt-4 pt-3 border-t border-gray-800">
                    <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('videoEditor.inspector.projectInfo') }}
                    </h4>
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.resolution') }}</span>
                            <span class="text-gray-300">{{ project.width }}x{{ project.height }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.duration') }}</span>
                            <span class="text-gray-300">{{ formatDuration(project.duration) }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">{{ t('videoEditor.inspector.language') }}</span>
                            <span class="text-gray-300">{{ project.language || 'auto' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Captions tab -->
            <div v-else-if="activeTab === 'captions'">
                <CaptionStylePicker
                    :current-style="captionStyle"
                    :caption-settings="project?.caption_settings || {}"
                    :styles="captionStyles"
                    @update:style="(s) => emit('update:caption-style', s)"
                    @update:settings="(s) => emit('update:caption-settings', s)"
                />
            </div>

            <!-- Transcript tab -->
            <div v-else-if="activeTab === 'transcript'" class="space-y-3">
                <div v-if="!project?.has_transcription" class="text-center py-8">
                    <p class="text-xs text-gray-500">{{ t('videoEditor.inspector.noTranscription') }}</p>
                </div>
                <template v-else>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] text-gray-500">
                            {{ segments.length }} {{ t('videoEditor.inspector.segments') }}
                        </p>
                        <button
                            @click="emit('save-transcript', segments)"
                            :disabled="saving"
                            class="px-2 py-1 bg-violet-600 hover:bg-violet-700 text-white text-[10px] font-medium rounded transition-colors disabled:opacity-50"
                        >
                            {{ saving ? t('videoEditor.toolbar.saving') : t('videoEditor.toolbar.save') }}
                        </button>
                    </div>
                    <TranscriptEditor
                        :segments="segments"
                        @update:segments="(segs) => emit('save-transcript', segs)"
                    />
                </template>
            </div>

            <!-- Export tab -->
            <div v-else-if="activeTab === 'export'" class="space-y-4">
                <div v-if="project" class="bg-gray-800/50 rounded-lg p-3 space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">{{ t('videoEditor.export.captionStyle') }}</span>
                        <span class="text-white capitalize">{{ project.caption_style }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">{{ t('videoEditor.export.duration') }}</span>
                        <span class="text-white">{{ formatDuration(project.duration) }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">{{ t('videoEditor.export.resolution') }}</span>
                        <span class="text-white">{{ project.width }}x{{ project.height }}</span>
                    </div>
                </div>

                <button
                    v-if="project?.can_export"
                    @click="emit('render')"
                    :disabled="rendering || project?.is_processing"
                    class="w-full py-2 bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-lg transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                >
                    <svg v-if="rendering || project?.is_processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    {{ t('videoEditor.export.renderVideo') }}
                </button>

                <button
                    v-if="project?.status === 'completed'"
                    @click="emit('download')"
                    class="w-full py-2 border border-green-600/50 text-green-400 hover:bg-green-600/10 text-xs font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ t('videoEditor.export.download') }}
                </button>
            </div>
        </div>
    </div>
</template>
