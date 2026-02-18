<script setup>
import { computed } from 'vue';
import { useTimeline } from './composables/useTimeline';

const props = defineProps({
    zoom: { type: Number, required: true },
    duration: { type: Number, required: true },
    scrollLeft: { type: Number, default: 0 },
});

const zoomRef = computed(() => props.zoom);
const durationRef = computed(() => props.duration);
const { timeToX, getGridInterval, getMajorInterval, formatTime } = useTimeline(zoomRef, durationRef);

const ticks = computed(() => {
    const minor = getGridInterval(props.zoom);
    const major = getMajorInterval(props.zoom);
    const result = [];
    const end = props.duration + minor;

    for (let t = 0; t <= end; t += minor) {
        const rounded = Math.round(t * 1000) / 1000;
        result.push({
            time: rounded,
            x: timeToX(rounded),
            isMajor: Math.abs(rounded % major) < 0.001 || Math.abs(rounded % major - major) < 0.001,
            label: (Math.abs(rounded % major) < 0.001 || Math.abs(rounded % major - major) < 0.001) ? formatTime(rounded) : null,
        });
    }

    return result;
});

const totalWidth = computed(() => timeToX(props.duration) + 100);
</script>

<template>
    <div class="h-6 bg-gray-900 border-b border-gray-800 relative overflow-hidden select-none" :style="{ width: `${totalWidth}px` }">
        <template v-for="tick in ticks" :key="tick.time">
            <div
                class="absolute bottom-0"
                :class="tick.isMajor ? 'h-3 w-px bg-gray-500' : 'h-1.5 w-px bg-gray-700'"
                :style="{ left: `${tick.x}px` }"
            />
            <span
                v-if="tick.label"
                class="absolute top-0 text-[9px] text-gray-500 font-mono"
                :style="{ left: `${tick.x + 3}px` }"
            >
                {{ tick.label }}
            </span>
        </template>
    </div>
</template>
