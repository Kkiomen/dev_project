<template>
    <div
        class="absolute top-1 bottom-1 rounded cursor-pointer group select-none"
        :class="[
            blockColorClass,
            { 'ring-2 ring-blue-400': isSelected },
            { 'pointer-events-none': track.locked },
        ]"
        :style="blockStyle"
        @mousedown.stop="handleMouseDown"
        @dblclick.stop="handleDoubleClick"
    >
        <!-- Trim handle left -->
        <div
            class="absolute left-0 top-0 bottom-0 w-1.5 cursor-ew-resize hover:bg-white/30 rounded-l z-10"
            @mousedown.stop="dragDrop.startElementDrag($event, element.id, 'trim-start')"
        />

        <!-- Content -->
        <div class="flex items-center h-full px-2 overflow-hidden">
            <!-- Type-specific content -->
            <NleVideoBlock v-if="element.type === 'video'" :element="element" :peaks="peaks" :sourceDuration="sourceDuration" :blockWidth="blockWidthPx" />
            <NleAudioBlock v-else-if="element.type === 'audio'" :element="element" :peaks="peaks" :sourceDuration="sourceDuration" :blockWidth="blockWidthPx" />
            <NleTextBlock v-else-if="element.type === 'text'" :element="element" />
            <NleImageBlock v-else-if="element.type === 'image'" :element="element" />

            <!-- Fallback name -->
            <span v-else class="text-[10px] text-white/70 truncate">{{ element.name }}</span>
        </div>

        <!-- Trim handle right -->
        <div
            class="absolute right-0 top-0 bottom-0 w-1.5 cursor-ew-resize hover:bg-white/30 rounded-r z-10"
            @mousedown.stop="dragDrop.startElementDrag($event, element.id, 'trim-end')"
        />

        <!-- Transition indicator -->
        <div
            v-if="element.transition"
            class="absolute -left-1 top-0 bottom-0 w-2 bg-yellow-400/30 border-l border-yellow-400"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleTimeline } from '@/composables/useNleTimeline';
import { useNleDragDrop } from '@/composables/useNleDragDrop';
import NleVideoBlock from './NleVideoBlock.vue';
import NleAudioBlock from './NleAudioBlock.vue';
import NleTextBlock from './NleTextBlock.vue';
import NleImageBlock from './NleImageBlock.vue';

const store = useVideoEditorStore();
const timeline = useNleTimeline();
const dragDrop = useNleDragDrop();

const props = defineProps({
    element: { type: Object, required: true },
    track: { type: Object, required: true },
});

const isSelected = computed(() => store.selectedElementIds.includes(props.element.id));

const peaks = computed(() => store.waveformCache[store.projectId] || []);
const sourceDuration = computed(() => store.project?.duration || 0);
const blockWidthPx = computed(() => timeline.timeToPixel(props.element.duration));

const blockStyle = computed(() => ({
    left: timeline.timeToPixel(props.element.time) + 'px',
    width: timeline.timeToPixel(props.element.duration) + 'px',
}));

const blockColorClass = computed(() => {
    switch (props.element.type) {
        case 'video': return 'bg-blue-700/60 hover:bg-blue-600/60';
        case 'audio': return 'bg-green-700/60 hover:bg-green-600/60';
        case 'text': return 'bg-purple-700/60 hover:bg-purple-600/60';
        case 'image': return 'bg-orange-700/60 hover:bg-orange-600/60';
        case 'shape': return 'bg-pink-700/60 hover:bg-pink-600/60';
        default: return 'bg-gray-700/60 hover:bg-gray-600/60';
    }
});

function handleMouseDown(event) {
    if (props.track.locked) return;

    if (event.shiftKey) {
        store.selectElementRange(props.element.id);
        // Start drag immediately so user can shift+click and drag in one motion
        dragDrop.startElementDrag(event, props.element.id, 'move');
        return;
    }

    if (event.ctrlKey || event.metaKey) {
        store.toggleElementSelection(props.element.id);
        // Start drag if element is now in selection (not if it was just deselected)
        if (store.selectedElementIds.includes(props.element.id)) {
            dragDrop.startElementDrag(event, props.element.id, 'move');
        }
        return;
    }

    // If clicking an already-selected element in a multi-select, keep selection for group drag
    if (!store.selectedElementIds.includes(props.element.id)) {
        store.selectElement(props.element.id);
    }

    dragDrop.startElementDrag(event, props.element.id, 'move');
}

function handleDoubleClick() {
    store.seekTo(props.element.time);
}
</script>
