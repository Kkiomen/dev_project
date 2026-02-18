<template>
    <div class="flex flex-col h-full">
        <!-- Tabs -->
        <div class="flex border-b border-gray-700">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                @click="activeTab = tab.value"
                class="flex-1 px-3 py-2 text-xs font-medium transition-colors"
                :class="activeTab === tab.value
                    ? 'text-blue-400 border-b-2 border-blue-400'
                    : 'text-gray-500 hover:text-gray-300'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Upload Zone -->
        <NleMediaUploader @upload="handleUpload" class="mx-3 mt-3" />

        <!-- Media Grid -->
        <div class="flex-1 overflow-y-auto p-3">
            <NleMediaGrid
                :items="filteredItems"
                :type="activeTab"
                @drag-start="handleDragStart"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import NleMediaUploader from './NleMediaUploader.vue';
import NleMediaGrid from './NleMediaGrid.vue';

const { t } = useI18n();

const props = defineProps({
    mediaItems: { type: Array, default: () => [] },
});

const emit = defineEmits(['upload', 'drag-start']);

const activeTab = ref('all');

const tabs = computed(() => [
    { value: 'all', label: t('nle.media.all') },
    { value: 'video', label: t('nle.media.videos') },
    { value: 'image', label: t('nle.media.images') },
    { value: 'audio', label: t('nle.media.audio') },
]);

const filteredItems = computed(() => {
    if (activeTab.value === 'all') return props.mediaItems;
    return props.mediaItems.filter((item) => item.type === activeTab.value);
});

function handleUpload(files) {
    emit('upload', files);
}

function handleDragStart(event, item) {
    emit('drag-start', event, item);
}
</script>
