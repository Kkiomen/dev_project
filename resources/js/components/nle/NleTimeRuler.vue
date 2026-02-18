<template>
    <div
        class="h-6 border-b border-gray-700 relative bg-gray-850 sticky top-0 z-10 cursor-pointer"
        @mousedown="handleClick"
    >
        <template v-for="tick in ticks" :key="tick.time">
            <div
                class="absolute top-0 h-full"
                :style="{ left: tick.x + 'px' }"
            >
                <div
                    class="bg-gray-600"
                    :class="tick.isMajor ? 'w-px h-full' : 'w-px h-2 mt-4'"
                />
                <span
                    v-if="tick.isMajor"
                    class="absolute top-0.5 left-1 text-[9px] text-gray-500 whitespace-nowrap select-none"
                >
                    {{ tick.label }}
                </span>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleTimeline } from '@/composables/useNleTimeline';

const store = useVideoEditorStore();
const timeline = useNleTimeline();

const props = defineProps({
    scrollLeft: { type: Number, default: 0 },
});

const ticks = computed(() => {
    return timeline.getTickMarks(store.timelineWidth);
});

function handleClick(event) {
    const rect = event.currentTarget.getBoundingClientRect();
    const x = event.clientX - rect.left + props.scrollLeft;
    const time = timeline.pixelToTime(x);
    store.seekTo(Math.max(0, time));
}
</script>
