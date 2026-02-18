<template>
    <div class="relative overflow-visible" :style="containerStyle">
        <canvas
            ref="canvasEl"
            :width="store.compositionWidth"
            :height="store.compositionHeight"
            class="w-full h-full bg-black rounded shadow-lg"
            :style="canvasStyle"
        />

        <!-- Interaction Overlay (extends 12px beyond canvas for edge handle clicks) -->
        <div
            ref="overlayEl"
            class="absolute z-10"
            :style="{ inset: '-12px', cursor: canvasInteraction.cursorStyle.value }"
            @mousedown="handleOverlayMouseDown"
            @mousemove="handleOverlayMouseMove"
        >
            <!-- Inner positioning layer — matches canvas exactly so % = composition space -->
            <div class="absolute" style="inset: 12px; overflow: visible;">
                <template v-for="overlay in selectionOverlays" :key="overlay.id">
                    <!-- Selection border -->
                    <div
                        class="absolute border-2 border-blue-400 pointer-events-none"
                        :style="overlayStyle(overlay)"
                    />

                    <!-- Corner handles -->
                    <div
                        v-for="corner in ['nw', 'ne', 'sw', 'se']"
                        :key="corner"
                        class="absolute w-2 h-2 bg-white border border-blue-500 rounded-sm pointer-events-none"
                        :style="handleStyle(overlay, corner)"
                    />
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNlePlayback } from '@/composables/useNlePlayback';
import { useNleCanvasInteraction } from '@/composables/useNleCanvasInteraction';

const store = useVideoEditorStore();
const canvasEl = ref(null);
const overlayEl = ref(null);

const { renderFrame, canvasWidth, canvasHeight } = useNlePlayback(canvasEl);
const canvasInteraction = useNleCanvasInteraction();

const containerStyle = computed(() => {
    const aspect = canvasWidth.value / canvasHeight.value;
    if (aspect >= 1) {
        return { width: '100%', maxWidth: '720px', aspectRatio: `${aspect}` };
    }
    return { height: '100%', maxHeight: '500px', aspectRatio: `${aspect}` };
});

const canvasStyle = computed(() => ({
    imageRendering: 'auto',
}));

const selectionOverlays = computed(() => {
    // Trigger reactivity on relevant state
    void store.selectedElementIds;
    void store.playhead;
    void store.composition;
    return canvasInteraction.getSelectionOverlays();
});

/**
 * Convert composition-space coordinates to percentage positions
 * relative to the inner positioning div (which matches canvas size).
 */
function overlayStyle(overlay) {
    const w = store.compositionWidth;
    const h = store.compositionHeight;
    return {
        left: (overlay.x / w * 100) + '%',
        top: (overlay.y / h * 100) + '%',
        width: (overlay.width / w * 100) + '%',
        height: (overlay.height / h * 100) + '%',
    };
}

function handleStyle(overlay, corner) {
    const w = store.compositionWidth;
    const h = store.compositionHeight;

    let left, top;
    if (corner.includes('w')) {
        left = (overlay.x / w * 100) + '%';
    } else {
        left = ((overlay.x + overlay.width) / w * 100) + '%';
    }
    if (corner.includes('n')) {
        top = (overlay.y / h * 100) + '%';
    } else {
        top = ((overlay.y + overlay.height) / h * 100) + '%';
    }

    return {
        left,
        top,
        transform: 'translate(-50%, -50%)',
    };
}

function handleOverlayMouseDown(event) {
    if (!canvasEl.value) return;
    canvasInteraction.onCanvasMouseDown(event, canvasEl.value);
}

function handleOverlayMouseMove(event) {
    if (!canvasEl.value) return;
    canvasInteraction.onCanvasMouseMove(event, canvasEl.value);
}

onMounted(() => {
    if (store.composition) {
        renderFrame(store.playhead);
    }
});

watch(() => store.composition, () => {
    if (store.composition && !store.isPlaying) {
        renderFrame(store.playhead);
    }
}, { deep: true });

defineExpose({ canvasEl });
</script>
