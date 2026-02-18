<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import TimeRuler from './TimeRuler.vue';
import PlayheadLine from './PlayheadLine.vue';
import VideoClip from './VideoClip.vue';
import AudioWaveform from './AudioWaveform.vue';
import CaptionClip from './CaptionClip.vue';
import TimelineToolbar from './TimelineToolbar.vue';

const LABEL_WIDTH = 112; // w-28 = 7rem = 112px
const TRACK_HEIGHT = 72;

const props = defineProps({
    tracks: { type: Array, required: true },
    playhead: { type: Number, required: true },
    zoom: { type: Number, required: true },
    duration: { type: Number, required: true },
    selectedClipId: { type: String, default: null },
    snapEnabled: { type: Boolean, default: true },
    waveformPeaks: { type: Array, default: () => [] },
    thumbnails: { type: Array, default: () => [] },
});

const emit = defineEmits([
    'seek',
    'zoom',
    'select-clip',
    'split',
    'delete-clip',
    'toggle-snap',
    'move-clip',
    'trim-clip',
    'toggle-track-mute',
    'toggle-track-lock',
]);

const { t } = useI18n();
const scrollRef = ref(null);
const scrollLeft = ref(0);

const timelineWidth = computed(() => Math.ceil(props.duration * props.zoom) + 200);
const trackAreaHeight = computed(() => props.tracks.length * TRACK_HEIGHT);

const trackIcons = { video: '🎬', audio: '🔊', captions: '🔤', music: '🎵' };

function handleRulerClick(event) {
    const rect = event.currentTarget.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const time = x / props.zoom;
    emit('seek', Math.max(0, Math.min(time, props.duration)));
}

function handleScroll() {
    if (scrollRef.value) {
        scrollLeft.value = scrollRef.value.scrollLeft;
    }
}

// Keep playhead visible during playback
watch(() => props.playhead, (time) => {
    if (!scrollRef.value) return;
    const x = time * props.zoom;
    const viewLeft = scrollRef.value.scrollLeft;
    const viewRight = viewLeft + scrollRef.value.clientWidth;

    if (x < viewLeft + 50 || x > viewRight - 50) {
        scrollRef.value.scrollLeft = x - scrollRef.value.clientWidth / 3;
    }
});
</script>

<template>
    <div class="flex flex-col bg-gray-950 border-t border-gray-800 h-full">
        <!-- Timeline Toolbar -->
        <TimelineToolbar
            :zoom="zoom"
            :snap-enabled="snapEnabled"
            :has-selection="!!selectedClipId"
            @zoom="(v) => emit('zoom', v)"
            @split="emit('split')"
            @delete="emit('delete-clip')"
            @toggle-snap="emit('toggle-snap')"
        />

        <!-- Timeline body: fixed labels column + scrollable clip area -->
        <div class="flex-1 min-h-0 flex">
            <!-- Fixed track labels column -->
            <div class="shrink-0 flex flex-col" :style="{ width: `${LABEL_WIDTH}px` }">
                <!-- Ruler spacer (matches ruler height) -->
                <div class="h-6 bg-gray-900 border-b border-gray-800 border-r border-r-gray-800" />
                <!-- Track labels -->
                <div class="flex-1 overflow-hidden">
                    <div
                        v-for="track in tracks"
                        :key="'label-' + track.id"
                        class="border-b border-gray-800/50 border-r border-r-gray-800 bg-gray-900 flex items-center px-2 gap-1.5"
                        :style="{ height: `${TRACK_HEIGHT}px` }"
                    >
                        <span class="text-sm">{{ trackIcons[track.type] || '📁' }}</span>
                        <span class="text-[10px] text-gray-400 font-medium truncate">
                            {{ t(`videoEditor.tracks.${track.type}`) }}
                        </span>

                        <div class="ml-auto flex items-center gap-0.5">
                            <!-- Mute -->
                            <button
                                @click="emit('toggle-track-mute', track.id)"
                                class="p-0.5 rounded transition"
                                :class="track.muted ? 'text-red-400' : 'text-gray-600 hover:text-gray-400'"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path v-if="track.muted" stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                </svg>
                            </button>
                            <!-- Lock -->
                            <button
                                @click="emit('toggle-track-lock', track.id)"
                                class="p-0.5 rounded transition"
                                :class="track.locked ? 'text-amber-400' : 'text-gray-600 hover:text-gray-400'"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path v-if="track.locked" stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scrollable timeline (ruler + clips + playhead share the same X coordinate space) -->
            <div
                ref="scrollRef"
                class="flex-1 min-w-0 overflow-auto"
                @scroll="handleScroll"
            >
                <div :style="{ width: `${timelineWidth}px` }" class="relative">
                    <!-- Time Ruler (click to seek) -->
                    <div class="sticky top-0 z-20 cursor-pointer" @click="handleRulerClick">
                        <TimeRuler
                            :zoom="zoom"
                            :duration="duration"
                            :scroll-left="scrollLeft"
                        />
                    </div>

                    <!-- Tracks clip area + Playhead — same parent, same coordinate system -->
                    <div class="relative">
                        <!-- Track rows (clips only, no labels) -->
                        <div
                            v-for="track in tracks"
                            :key="track.id"
                            class="border-b border-gray-800/50 relative"
                            :style="{ height: `${TRACK_HEIGHT}px` }"
                        >
                            <template v-if="track.type === 'video'">
                                <VideoClip
                                    v-for="clip in track.clips"
                                    :key="clip.id"
                                    :clip="clip"
                                    :zoom="zoom"
                                    :selected="clip.id === selectedClipId"
                                    :thumbnails="thumbnails"
                                    @select="emit('select-clip', clip.id)"
                                    @move="(s) => emit('move-clip', clip.id, s)"
                                    @trim="(side, val) => emit('trim-clip', clip.id, side, val)"
                                />
                            </template>

                            <template v-else-if="track.type === 'audio'">
                                <AudioWaveform
                                    v-for="clip in track.clips"
                                    :key="clip.id"
                                    :clip="clip"
                                    :zoom="zoom"
                                    :peaks="waveformPeaks"
                                    :selected="clip.id === selectedClipId"
                                    :source-duration="duration"
                                    @select="emit('select-clip', clip.id)"
                                    @move="(s) => emit('move-clip', clip.id, s)"
                                    @trim="(side, val) => emit('trim-clip', clip.id, side, val)"
                                />
                            </template>

                            <template v-else-if="track.type === 'captions'">
                                <CaptionClip
                                    v-for="clip in track.clips"
                                    :key="clip.id"
                                    :clip="clip"
                                    :zoom="zoom"
                                    :selected="clip.id === selectedClipId"
                                    @select="emit('select-clip', clip.id)"
                                    @move="(s) => emit('move-clip', clip.id, s)"
                                />
                            </template>
                        </div>

                        <!-- Playhead — inside same div as clips -->
                        <PlayheadLine
                            :time="playhead"
                            :zoom="zoom"
                            :height="trackAreaHeight"
                            @seek="(t) => emit('seek', t)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
