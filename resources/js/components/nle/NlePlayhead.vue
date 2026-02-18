<template>
    <div
        class="absolute top-0 bottom-0 w-px bg-red-500 z-20 pointer-events-none"
        :style="{ left: position + 'px' }"
    >
        <!-- Playhead handle -->
        <div class="absolute -top-0 -left-1.5 w-3 h-3 bg-red-500 pointer-events-auto cursor-ew-resize"
             style="clip-path: polygon(0 0, 100% 0, 50% 100%)"
             @mousedown.stop="startDrag"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleTimeline } from '@/composables/useNleTimeline';

const store = useVideoEditorStore();
const timeline = useNleTimeline();

const position = computed(() => timeline.timeToPixel(store.playhead));

function startDrag(event) {
    const startX = event.clientX;
    const startTime = store.playhead;

    const onMove = (e) => {
        const deltaX = e.clientX - startX;
        const deltaTime = timeline.pixelToTime(deltaX);
        store.seekTo(Math.max(0, startTime + deltaTime));
    };

    const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}
</script>
