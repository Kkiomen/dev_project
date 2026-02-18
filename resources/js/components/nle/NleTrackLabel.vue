<template>
    <div
        class="flex items-center gap-1 px-2 border-b border-gray-700 group"
        :class="[
            track.type === 'audio' ? 'h-20' : 'h-16',
            {
            'bg-gray-800/50': store.selectedTrackId === track.id,
            'opacity-50': track.muted || !track.visible,
            'border-t-2 border-t-blue-400': dropPosition === 'before',
            'border-b-2 border-b-blue-400': dropPosition === 'after',
            },
        ]"
        @click="store.selectTrack(track.id)"
        draggable="true"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
        @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave"
        @drop.prevent="handleDrop"
    >
        <!-- Drag handle -->
        <div class="w-3 shrink-0 cursor-grab opacity-0 group-hover:opacity-100 transition-opacity">
            <svg class="w-3 h-3 text-gray-500" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="9" cy="6" r="1.5" />
                <circle cx="15" cy="6" r="1.5" />
                <circle cx="9" cy="12" r="1.5" />
                <circle cx="15" cy="12" r="1.5" />
                <circle cx="9" cy="18" r="1.5" />
                <circle cx="15" cy="18" r="1.5" />
            </svg>
        </div>

        <!-- Track icon -->
        <div class="w-4 shrink-0">
            <svg v-if="track.type === 'video'" class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <svg v-else-if="track.type === 'audio'" class="w-3.5 h-3.5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
            </svg>
            <svg v-else class="w-3.5 h-3.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
            </svg>
        </div>

        <!-- Name -->
        <span class="text-[11px] text-gray-300 truncate flex-1">{{ track.name }}</span>

        <!-- Controls -->
        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
            <!-- Move up -->
            <button
                @click.stop="store.moveTrackUp(track.id)"
                :disabled="!canMoveUp"
                class="p-0.5 text-gray-500 hover:text-white disabled:opacity-20 disabled:cursor-not-allowed transition-colors"
                :title="t('nle.track.moveUp')"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
            </button>

            <!-- Move down -->
            <button
                @click.stop="store.moveTrackDown(track.id)"
                :disabled="!canMoveDown"
                class="p-0.5 text-gray-500 hover:text-white disabled:opacity-20 disabled:cursor-not-allowed transition-colors"
                :title="t('nle.track.moveDown')"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <!-- Mute -->
            <button
                @click.stop="store.toggleTrackMute(track.id)"
                class="p-0.5 text-gray-500 hover:text-white transition-colors"
                :class="{ 'text-red-400': track.muted }"
                :title="t('nle.track.mute')"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path v-if="track.muted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M12 6.253v11.494m0-11.494l-4.707 4.707H4v4h3.293L12 17.747V6.253z" />
                </svg>
            </button>

            <!-- Lock -->
            <button
                @click.stop="store.toggleTrackLock(track.id)"
                class="p-0.5 text-gray-500 hover:text-white transition-colors"
                :class="{ 'text-yellow-400': track.locked }"
                :title="t('nle.track.lock')"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path v-if="track.locked" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                </svg>
            </button>

            <!-- Visibility -->
            <button
                @click.stop="store.toggleTrackVisibility(track.id)"
                class="p-0.5 text-gray-500 hover:text-white transition-colors"
                :class="{ 'text-gray-600': !track.visible }"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path v-if="track.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
            </button>

            <!-- Delete track -->
            <button
                @click.stop="store.removeTrack(track.id)"
                class="p-0.5 text-gray-500 hover:text-red-400 transition-colors"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const { t } = useI18n();
const store = useVideoEditorStore();

const props = defineProps({
    track: { type: Object, required: true },
});

const dropPosition = ref(null);

const trackIndex = computed(() =>
    store.composition?.tracks.findIndex(t => t.id === props.track.id) ?? -1
);

const canMoveUp = computed(() => trackIndex.value > 0);
const canMoveDown = computed(() => {
    if (!store.composition?.tracks) return false;
    return trackIndex.value < store.composition.tracks.length - 1;
});

// --- Drag-and-drop reorder ---

function handleDragStart(event) {
    event.dataTransfer.setData('application/nle-track-id', props.track.id);
    event.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd() {
    dropPosition.value = null;
}

function handleDragOver(event) {
    const trackId = event.dataTransfer.types.includes('application/nle-track-id');
    if (!trackId) return;

    event.dataTransfer.dropEffect = 'move';

    const rect = event.currentTarget.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    dropPosition.value = event.clientY < midY ? 'before' : 'after';
}

function handleDragLeave() {
    dropPosition.value = null;
}

function handleDrop(event) {
    dropPosition.value = null;
    const draggedTrackId = event.dataTransfer.getData('application/nle-track-id');
    if (!draggedTrackId || draggedTrackId === props.track.id) return;

    const fromIdx = store.composition.tracks.findIndex(t => t.id === draggedTrackId);
    let toIdx = trackIndex.value;

    if (fromIdx === -1 || toIdx === -1) return;

    const rect = event.currentTarget.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    if (event.clientY > midY && toIdx < store.composition.tracks.length - 1) {
        toIdx++;
    }

    if (fromIdx !== toIdx) {
        store.reorderTracks(fromIdx, toIdx > fromIdx ? toIdx - 1 : toIdx);
    }
}
</script>
