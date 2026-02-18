<template>
    <div
        @dragover.prevent="isDraggingOver = true"
        @dragleave="isDraggingOver = false"
        @drop.prevent="handleDrop"
        @click="triggerFileInput"
        class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-colors"
        :class="isDraggingOver
            ? 'border-blue-400 bg-blue-900/20'
            : 'border-gray-600 hover:border-gray-500 bg-gray-800/50'"
    >
        <svg class="w-6 h-6 mx-auto text-gray-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p class="text-xs text-gray-400">{{ t('nle.media.upload') }}</p>
        <input
            ref="fileInput"
            type="file"
            multiple
            accept="video/*,image/*,audio/*"
            class="hidden"
            @change="handleFileSelect"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const emit = defineEmits(['upload']);

const fileInput = ref(null);
const isDraggingOver = ref(false);

function triggerFileInput() {
    fileInput.value?.click();
}

function handleFileSelect(event) {
    const files = Array.from(event.target.files || []);
    if (files.length) emit('upload', files);
    event.target.value = '';
}

function handleDrop(event) {
    isDraggingOver.value = false;
    const files = Array.from(event.dataTransfer?.files || []);
    if (files.length) emit('upload', files);
}
</script>
