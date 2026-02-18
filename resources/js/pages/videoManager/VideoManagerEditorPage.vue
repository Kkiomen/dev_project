<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditor';
import { useVideoManagerStore } from '@/stores/videoManager';
import { useToast } from '@/composables/useToast';
import { useCanvasPlayback } from '@/components/videoEditor/composables/useCanvasPlayback';
import { useEditorHistory } from '@/components/videoEditor/composables/useEditorHistory';

import VideoEditorToolbar from '@/components/videoEditor/VideoEditorToolbar.vue';
import VideoPlayerPanel from '@/components/videoEditor/VideoPlayerPanel.vue';
import InspectorPanel from '@/components/videoEditor/InspectorPanel.vue';
import TimelineContainer from '@/components/videoEditor/TimelineContainer.vue';

const props = defineProps({
    projectId: { type: String, required: true },
});

const { t } = useI18n();
const router = useRouter();
const editorStore = useVideoEditorStore();
const videoManagerStore = useVideoManagerStore();
const toast = useToast();

const saving = ref(false);
const rendering = ref(false);
const inspectorOpen = ref(true);
let refreshInterval = null;

const project = computed(() => editorStore.project);
const videoCacheBuster = ref(Date.now());

const videoSrc = computed(() => {
    if (!project.value) return null;
    const url = project.value.output_url || project.value.video_url;
    return url ? `${url}?v=${videoCacheBuster.value}` : null;
});

// Canvas element ref + canvas playback engine
const canvasEl = ref(null);
const playback = useCanvasPlayback(canvasEl, {
    getVideoClips: () => editorStore.sortedVideoClips,
    getAudioClips: () => editorStore.sortedAudioClips,
    src: videoSrc,
});

// Undo/redo
const { canUndo, canRedo, pushState, undo, redo } = useEditorHistory(
    (snapshot) => editorStore.applyState(snapshot),
    () => editorStore.captureState(),
);

const segments = computed(() => project.value?.transcription?.segments || []);

// Sync playback ↔ store
watch(() => playback.currentTime.value, (t) => {
    editorStore.seekTo(t);
});

watch(() => playback.isPlaying.value, (p) => {
    editorStore.isPlaying = p;
});

watch(() => playback.duration.value, (d) => {
    if (d > 0 && d !== editorStore.duration) {
        editorStore.duration = d;
        editorStore.initializeTracks();
    }
});

// === Actions ===

async function loadProject() {
    try {
        await editorStore.loadProject(props.projectId);
        videoManagerStore.fetchCaptionStyles();
        // Load waveform & thumbnails in background
        editorStore.loadWaveform(props.projectId);
        editorStore.loadThumbnails(props.projectId);
    } catch {
        toast.error(t('videoEditor.errors.loadFailed'));
        router.push({ name: 'videoManager.library' });
    }
}

function onCanvasElUpdate(el) {
    canvasEl.value = el;
}

function handlePlay() {
    playback.play();
}

function handleSeek(time) {
    playback.seekTo(time);
}

function handleZoom(pxPerSec) {
    editorStore.setZoom(pxPerSec);
}

function handleSelectClip(clipId) {
    editorStore.selectClip(clipId);
}

function handleSplit() {
    pushState('split');
    editorStore.splitClipAtPlayhead();
}

function handleDeleteClip() {
    if (!editorStore.selectedClipId) return;
    pushState('delete');
    editorStore.deleteClip(editorStore.selectedClipId);
}

function handleMoveClip(clipId, newStart) {
    editorStore.moveClip(clipId, newStart);
}

function handleTrimClip(clipId, side, value) {
    editorStore.trimClip(clipId, side, value);
}

function handleToggleSnap() {
    editorStore.snapEnabled = !editorStore.snapEnabled;
}

async function handleSave() {
    saving.value = true;
    try {
        await videoManagerStore.updateProject(props.projectId, {
            caption_style: project.value?.caption_style,
            caption_settings: project.value?.caption_settings,
            transcription: { segments: segments.value },
        });
        toast.success(t('videoEditor.saved'));
    } catch {
        toast.error(t('videoEditor.errors.saveFailed'));
    } finally {
        saving.value = false;
    }
}

async function handleExport() {
    rendering.value = true;
    try {
        await handleSave();
        await videoManagerStore.renderProject(props.projectId);
        toast.success(t('videoEditor.renderStarted'));
    } catch {
        toast.error(t('videoEditor.errors.renderFailed'));
    } finally {
        rendering.value = false;
    }
}

function handleDownload() {
    window.open(videoManagerStore.getDownloadUrl(props.projectId), '_blank');
}

async function handleSaveTranscript(updatedSegments) {
    saving.value = true;
    try {
        await videoManagerStore.updateProject(props.projectId, {
            transcription: { segments: updatedSegments },
        });
        toast.success(t('videoEditor.saved'));
    } catch {
        toast.error(t('videoEditor.errors.saveFailed'));
    } finally {
        saving.value = false;
    }
}

function handleCaptionStyleUpdate(style) {
    if (editorStore.project) {
        editorStore.project.caption_style = style;
    }
}

function handleCaptionSettingsUpdate(settings) {
    if (editorStore.project) {
        editorStore.project.caption_settings = settings;
    }
}

// Keyboard shortcuts
function handleKeyDown(event) {
    // Space → play/pause
    if (event.code === 'Space' && !event.target.closest('input, textarea, [contenteditable]')) {
        event.preventDefault();
        if (playback.isPlaying.value) {
            playback.pause();
        } else {
            handlePlay();
        }
    }
    // Delete / Backspace → delete selected clip
    if ((event.code === 'Delete' || event.code === 'Backspace') && !event.target.closest('input, textarea, [contenteditable]')) {
        event.preventDefault();
        handleDeleteClip();
    }
    // S → split
    if (event.code === 'KeyS' && !event.ctrlKey && !event.metaKey && !event.target.closest('input, textarea, [contenteditable]')) {
        handleSplit();
    }
    // Left/Right arrow → step frame
    if (event.code === 'ArrowLeft' && !event.target.closest('input, textarea, [contenteditable]')) {
        event.preventDefault();
        playback.stepFrame(-1);
    }
    if (event.code === 'ArrowRight' && !event.target.closest('input, textarea, [contenteditable]')) {
        event.preventDefault();
        playback.stepFrame(1);
    }
}

// Auto-refresh for processing status
onMounted(() => {
    loadProject();

    window.addEventListener('keydown', handleKeyDown);

    refreshInterval = setInterval(() => {
        if (project.value?.is_processing) {
            editorStore.loadProject(props.projectId);
        }
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
    window.removeEventListener('keydown', handleKeyDown);
    editorStore.$reset();
});

// Watch for status changes
watch(() => project.value?.status, (newStatus, oldStatus) => {
    if (oldStatus && newStatus !== oldStatus) {
        if (newStatus === 'completed') {
            toast.success(t('videoEditor.renderComplete'));
            videoCacheBuster.value = Date.now();
        } else if (newStatus === 'transcribed') {
            videoCacheBuster.value = Date.now();
        } else if (newStatus === 'failed') {
            toast.error(t('videoEditor.errors.processingFailed'));
        }
    }
});
</script>

<template>
    <div class="h-full flex flex-col bg-gray-950 overflow-hidden">
        <!-- Top Toolbar -->
        <VideoEditorToolbar
            :title="project?.title"
            :status="project?.status"
            :status-label="project?.status_label"
            :is-processing="project?.is_processing"
            :can-undo="canUndo"
            :can-redo="canRedo"
            :saving="saving"
            @back="router.push({ name: 'videoManager.library' })"
            @undo="undo"
            @redo="redo"
            @save="handleSave"
            @export="handleExport"
        />

        <!-- Main content: Preview + Inspector -->
        <div class="flex-1 min-h-0 flex">
            <!-- Preview area -->
            <div class="flex-1 min-w-0 p-2">
                <VideoPlayerPanel
                    :src="videoSrc"
                    :current-time="editorStore.playhead"
                    :is-playing="editorStore.isPlaying"
                    :duration="editorStore.timelineDuration"
                    :width="project?.width || 1080"
                    :height="project?.height || 1920"
                    @play="handlePlay"
                    @pause="playback.pause()"
                    @seek="handleSeek"
                    @update:canvas-el="onCanvasElUpdate"
                />
            </div>

            <!-- Inspector panel (collapsible) -->
            <div v-if="inspectorOpen" class="w-72 shrink-0 hidden lg:flex">
                <InspectorPanel
                    :project="project"
                    :selected-clip="editorStore.selectedClip"
                    :active-tab="editorStore.inspectorTab"
                    :caption-style="project?.caption_style || 'clean'"
                    :caption-styles="videoManagerStore.captionStyles"
                    :segments="segments"
                    :saving="saving"
                    :rendering="rendering"
                    @update:active-tab="(tab) => editorStore.inspectorTab = tab"
                    @update:caption-style="handleCaptionStyleUpdate"
                    @update:caption-settings="handleCaptionSettingsUpdate"
                    @save-transcript="handleSaveTranscript"
                    @render="handleExport"
                    @download="handleDownload"
                />
            </div>
        </div>

        <!-- Timeline (bottom panel) -->
        <div class="h-[240px] shrink-0 lg:h-[280px]">
            <TimelineContainer
                :tracks="editorStore.tracks"
                :playhead="editorStore.playhead"
                :zoom="editorStore.zoom"
                :duration="editorStore.duration"
                :selected-clip-id="editorStore.selectedClipId"
                :snap-enabled="editorStore.snapEnabled"
                :waveform-peaks="editorStore.waveformPeaks"
                :thumbnails="editorStore.thumbnails"
                @seek="handleSeek"
                @zoom="handleZoom"
                @select-clip="handleSelectClip"
                @split="handleSplit"
                @delete-clip="handleDeleteClip"
                @toggle-snap="handleToggleSnap"
                @move-clip="handleMoveClip"
                @trim-clip="handleTrimClip"
                @toggle-track-mute="(id) => editorStore.toggleTrackMute(id)"
                @toggle-track-lock="(id) => editorStore.toggleTrackLock(id)"
            />
        </div>

        <!-- Loading overlay -->
        <div
            v-if="editorStore.loading"
            class="absolute inset-0 bg-gray-950/80 flex items-center justify-center z-50"
        >
            <div class="text-center">
                <svg class="w-8 h-8 text-violet-500 animate-spin mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                </svg>
                <p class="text-sm text-gray-400 mt-2">{{ t('videoEditor.loading') }}</p>
            </div>
        </div>
    </div>
</template>
