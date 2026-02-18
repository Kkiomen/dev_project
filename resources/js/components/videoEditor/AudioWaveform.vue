<script setup>
import { computed, ref, onMounted, watch, nextTick } from 'vue';

const TRACK_INNER_HEIGHT = 64; // 72px track - 4px top - 4px bottom

const props = defineProps({
    clip: { type: Object, required: true },
    zoom: { type: Number, required: true },
    peaks: { type: Array, default: () => [] },
    selected: { type: Boolean, default: false },
    sourceDuration: { type: Number, default: 0 },
});

const emit = defineEmits(['select', 'move', 'trim']);

const canvasRef = ref(null);

const clipDuration = computed(() => props.clip.sourceOut - props.clip.sourceIn);
const left = computed(() => props.clip.timelineStart * props.zoom);
const width = computed(() => Math.max(4, clipDuration.value * props.zoom));

function drawWaveform() {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const w = Math.round(width.value);
    const h = TRACK_INNER_HEIGHT;

    canvas.width = w;
    canvas.height = h;

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    if (!props.peaks.length) {
        // Draw deterministic placeholder bars when no peaks loaded
        ctx.fillStyle = 'rgba(74, 222, 128, 0.15)';
        const mid = h / 2;
        for (let x = 0; x < w; x += 3) {
            const barH = 4 + Math.abs(Math.sin(x * 0.4)) * 8;
            ctx.fillRect(x, mid - barH, 2, barH * 2);
        }
        return;
    }

    // Slice peaks to the sourceIn→sourceOut portion of the source file
    let clipPeaks = props.peaks;
    if (props.sourceDuration > 0) {
        const startIdx = Math.floor((props.clip.sourceIn / props.sourceDuration) * props.peaks.length);
        const endIdx = Math.ceil((props.clip.sourceOut / props.sourceDuration) * props.peaks.length);
        clipPeaks = props.peaks.slice(startIdx, endIdx);
    }

    const mid = h / 2;
    const peaksPerPixel = clipPeaks.length / w;
    ctx.fillStyle = props.selected ? 'rgba(74, 222, 128, 0.7)' : 'rgba(74, 222, 128, 0.4)';

    for (let x = 0; x < w; x++) {
        const idx = Math.floor(x * peaksPerPixel);
        const peak = Math.abs(clipPeaks[idx] || 0);
        const barH = peak * mid * 0.85;
        ctx.fillRect(x, mid - barH, 1, barH * 2);
    }
}

watch([() => props.peaks, () => props.zoom, () => width.value, () => props.selected, () => props.sourceDuration], () => {
    nextTick(drawWaveform);
});

onMounted(() => {
    nextTick(drawWaveform);
});

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
        :class="selected ? 'ring-2 ring-violet-500 bg-green-600/20' : 'bg-green-600/10 hover:bg-green-600/15'"
        :style="{ left: `${left}px`, width: `${width}px` }"
        @pointerdown="onMovePointerDown"
    >
        <!-- Trim handle left -->
        <div
            class="trim-handle absolute left-0 top-0 bottom-0 w-2 cursor-col-resize bg-green-400/40 opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-green-400/70"
            @pointerdown="onTrimLeftDown"
        />

        <!-- Waveform canvas — sized via pixel attributes, displayed at container size -->
        <canvas
            ref="canvasRef"
            class="pointer-events-none block"
            :style="{ width: `${width}px`, height: `${TRACK_INNER_HEIGHT}px` }"
        />

        <!-- Trim handle right -->
        <div
            class="trim-handle absolute right-0 top-0 bottom-0 w-2 cursor-col-resize bg-green-400/40 opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-green-400/70"
            @pointerdown="onTrimRightDown"
        />
    </div>
</template>
