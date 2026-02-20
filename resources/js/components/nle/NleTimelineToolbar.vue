<template>
    <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-700 shrink-0">
        <!-- Left: Actions -->
        <div class="flex items-center gap-1">
            <!-- Add Track -->
            <div class="relative" ref="addTrackRef">
                <button
                    @click="showAddTrack = !showAddTrack"
                    class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('nle.timeline.addTrack') }}
                </button>

                <!-- Dropdown -->
                <div
                    v-if="showAddTrack"
                    class="absolute top-full left-0 mt-1 bg-gray-800 border border-gray-600 rounded shadow-lg z-10 min-w-[120px]"
                >
                    <button
                        v-for="type in ['video', 'audio', 'overlay']"
                        :key="type"
                        @click="addTrack(type)"
                        class="block w-full px-3 py-1.5 text-xs text-gray-300 hover:bg-gray-700 text-left capitalize"
                    >
                        {{ t('nle.track.' + type) }}
                    </button>
                </div>
            </div>

            <!-- Split -->
            <button
                @click="store.splitElementAtPlayhead()"
                :disabled="!store.selectedElementId"
                class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('nle.timeline.split')"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12M8 12h12m-12 5h12M4 7v0m0 5v0m0 5v0" />
                </svg>
                {{ t('nle.timeline.split') }}
            </button>

            <!-- Delete -->
            <button
                @click="deleteSelected"
                :disabled="!store.selectedElementIds.length"
                class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-red-400 bg-gray-800 hover:bg-gray-700 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('nle.timeline.delete')"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>

            <!-- Select All in Track -->
            <button
                @click="selectAllInTrack"
                :disabled="!store.selectedTrackId"
                class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('nle.timeline.selectAllInTrack')"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </button>

            <div class="w-px h-4 bg-gray-700" />

            <!-- Nudge Left -->
            <button
                @click="nudgeLeft"
                :disabled="!store.selectedElementIds.length"
                class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('nle.timeline.nudgeLeft')"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Nudge Right -->
            <button
                @click="nudgeRight"
                :disabled="!store.selectedElementIds.length"
                class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('nle.timeline.nudgeRight')"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <div class="w-px h-4 bg-gray-700" />

            <!-- Remove Silence -->
            <button
                @click="removeSilence"
                :disabled="detectingSilence"
                class="flex items-center gap-1 px-2 py-1 text-[11px] text-gray-400 hover:text-yellow-400 bg-gray-800 hover:bg-gray-700 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('nle.timeline.removeSilence')"
            >
                <svg v-if="!detectingSilence" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
                <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ detectingSilence ? t('nle.timeline.detectingSilence') : t('nle.timeline.removeSilence') }}
            </button>
        </div>

        <!-- Right: Zoom + Snap -->
        <div class="flex items-center gap-2">
            <!-- Snap toggle -->
            <button
                @click="store.snapEnabled = !store.snapEnabled"
                class="px-2 py-1 text-[11px] rounded transition-colors"
                :class="store.snapEnabled
                    ? 'text-blue-400 bg-blue-900/30'
                    : 'text-gray-500 bg-gray-800 hover:bg-gray-700'"
            >
                {{ t('nle.timeline.snap') }}
            </button>

            <!-- Zoom -->
            <div class="flex items-center gap-1">
                <button
                    @click="store.setZoom(store.zoom - 10)"
                    class="p-1 text-gray-400 hover:text-white transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </button>
                <input
                    type="range"
                    :value="store.zoom"
                    @input="store.setZoom(parseInt($event.target.value))"
                    min="10"
                    max="200"
                    class="w-20 accent-blue-500"
                />
                <button
                    @click="store.setZoom(store.zoom + 10)"
                    class="p-1 text-gray-400 hover:text-white transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleHistory } from '@/composables/useNleHistory';

const { t } = useI18n();
const store = useVideoEditorStore();
const history = useNleHistory();

const showAddTrack = ref(false);
const addTrackRef = ref(null);
const detectingSilence = ref(false);

function addTrack(type) {
    store.addTrack(type);
    showAddTrack.value = false;
}

const NUDGE_AMOUNT = 0.1; // seconds

function nudgeLeft() {
    if (!store.selectedElementIds.length) return;
    history.captureState();
    store.moveElements([...store.selectedElementIds], -NUDGE_AMOUNT);
}

function nudgeRight() {
    if (!store.selectedElementIds.length) return;
    history.captureState();
    store.moveElements([...store.selectedElementIds], NUDGE_AMOUNT);
}

function selectAllInTrack() {
    if (store.selectedTrackId) {
        store.selectTrackElements(store.selectedTrackId);
    }
}

function deleteSelected() {
    if (!store.selectedElementIds.length) return;
    history.captureState();
    store.removeElements([...store.selectedElementIds]);
}

async function removeSilence() {
    if (detectingSilence.value) return;
    detectingSilence.value = true;
    try {
        const result = await store.detectSilence();
        if (!result?.speech_regions?.length) {
            store.error = t('nle.timeline.noSilenceDetected');
            return;
        }
        history.captureState();
        const changed = store.applySilenceRemoval(result.speech_regions);
        if (!changed) {
            store.error = t('nle.timeline.noSilenceDetected');
        }
    } finally {
        detectingSilence.value = false;
    }
}

function handleClickOutside(event) {
    if (addTrackRef.value && !addTrackRef.value.contains(event.target)) {
        showAddTrack.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>
