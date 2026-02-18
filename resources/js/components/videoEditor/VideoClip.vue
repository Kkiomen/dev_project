<script setup>
import { computed } from 'vue';

const props = defineProps({
    clip: { type: Object, required: true },
    zoom: { type: Number, required: true },
    selected: { type: Boolean, default: false },
    thumbnails: { type: Array, default: () => [] },
});

const emit = defineEmits(['select', 'move', 'trim']);

const clipDuration = computed(() => props.clip.sourceOut - props.clip.sourceIn);
const left = computed(() => props.clip.timelineStart * props.zoom);
const width = computed(() => Math.max(4, clipDuration.value * props.zoom));

// Drag to move — only timelineStart changes
function onMovePointerDown(event) {
    if (event.target.closest('.trim-handle')) return;
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

// Trim left handle — adjusts sourceIn + timelineStart
function onTrimLeftDown(event) {
    event.preventDefault();
    event.stopPropagation();

    const startX = event.clientX;
    const startEdge = props.clip.timelineStart;

    const onMove = (e) => {
        const dx = e.clientX - startX;
        const dt = dx / props.zoom;
        emit('trim', 'left', startEdge + dt);
    };

    const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
}

// Trim right handle — adjusts sourceOut
function onTrimRightDown(event) {
    event.preventDefault();
    event.stopPropagation();

    const startX = event.clientX;
    const startEdge = props.clip.timelineStart + clipDuration.value;

    const onMove = (e) => {
        const dx = e.clientX - startX;
        const dt = dx / props.zoom;
        emit('trim', 'right', startEdge + dt);
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
        class="absolute top-1 bottom-1 rounded cursor-grab active:cursor-grabbing overflow-hidden group"
        :class="selected ? 'ring-2 ring-violet-500 bg-blue-600/30' : 'bg-blue-600/20 hover:bg-blue-600/25'"
        :style="{ left: `${left}px`, width: `${width}px` }"
        @pointerdown="onMovePointerDown"
    >
        <!-- Trim handle left -->
        <div
            class="trim-handle absolute left-0 top-0 bottom-0 w-2 cursor-col-resize bg-blue-400/40 opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-blue-400/70"
            @pointerdown="onTrimLeftDown"
        />

        <!-- Content: thumbnail strip or label -->
        <div class="h-full flex items-center px-3 pointer-events-none">
            <div v-if="thumbnails.length" class="flex h-full gap-px overflow-hidden">
                <img
                    v-for="(thumb, i) in thumbnails"
                    :key="i"
                    :src="thumb"
                    class="h-full w-auto object-cover opacity-60"
                />
            </div>
            <span v-else class="text-[10px] text-blue-300/70 font-medium truncate">
                {{ clip.label }}
            </span>
        </div>

        <!-- Trim handle right -->
        <div
            class="trim-handle absolute right-0 top-0 bottom-0 w-2 cursor-col-resize bg-blue-400/40 opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-blue-400/70"
            @pointerdown="onTrimRightDown"
        />
    </div>
</template>
