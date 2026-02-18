<template>
    <div class="flex items-center justify-center gap-4 px-4 py-2 border-t border-gray-800 bg-gray-900/50">
        <!-- Time display -->
        <span class="text-xs text-gray-400 font-mono w-24 text-right">
            {{ timeline.formatTimecode(store.playhead) }}
        </span>

        <!-- Controls -->
        <div class="flex items-center gap-1">
            <!-- Skip to start -->
            <button
                @click="store.seekTo(0)"
                class="p-1.5 text-gray-400 hover:text-white transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>

            <!-- Play/Pause -->
            <button
                @click="store.togglePlayback()"
                class="p-2 text-white bg-blue-600 hover:bg-blue-500 rounded-full transition-colors"
            >
                <!-- Pause icon -->
                <svg v-if="store.isPlaying" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                </svg>
                <!-- Play icon -->
                <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </button>

            <!-- Skip to end -->
            <button
                @click="store.seekTo(store.timelineDuration)"
                class="p-1.5 text-gray-400 hover:text-white transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- Duration display -->
        <span class="text-xs text-gray-500 font-mono w-24">
            {{ timeline.formatTimecode(store.timelineDuration) }}
        </span>

        <!-- Volume control -->
        <div class="flex items-center gap-1.5 ml-2">
            <button
                @click="toggleMute"
                class="p-1 text-gray-400 hover:text-white transition-colors"
                :title="store.masterVolume > 0 ? t('nle.playback.mute') : t('nle.playback.volume')"
            >
                <!-- Muted -->
                <svg v-if="store.masterVolume === 0" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
                <!-- Low volume -->
                <svg v-else-if="store.masterVolume < 0.5" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072" />
                </svg>
                <!-- Normal volume -->
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728" />
                </svg>
            </button>

            <input
                type="range"
                min="0"
                max="1"
                step="0.05"
                :value="store.masterVolume"
                @input="store.setMasterVolume(parseFloat($event.target.value))"
                class="w-16 h-1 accent-blue-500 cursor-pointer"
                :title="t('nle.playback.volume')"
            />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleTimeline } from '@/composables/useNleTimeline';

const { t } = useI18n();
const store = useVideoEditorStore();
const timeline = useNleTimeline();

const previousVolume = ref(1);

function toggleMute() {
    if (store.masterVolume > 0) {
        previousVolume.value = store.masterVolume;
        store.setMasterVolume(0);
    } else {
        store.setMasterVolume(previousVolume.value || 1);
    }
}
</script>
