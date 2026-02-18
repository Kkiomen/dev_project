<template>
    <div class="flex flex-col bg-gray-900 border-t border-gray-700" :style="{ height: panelHeight + 'px' }">
        <!-- Resize Handle -->
        <div
            @mousedown="startResize"
            class="h-1 bg-gray-700 hover:bg-blue-500 cursor-ns-resize shrink-0"
        />

        <!-- Timeline Toolbar -->
        <NleTimelineToolbar />

        <!-- Timeline Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Track Labels (fixed left) -->
            <div class="w-40 shrink-0 border-r border-gray-700 overflow-y-auto">
                <!-- Ruler spacer -->
                <div class="h-6 border-b border-gray-700" />
                <!-- Track labels -->
                <NleTrackLabel
                    v-for="track in store.tracks"
                    :key="track.id"
                    :track="track"
                />
            </div>

            <!-- Scrollable Timeline Area -->
            <div
                ref="timelineScrollRef"
                class="flex-1 overflow-auto relative"
                @scroll="handleScroll"
            >
                <!-- Time Ruler -->
                <NleTimeRuler
                    :scroll-left="scrollLeft"
                    :style="{ width: store.timelineWidth + 'px' }"
                />

                <!-- Tracks -->
                <div class="relative" :style="{ width: store.timelineWidth + 'px' }">
                    <!-- Playhead -->
                    <NlePlayhead />

                    <!-- Track Rows -->
                    <NleTrackRow
                        v-for="track in store.tracks"
                        :key="track.id"
                        :track="track"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import NleTimelineToolbar from './NleTimelineToolbar.vue';
import NleTimeRuler from './NleTimeRuler.vue';
import NlePlayhead from './NlePlayhead.vue';
import NleTrackRow from './NleTrackRow.vue';
import NleTrackLabel from './NleTrackLabel.vue';

const store = useVideoEditorStore();

const panelHeight = ref(250);
const scrollLeft = ref(0);
const timelineScrollRef = ref(null);

function handleScroll() {
    if (timelineScrollRef.value) {
        scrollLeft.value = timelineScrollRef.value.scrollLeft;
    }
}

let resizeStartY = 0;
let resizeStartHeight = 0;

function startResize(event) {
    resizeStartY = event.clientY;
    resizeStartHeight = panelHeight.value;

    const onMove = (e) => {
        const delta = resizeStartY - e.clientY;
        panelHeight.value = Math.max(150, Math.min(600, resizeStartHeight + delta));
    };

    const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}
</script>
