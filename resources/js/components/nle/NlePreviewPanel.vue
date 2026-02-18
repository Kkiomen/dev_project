<template>
    <div class="flex flex-col h-full">
        <!-- Canvas Area -->
        <div class="flex-1 flex items-center justify-center p-4 overflow-hidden">
            <NleCanvas ref="canvasRef" />
        </div>

        <!-- Playback Controls -->
        <NlePlaybackControls />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import NleCanvas from './NleCanvas.vue';
import NlePlaybackControls from './NlePlaybackControls.vue';

const canvasRef = ref(null);

defineExpose({ canvasRef });
</script>
