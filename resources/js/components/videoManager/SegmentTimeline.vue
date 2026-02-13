<script setup>
import { computed } from 'vue';

const props = defineProps({
    segments: { type: Array, default: () => [] },
    duration: { type: Number, default: 0 },
});

const timelineSegments = computed(() => {
    if (!props.duration || props.segments.length === 0) return [];

    const result = [];
    let lastEnd = 0;

    for (const seg of props.segments) {
        // Gap (silence) before this segment
        if (seg.start > lastEnd) {
            result.push({
                type: 'silence',
                start: lastEnd,
                end: seg.start,
                width: ((seg.start - lastEnd) / props.duration) * 100,
            });
        }
        // Speech segment
        result.push({
            type: 'speech',
            start: seg.start,
            end: seg.end,
            text: seg.text || '',
            width: ((seg.end - seg.start) / props.duration) * 100,
        });
        lastEnd = seg.end;
    }

    // Trailing silence
    if (lastEnd < props.duration) {
        result.push({
            type: 'silence',
            start: lastEnd,
            end: props.duration,
            width: ((props.duration - lastEnd) / props.duration) * 100,
        });
    }

    return result;
});

const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};
</script>

<template>
    <div class="space-y-2">
        <!-- Timeline bar -->
        <div class="flex h-8 rounded-lg overflow-hidden bg-gray-800">
            <div
                v-for="(seg, idx) in timelineSegments"
                :key="idx"
                :style="{ width: `${Math.max(seg.width, 0.5)}%` }"
                :class="[
                    'h-full transition-colors cursor-default',
                    seg.type === 'speech' ? 'bg-green-600/70 hover:bg-green-600' : 'bg-gray-700/50 hover:bg-red-600/30',
                ]"
                :title="`${formatTime(seg.start)} - ${formatTime(seg.end)}${seg.text ? ': ' + seg.text.substring(0, 50) : ''}`"
            />
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 text-xs text-gray-500">
            <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded bg-green-600/70" />
                <span>Speech</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded bg-gray-700/50" />
                <span>Silence</span>
            </div>
            <span class="ml-auto">{{ formatTime(duration) }}</span>
        </div>
    </div>
</template>
