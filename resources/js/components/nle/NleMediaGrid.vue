<template>
    <div class="grid grid-cols-2 gap-2">
        <div
            v-for="item in items"
            :key="item.id"
            draggable="true"
            @dragstart="handleDragStart($event, item)"
            class="relative rounded-lg overflow-hidden bg-gray-800 cursor-grab hover:ring-2 hover:ring-blue-500 transition-all group"
        >
            <!-- Thumbnail -->
            <div class="aspect-video bg-gray-700 flex items-center justify-center">
                <img
                    v-if="item.thumbnail"
                    :src="item.thumbnail"
                    :alt="item.name"
                    class="w-full h-full object-cover"
                />
                <svg v-else-if="item.type === 'video'" class="w-8 h-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <svg v-else-if="item.type === 'audio'" class="w-8 h-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM21 16c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                </svg>
                <svg v-else class="w-8 h-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>

                <!-- Duration badge -->
                <span
                    v-if="item.duration"
                    class="absolute bottom-1 right-1 text-[10px] bg-black/70 text-white px-1 rounded"
                >
                    {{ formatDuration(item.duration) }}
                </span>
            </div>

            <!-- Name -->
            <div class="px-2 py-1.5">
                <p class="text-[11px] text-gray-300 truncate">{{ item.name }}</p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!items.length" class="col-span-2 text-center py-8">
            <p class="text-xs text-gray-500">{{ t('nle.media.empty') }}</p>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    items: { type: Array, default: () => [] },
    type: { type: String, default: 'all' },
});

const emit = defineEmits(['drag-start']);

function handleDragStart(event, item) {
    event.dataTransfer.setData(
        'application/nle-media',
        JSON.stringify({
            type: item.type,
            name: item.name,
            source: item.source,
            duration: item.duration || 5,
        })
    );
    event.dataTransfer.effectAllowed = 'copy';
    emit('drag-start', event, item);
}

function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>
