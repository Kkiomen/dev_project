<script setup>
import { useI18n } from 'vue-i18n';

const props = defineProps({
    zoom: { type: Number, required: true },
    snapEnabled: { type: Boolean, default: true },
    hasSelection: { type: Boolean, default: false },
});

const emit = defineEmits(['zoom', 'split', 'delete', 'toggle-snap']);
const { t } = useI18n();

const MIN_ZOOM = 10;
const MAX_ZOOM = 300;

function zoomIn() {
    emit('zoom', Math.min(props.zoom * 1.3, MAX_ZOOM));
}

function zoomOut() {
    emit('zoom', Math.max(props.zoom / 1.3, MIN_ZOOM));
}

function handleZoomSlider(event) {
    emit('zoom', Number(event.target.value));
}
</script>

<template>
    <div class="h-8 px-3 flex items-center gap-3 bg-gray-900 border-b border-gray-800 shrink-0">
        <!-- Zoom controls -->
        <div class="flex items-center gap-1.5">
            <button
                @click="zoomOut"
                class="p-0.5 text-gray-400 hover:text-white transition"
                :title="t('videoEditor.timeline.zoomOut')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607zM13.5 10.5h-6" />
                </svg>
            </button>
            <input
                type="range"
                :min="MIN_ZOOM"
                :max="MAX_ZOOM"
                :value="zoom"
                @input="handleZoomSlider"
                class="w-20 h-1 bg-gray-700 rounded-full appearance-none cursor-pointer accent-violet-500"
            />
            <button
                @click="zoomIn"
                class="p-0.5 text-gray-400 hover:text-white transition"
                :title="t('videoEditor.timeline.zoomIn')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607zM10.5 7.5v6m3-3h-6" />
                </svg>
            </button>
        </div>

        <div class="w-px h-4 bg-gray-700" />

        <!-- Split -->
        <button
            @click="emit('split')"
            :disabled="!hasSelection"
            class="flex items-center gap-1 px-2 py-0.5 text-[11px] text-gray-400 hover:text-white rounded transition disabled:opacity-30 disabled:cursor-not-allowed"
            :title="t('videoEditor.timeline.split')"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m7.848 8.25 1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 0 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.33 4.33 0 0 0 10.607 12m3.736 0 7.794 4.5-.802.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664" />
            </svg>
            {{ t('videoEditor.timeline.split') }}
        </button>

        <!-- Delete -->
        <button
            @click="emit('delete')"
            :disabled="!hasSelection"
            class="flex items-center gap-1 px-2 py-0.5 text-[11px] text-gray-400 hover:text-red-400 rounded transition disabled:opacity-30 disabled:cursor-not-allowed"
            :title="t('videoEditor.timeline.delete')"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
            </svg>
            {{ t('videoEditor.timeline.delete') }}
        </button>

        <div class="w-px h-4 bg-gray-700" />

        <!-- Snap toggle -->
        <button
            @click="emit('toggle-snap')"
            class="flex items-center gap-1 px-2 py-0.5 text-[11px] rounded transition"
            :class="snapEnabled ? 'text-violet-400' : 'text-gray-500'"
            :title="t('videoEditor.timeline.snap')"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
            </svg>
            {{ t('videoEditor.timeline.snap') }}
        </button>
    </div>
</template>
