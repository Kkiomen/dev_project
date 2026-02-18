<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    src: { type: String, default: null },
    currentTime: { type: Number, default: 0 },
    isPlaying: { type: Boolean, default: false },
    duration: { type: Number, default: 0 },
    width: { type: Number, default: 1080 },
    height: { type: Number, default: 1920 },
});

const emit = defineEmits(['play', 'pause', 'seek', 'update:canvasEl']);
const { t } = useI18n();

const canvasRef = ref(null);
const isFullscreen = ref(false);
const containerRef = ref(null);

const formattedTime = computed(() => {
    return `${formatTime(props.currentTime)} / ${formatTime(props.duration)}`;
});

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function toggleFullscreen() {
    if (!containerRef.value) return;
    if (!document.fullscreenElement) {
        containerRef.value.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
}

function handleProgressClick(event) {
    const rect = event.currentTarget.getBoundingClientRect();
    const ratio = (event.clientX - rect.left) / rect.width;
    emit('seek', ratio * props.duration);
}

function onCanvasRefChange(el) {
    canvasRef.value = el;
    emit('update:canvasEl', el);
}

function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
}

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
});
</script>

<template>
    <div ref="containerRef" class="flex flex-col h-full bg-black rounded-lg overflow-hidden">
        <!-- Canvas preview area -->
        <div class="flex-1 min-h-0 flex items-center justify-center relative">
            <canvas
                v-if="src"
                :ref="onCanvasRefChange"
                :width="width"
                :height="height"
                class="max-w-full max-h-full object-contain"
            />
            <div v-else class="text-center text-gray-600">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
                </svg>
                <p class="text-xs">{{ t('videoEditor.preview.noOutput') }}</p>
            </div>
        </div>

        <!-- Custom controls -->
        <div class="h-10 px-3 flex items-center gap-3 bg-gray-900/90 border-t border-gray-800 shrink-0">
            <!-- Play/Pause -->
            <button
                @click="isPlaying ? emit('pause') : emit('play')"
                class="p-1 text-white hover:text-violet-400 transition"
            >
                <svg v-if="isPlaying" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </button>

            <!-- Progress bar -->
            <div
                class="flex-1 h-1 bg-gray-700 rounded-full cursor-pointer relative group"
                @click="handleProgressClick"
            >
                <div
                    class="h-full bg-violet-500 rounded-full transition-all"
                    :style="{ width: duration > 0 ? `${(currentTime / duration) * 100}%` : '0%' }"
                />
                <div
                    class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full shadow opacity-0 group-hover:opacity-100 transition-opacity"
                    :style="{ left: duration > 0 ? `calc(${(currentTime / duration) * 100}% - 6px)` : '0' }"
                />
            </div>

            <!-- Time display -->
            <span class="text-[10px] text-gray-400 font-mono whitespace-nowrap">{{ formattedTime }}</span>

            <!-- Fullscreen -->
            <button
                @click="toggleFullscreen"
                class="p-1 text-gray-400 hover:text-white transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path v-if="!isFullscreen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
                </svg>
            </button>
        </div>
    </div>
</template>
