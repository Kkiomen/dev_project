<template>
    <div class="relative w-full h-full overflow-hidden">
        <!-- Waveform canvas (background) -->
        <canvas
            ref="waveformCanvas"
            class="absolute inset-0 pointer-events-none"
        />
        <!-- Info bar (foreground) -->
        <div class="relative flex items-center gap-1.5 px-1 pt-0.5 z-10">
            <svg class="w-3 h-3 text-green-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
            </svg>
            <span class="text-[10px] text-white/80 truncate">{{ element.name }}</span>
            <span v-if="element.volume !== undefined && element.volume !== 1" class="text-[9px] text-green-300/60 shrink-0">
                {{ Math.round(element.volume * 100) }}%
            </span>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    element: { type: Object, required: true },
    peaks: { type: Array, default: () => [] },
    sourceDuration: { type: Number, default: 0 },
    blockWidth: { type: Number, default: 100 },
});

const waveformCanvas = ref(null);

function drawWaveform() {
    const canvas = waveformCanvas.value;
    if (!canvas) return;

    const w = Math.max(1, Math.round(props.blockWidth));
    const h = canvas.parentElement?.clientHeight || 32;

    canvas.width = w;
    canvas.height = h;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    if (!props.peaks.length) {
        drawPlaceholder(ctx, w, h);
        return;
    }

    // Slice peaks to trim_start -> trim_start + duration range
    let clipPeaks = props.peaks;
    if (props.sourceDuration > 0) {
        const trimStart = props.element.trim_start || 0;
        const trimEnd = trimStart + (props.element.duration || 0);
        const startIdx = Math.floor((trimStart / props.sourceDuration) * props.peaks.length);
        const endIdx = Math.ceil((trimEnd / props.sourceDuration) * props.peaks.length);
        clipPeaks = props.peaks.slice(startIdx, endIdx);
    }

    if (!clipPeaks.length) return;

    // Max-downsample: for each pixel column, take the max peak in its range
    const downsampledPeaks = downsample(clipPeaks, w);

    // Find max peak for dynamic normalization (like Premiere Pro)
    let maxPeak = 0;
    for (let i = 0; i < downsampledPeaks.length; i++) {
        if (downsampledPeaks[i] > maxPeak) maxPeak = downsampledPeaks[i];
    }
    if (maxPeak < 0.001) maxPeak = 1;

    const mid = h / 2;
    const usableHeight = mid * 0.9;

    // Draw filled waveform (mirrored)
    ctx.fillStyle = 'rgba(74, 222, 128, 0.3)';
    ctx.beginPath();
    ctx.moveTo(0, mid);
    for (let x = 0; x < w; x++) {
        const normalized = (downsampledPeaks[x] || 0) / maxPeak;
        const barH = normalized * usableHeight;
        ctx.lineTo(x, mid - barH);
    }
    ctx.lineTo(w - 1, mid);
    for (let x = w - 1; x >= 0; x--) {
        const normalized = (downsampledPeaks[x] || 0) / maxPeak;
        const barH = normalized * usableHeight;
        ctx.lineTo(x, mid + barH);
    }
    ctx.closePath();
    ctx.fill();

    // Draw waveform outline
    ctx.strokeStyle = 'rgba(74, 222, 128, 0.55)';
    ctx.lineWidth = 0.5;

    ctx.beginPath();
    for (let x = 0; x < w; x++) {
        const normalized = (downsampledPeaks[x] || 0) / maxPeak;
        const barH = normalized * usableHeight;
        if (x === 0) {
            ctx.moveTo(x, mid - barH);
        } else {
            ctx.lineTo(x, mid - barH);
        }
    }
    ctx.stroke();

    ctx.beginPath();
    for (let x = 0; x < w; x++) {
        const normalized = (downsampledPeaks[x] || 0) / maxPeak;
        const barH = normalized * usableHeight;
        if (x === 0) {
            ctx.moveTo(x, mid + barH);
        } else {
            ctx.lineTo(x, mid + barH);
        }
    }
    ctx.stroke();

    // Center line
    ctx.strokeStyle = 'rgba(74, 222, 128, 0.12)';
    ctx.lineWidth = 0.5;
    ctx.beginPath();
    ctx.moveTo(0, mid);
    ctx.lineTo(w, mid);
    ctx.stroke();
}

/**
 * Max-downsample: maps N peaks to W pixels, taking the max in each bucket.
 */
function downsample(peaks, targetWidth) {
    const result = new Float32Array(targetWidth);
    const ratio = peaks.length / targetWidth;

    for (let x = 0; x < targetWidth; x++) {
        const startIdx = Math.floor(x * ratio);
        const endIdx = Math.max(startIdx + 1, Math.floor((x + 1) * ratio));
        let max = 0;
        for (let i = startIdx; i < endIdx && i < peaks.length; i++) {
            const v = Math.abs(peaks[i] || 0);
            if (v > max) max = v;
        }
        result[x] = max;
    }

    return result;
}

function drawPlaceholder(ctx, w, h) {
    ctx.fillStyle = 'rgba(74, 222, 128, 0.1)';
    const mid = h / 2;
    for (let x = 0; x < w; x += 3) {
        const barH = 3 + Math.abs(Math.sin(x * 0.4)) * 5;
        ctx.fillRect(x, mid - barH, 2, barH * 2);
    }
}

watch(
    [() => props.peaks, () => props.blockWidth, () => props.sourceDuration],
    () => nextTick(drawWaveform)
);

onMounted(() => nextTick(drawWaveform));
</script>
