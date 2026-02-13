<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    project: { type: Object, required: true },
});

const { t } = useI18n();
const videoRef = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);

const videoUrl = computed(() => {
    if (!props.project?.video_path) return null;
    return `/api/v1/video-projects/${props.project.id}/download`;
});

const aspectRatio = computed(() => {
    if (!props.project?.width || !props.project?.height) return '9/16';
    return `${props.project.width}/${props.project.height}`;
});

function togglePlay() {
    if (!videoRef.value) return;
    if (videoRef.value.paused) {
        videoRef.value.play();
        isPlaying.value = true;
    } else {
        videoRef.value.pause();
        isPlaying.value = false;
    }
}

function onTimeUpdate() {
    if (videoRef.value) {
        currentTime.value = videoRef.value.currentTime;
    }
}

function seekTo(time) {
    if (videoRef.value) {
        videoRef.value.currentTime = time;
    }
}

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

defineExpose({ seekTo, currentTime });
</script>

<template>
    <div class="flex flex-col items-center w-full max-w-md">
        <!-- Video Player or Placeholder -->
        <div
            class="relative w-full rounded-lg overflow-hidden bg-black"
            :style="{ aspectRatio }"
        >
            <video
                v-if="project.status === 'completed' && project.output_path"
                ref="videoRef"
                class="w-full h-full object-contain"
                @timeupdate="onTimeUpdate"
                @ended="isPlaying = false"
                @click="togglePlay"
            >
                <source :src="videoUrl" type="video/mp4" />
            </video>

            <!-- Placeholder for non-completed projects -->
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <p class="text-sm">
                    {{ project.is_processing
                        ? t('videoEditor.preview.processing')
                        : t('videoEditor.preview.noOutput')
                    }}
                </p>
            </div>

            <!-- Play/Pause Overlay -->
            <div
                v-if="project.status === 'completed' && !isPlaying"
                class="absolute inset-0 flex items-center justify-center cursor-pointer"
                @click="togglePlay"
            >
                <div class="bg-black/50 rounded-full p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8.132v3.736a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664l-3.197-2.736z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Time Display -->
        <div v-if="project.duration" class="mt-2 text-xs text-gray-400">
            {{ formatTime(currentTime) }} / {{ formatTime(project.duration) }}
        </div>
    </div>
</template>
