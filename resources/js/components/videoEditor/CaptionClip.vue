<script setup>
import { computed } from 'vue';

const props = defineProps({
    clip: { type: Object, required: true },
    zoom: { type: Number, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'move']);

const clipDuration = computed(() => props.clip.sourceOut - props.clip.sourceIn);
const left = computed(() => props.clip.timelineStart * props.zoom);
const width = computed(() => Math.max(4, clipDuration.value * props.zoom));

function onPointerDown(event) {
    event.preventDefault();
    emit('select');

    const startX = event.clientX;
    const startTimelineStart = props.clip.timelineStart;

    const onMove = (e) => {
        const dx = e.clientX - startX;
        const dt = dx / props.zoom;
        emit('move', Math.max(0, startTimelineStart + dt));
    };

    const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
}
</script>

<template>
    <div
        class="absolute top-2 bottom-2 rounded-sm cursor-grab active:cursor-grabbing overflow-hidden flex items-center px-1.5"
        :class="selected ? 'ring-2 ring-violet-500 bg-amber-500/30' : 'bg-amber-500/15 hover:bg-amber-500/20'"
        :style="{ left: `${left}px`, width: `${width}px` }"
        @pointerdown="onPointerDown"
    >
        <span class="text-[9px] text-amber-200/70 truncate pointer-events-none leading-tight">
            {{ clip.text || clip.label }}
        </span>
    </div>
</template>
