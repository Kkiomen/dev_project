<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useVideoManagerStore } from '@/stores/videoManager';
import { useToast } from '@/composables/useToast';
import TranscriptEditor from '@/components/video/TranscriptEditor.vue';
import CaptionStylePicker from '@/components/video/CaptionStylePicker.vue';
import SilenceRemovalPanel from '@/components/videoManager/SilenceRemovalPanel.vue';

const props = defineProps({
    projectId: { type: String, required: true },
});

const { t } = useI18n();
const router = useRouter();
const videoManagerStore = useVideoManagerStore();
const toast = useToast();

const activeTab = ref('transcript');
const saving = ref(false);
const rendering = ref(false);
let refreshInterval = null;

const project = computed(() => videoManagerStore.currentProject);
const segments = computed(() => project.value?.transcription?.segments || []);

const tabs = computed(() => [
    { key: 'transcript', label: t('videoManager.editor.tabTranscript') },
    { key: 'captions', label: t('videoManager.editor.tabCaptions') },
    { key: 'silence', label: t('videoManager.editor.tabSilence') },
    { key: 'export', label: t('videoManager.editor.tabExport') },
]);

const captionStyle = ref('clean');
const captionSettings = ref({
    highlight_keywords: false,
    position: 'bottom',
    font_size: 48,
});

const loadProject = async () => {
    try {
        await videoManagerStore.fetchProject(props.projectId);
        if (project.value) {
            captionStyle.value = project.value.caption_style || 'clean';
            captionSettings.value = {
                highlight_keywords: project.value.caption_settings?.highlight_keywords ?? false,
                position: project.value.caption_settings?.position ?? 'bottom',
                font_size: project.value.caption_settings?.font_size ?? 48,
            };
        }
    } catch {
        toast.error(t('videoManager.editor.loadFailed'));
        router.push({ name: 'videoManager.library' });
    }
};

const saveTranscript = async (updatedSegments) => {
    saving.value = true;
    try {
        await videoManagerStore.updateProject(props.projectId, {
            transcription: { segments: updatedSegments },
        });
        toast.success(t('videoManager.editor.saved'));
    } catch {
        toast.error(t('videoManager.editor.saveFailed'));
    } finally {
        saving.value = false;
    }
};

const saveCaptionSettings = async () => {
    saving.value = true;
    try {
        await videoManagerStore.updateProject(props.projectId, {
            caption_style: captionStyle.value,
            caption_settings: captionSettings.value,
        });
        toast.success(t('videoManager.editor.saved'));
    } catch {
        toast.error(t('videoManager.editor.saveFailed'));
    } finally {
        saving.value = false;
    }
};

const handleRender = async () => {
    rendering.value = true;
    try {
        await saveCaptionSettings();
        await videoManagerStore.renderProject(props.projectId);
        toast.success(t('videoManager.editor.renderStarted'));
    } catch {
        toast.error(t('videoManager.editor.renderFailed'));
    } finally {
        rendering.value = false;
    }
};

const handleRemoveSilence = async (options) => {
    try {
        await videoManagerStore.removeSilence(props.projectId, options);
        toast.success(t('videoManager.editor.silenceRemovalStarted'));
    } catch {
        toast.error(t('videoManager.editor.silenceRemovalFailed'));
    }
};

const handleDownload = () => {
    window.open(videoManagerStore.getDownloadUrl(props.projectId), '_blank');
};

const formatDuration = (seconds) => {
    if (!seconds) return '--';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};

onMounted(() => {
    loadProject();
    videoManagerStore.fetchCaptionStyles();

    // Auto-refresh while processing
    refreshInterval = setInterval(() => {
        if (project.value?.is_processing) {
            videoManagerStore.fetchProject(props.projectId);
        }
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

// Watch for status changes
watch(() => project.value?.status, (newStatus, oldStatus) => {
    if (oldStatus && newStatus !== oldStatus) {
        if (newStatus === 'completed') {
            toast.success(t('videoManager.editor.renderCompleted'));
        } else if (newStatus === 'failed') {
            toast.error(t('videoManager.editor.projectFailed'));
        }
    }
});
</script>

<template>
    <div class="h-full flex flex-col">
        <!-- Top bar -->
        <div class="px-4 sm:px-6 py-3 border-b border-gray-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button
                    @click="router.push({ name: 'videoManager.library' })"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition shrink-0"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </button>
                <div class="min-w-0">
                    <h1 class="text-sm font-semibold text-white truncate">{{ project?.title || '...' }}</h1>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span v-if="project?.duration">{{ formatDuration(project.duration) }}</span>
                        <span
                            v-if="project?.status_label"
                            class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                            :class="{
                                'bg-green-500/20 text-green-400': project.status === 'completed',
                                'bg-purple-500/20 text-purple-400': project.status === 'transcribed',
                                'bg-amber-500/20 text-amber-400': project.is_processing,
                                'bg-red-500/20 text-red-400': project.status === 'failed',
                            }"
                        >
                            {{ project.status_label }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    v-if="project?.status === 'completed'"
                    @click="handleDownload"
                    class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-700 text-gray-300 hover:text-white text-sm rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ t('videoManager.editor.download') }}
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 min-h-0 flex flex-col lg:flex-row">
            <!-- Left panel: Video preview -->
            <div class="lg:w-1/2 xl:w-3/5 p-4 sm:p-6 flex flex-col">
                <div class="flex-1 bg-gray-900 rounded-xl border border-gray-800 flex items-center justify-center min-h-[200px]">
                    <div class="text-center">
                        <svg class="w-16 h-16 mx-auto text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">{{ t('videoManager.editor.videoPreview') }}</p>
                        <p v-if="project?.width && project?.height" class="text-xs text-gray-600 mt-1">
                            {{ project.width }}x{{ project.height }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right panel: Tabs -->
            <div class="lg:w-1/2 xl:w-2/5 border-t lg:border-t-0 lg:border-l border-gray-800 flex flex-col min-h-0">
                <!-- Tab bar -->
                <div class="flex border-b border-gray-800 shrink-0 overflow-x-auto">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        class="px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors border-b-2"
                        :class="activeTab === tab.key
                            ? 'text-violet-400 border-violet-500'
                            : 'text-gray-400 border-transparent hover:text-gray-200'"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Tab content -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-5">
                    <!-- Loading state -->
                    <div v-if="videoManagerStore.projectLoading" class="flex items-center justify-center py-16">
                        <svg class="w-8 h-8 text-violet-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </div>

                    <!-- Transcript Tab -->
                    <div v-else-if="activeTab === 'transcript'" class="space-y-4">
                        <div v-if="!project?.has_transcription" class="text-center py-8">
                            <p class="text-sm text-gray-500">{{ t('videoManager.editor.noTranscription') }}</p>
                            <p v-if="project?.is_processing" class="text-xs text-gray-600 mt-1">{{ t('videoManager.editor.transcribing') }}</p>
                        </div>
                        <template v-else>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-gray-500">
                                    {{ t('videoManager.editor.segmentCount', { count: segments.length }) }}
                                </p>
                                <button
                                    @click="saveTranscript(segments)"
                                    :disabled="saving"
                                    class="px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white text-xs font-medium rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {{ saving ? t('videoManager.editor.saving') : t('videoManager.editor.save') }}
                                </button>
                            </div>
                            <TranscriptEditor
                                :segments="segments"
                                @update:segments="saveTranscript"
                            />
                        </template>
                    </div>

                    <!-- Captions Tab -->
                    <div v-else-if="activeTab === 'captions'" class="space-y-5">
                        <!-- Style picker -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-300 mb-3">{{ t('videoManager.editor.captionStyle') }}</h4>
                            <CaptionStylePicker
                                v-model="captionStyle"
                                :styles="videoManagerStore.captionStyles"
                            />
                        </div>

                        <!-- Position -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-300 mb-2">{{ t('videoManager.editor.position') }}</h4>
                            <div class="flex gap-2">
                                <button
                                    v-for="pos in ['top', 'center', 'bottom']"
                                    :key="pos"
                                    @click="captionSettings.position = pos"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors capitalize"
                                    :class="captionSettings.position === pos
                                        ? 'bg-violet-600 text-white'
                                        : 'bg-gray-800 text-gray-400 hover:text-white'"
                                >
                                    {{ t(`videoManager.editor.position${pos.charAt(0).toUpperCase() + pos.slice(1)}`) }}
                                </button>
                            </div>
                        </div>

                        <!-- Font size -->
                        <div>
                            <label class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-300">{{ t('videoManager.editor.fontSize') }}</span>
                                <span class="text-sm text-violet-400 font-medium">{{ captionSettings.font_size }}px</span>
                            </label>
                            <input
                                v-model.number="captionSettings.font_size"
                                type="range"
                                min="16"
                                max="128"
                                step="2"
                                class="w-full h-1.5 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-violet-500"
                            />
                        </div>

                        <!-- Highlight keywords -->
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="captionSettings.highlight_keywords"
                                type="checkbox"
                                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-violet-600 focus:ring-violet-500"
                            />
                            <span class="text-sm text-gray-300">{{ t('videoManager.editor.highlightKeywords') }}</span>
                        </label>

                        <!-- Save -->
                        <button
                            @click="saveCaptionSettings"
                            :disabled="saving"
                            class="w-full py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                        >
                            {{ saving ? t('videoManager.editor.saving') : t('videoManager.editor.saveSettings') }}
                        </button>

                        <p class="text-xs text-gray-600 text-center">{{ t('videoManager.editor.renderToSeeChanges') }}</p>
                    </div>

                    <!-- Silence Removal Tab -->
                    <div v-else-if="activeTab === 'silence'">
                        <div v-if="!project?.has_transcription" class="text-center py-8">
                            <p class="text-sm text-gray-500">{{ t('videoManager.editor.needsTranscription') }}</p>
                        </div>
                        <SilenceRemovalPanel
                            v-else
                            :segments="segments"
                            :duration="project?.duration || 0"
                            :processing="project?.is_processing"
                            @remove-silence="handleRemoveSilence"
                        />
                    </div>

                    <!-- Export Tab -->
                    <div v-else-if="activeTab === 'export'" class="space-y-5">
                        <!-- Project summary -->
                        <div class="bg-gray-800/50 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ t('videoManager.editor.projectTitle') }}</span>
                                <span class="text-white">{{ project?.title }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ t('videoManager.editor.duration') }}</span>
                                <span class="text-white">{{ formatDuration(project?.duration) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ t('videoManager.editor.captionStyle') }}</span>
                                <span class="text-white capitalize">{{ project?.caption_style }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ t('videoManager.editor.language') }}</span>
                                <span class="text-white">{{ project?.language || t('videoManager.editor.autoDetected') }}</span>
                            </div>
                        </div>

                        <!-- Render button -->
                        <button
                            v-if="project?.can_export"
                            @click="handleRender"
                            :disabled="rendering || project?.is_processing"
                            class="w-full py-3 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="rendering || project?.is_processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            {{ project?.status === 'completed' ? t('videoManager.editor.reRender') : t('videoManager.editor.render') }}
                        </button>

                        <!-- Processing indicator -->
                        <div v-if="project?.is_processing" class="text-center">
                            <p class="text-sm text-amber-400">{{ t('videoManager.editor.processingNote') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ t('videoManager.editor.autoRefresh') }}</p>
                        </div>

                        <!-- Download -->
                        <button
                            v-if="project?.status === 'completed'"
                            @click="handleDownload"
                            class="w-full py-3 border border-green-600/50 text-green-400 hover:bg-green-600/10 text-sm font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            {{ t('videoManager.editor.downloadOutput') }}
                        </button>

                        <!-- Error display -->
                        <div v-if="project?.status === 'failed' && project?.error_message" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                            <p class="text-sm text-red-400 font-medium">{{ t('videoManager.editor.error') }}</p>
                            <p class="text-xs text-red-300/70 mt-1">{{ project.error_message }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
