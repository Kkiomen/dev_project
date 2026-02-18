<script setup>
import { computed } from 'vue';

const props = defineProps({
    time: { type: Number, required: true },
    zoom: { type: Number, required: true },
    height: { type: Number, default: 200 },
});

const emit = defineEmits(['seek']);

const xPos = computed(() => props.time * props.zoom);

function onPointerDown(event) {
    event.preventDefault();
    const startX = event.clientX;
    const startTime = props.time;

    const onMove = (e) => {
        const dx = e.clientX - startX;
        const dt = dx / props.zoom;
        emit('seek', Math.max(0, startTime + dt));
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
        class="absolute top-0 z-30 pointer-events-none"
        :style="{ left: `${xPos}px`, height: `${height}px` }"
    >
        <!-- Head (triangle) — interactive -->
        <div
            class="pointer-events-auto cursor-col-resize absolute -top-0 -left-[5px] w-[11px] h-4 flex items-start justify-center"
            @pointerdown="onPointerDown"
        >
            <svg width="11" height="14" viewBox="0 0 11 14" class="text-red-500">
                <polygon points="0,0 11,0 11,8 5.5,14 0,8" fill="currentColor" />
            </svg>
        </div>
        <!-- Line -->
        <div class="w-px h-full bg-red-500 mx-auto" />
    </div>
</template>
