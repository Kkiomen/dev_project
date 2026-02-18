<template>
    <div
        class="relative border-b border-gray-700"
        :class="[
            trackHeightClass,
            {
                'bg-gray-800/30': store.selectedTrackId === track.id,
                'bg-gray-900/50': store.selectedTrackId !== track.id,
                'opacity-50': !track.visible,
            },
        ]"
        @dragover="dragDrop.handleDragOver"
        @drop="dragDrop.handleMediaDrop($event, track.id)"
        @click.self="handleTrackClick"
    >
        <!-- Elements -->
        <NleElementBlock
            v-for="element in track.elements"
            :key="element.id"
            :element="element"
            :track="track"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleDragDrop } from '@/composables/useNleDragDrop';
import { useNleTimeline } from '@/composables/useNleTimeline';
import NleElementBlock from './NleElementBlock.vue';

const store = useVideoEditorStore();
const dragDrop = useNleDragDrop();
const timeline = useNleTimeline();

const props = defineProps({
    track: { type: Object, required: true },
});

const trackHeightClass = computed(() => {
    // Audio tracks are taller to show waveform detail
    return props.track.type === 'audio' ? 'h-20' : 'h-16';
});

function handleTrackClick(event) {
    store.selectTrack(props.track.id);
    const rect = event.currentTarget.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const time = timeline.pixelToTime(x);
    store.seekTo(Math.max(0, time));
}
</script>
