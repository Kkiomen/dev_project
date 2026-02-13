<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useVideoManagerStore } from '@/stores/videoManager';
import { useToast } from '@/composables/useToast';
import UploadQueue from '@/components/videoManager/UploadQueue.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const videoManagerStore = useVideoManagerStore();
const toast = useToast();

const isDragging = ref(false);

const hasQueue = computed(() => videoManagerStore.uploadQueue.length > 0);
const hasPending = computed(() => videoManagerStore.uploadQueue.some(item => item.status === 'pending'));
const allDone = computed(() => videoManagerStore.uploadQueue.length > 0 && videoManagerStore.uploadQueue.every(item => item.status === 'completed' || item.status === 'failed'));

const acceptedTypes = 'video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska';

const handleDrop = (e) => {
    isDragging.value = false;
    const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('video/'));
    if (files.length > 0) {
        videoManagerStore.addToUploadQueue(files);
    }
};

const handleFileInput = (e) => {
    const files = Array.from(e.target.files);
    if (files.length > 0) {
        videoManagerStore.addToUploadQueue(files);
    }
    e.target.value = '';
};

const updateTitle = (id, title) => {
    const item = videoManagerStore.uploadQueue.find(i => i.id === id);
    if (item) item.title = title;
};

const updateLanguage = (id, language) => {
    const item = videoManagerStore.uploadQueue.find(i => i.id === id);
    if (item) item.language = language;
};

const updateStyle = (id, style) => {
    const item = videoManagerStore.uploadQueue.find(i => i.id === id);
    if (item) item.captionStyle = style;
};

const startUpload = async () => {
    const brandId = brandsStore.currentBrand?.id || null;
    await videoManagerStore.processUploadQueue(brandId);
    toast.success(t('videoManager.upload.completed'));
};

const cancelAll = () => {
    videoManagerStore.clearUploadQueue();
};

const goToLibrary = () => {
    videoManagerStore.clearUploadQueue();
    router.push({ name: 'videoManager.library' });
};
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-xl font-bold text-white">{{ t('videoManager.upload.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('videoManager.upload.subtitle') }}</p>
        </div>

        <!-- Drop zone -->
        <div
            @dragenter.prevent="isDragging = true"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            :class="[
                'border-2 border-dashed rounded-xl transition-colors cursor-pointer',
                isDragging
                    ? 'border-violet-500 bg-violet-600/10'
                    : 'border-gray-700 hover:border-gray-600 bg-gray-900/50',
                hasQueue ? 'p-6' : 'p-12',
            ]"
            @click="$refs.fileInput.click()"
        >
            <input
                ref="fileInput"
                type="file"
                :accept="acceptedTypes"
                multiple
                class="hidden"
                @change="handleFileInput"
            />

            <div class="text-center">
                <svg class="mx-auto w-12 h-12 text-gray-600" :class="isDragging && 'text-violet-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <p class="mt-3 text-sm text-gray-300">{{ t('videoManager.upload.dropHere') }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ t('videoManager.upload.orClick') }}</p>
                <p class="mt-1 text-xs text-gray-600">MP4, MOV, AVI, WebM, MKV - {{ t('videoManager.upload.maxSize') }}</p>
            </div>
        </div>

        <!-- Upload Queue -->
        <UploadQueue
            :queue="videoManagerStore.uploadQueue"
            @remove="videoManagerStore.removeFromUploadQueue"
            @update-title="updateTitle"
            @update-language="updateLanguage"
            @update-style="updateStyle"
        />

        <!-- Actions -->
        <div v-if="hasQueue" class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                {{ t('videoManager.upload.filesInQueue', { count: videoManagerStore.uploadQueue.length }) }}
            </p>
            <div class="flex gap-3">
                <button
                    v-if="allDone"
                    @click="goToLibrary"
                    class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    {{ t('videoManager.upload.goToLibrary') }}
                </button>
                <template v-else>
                    <button
                        @click="cancelAll"
                        :disabled="videoManagerStore.uploading"
                        class="px-4 py-2 border border-gray-700 text-gray-300 hover:text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                    >
                        {{ t('videoManager.upload.cancelAll') }}
                    </button>
                    <button
                        @click="startUpload"
                        :disabled="!hasPending || videoManagerStore.uploading"
                        class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                    >
                        <span v-if="videoManagerStore.uploading" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            {{ t('videoManager.upload.uploading') }}
                        </span>
                        <span v-else>{{ t('videoManager.upload.uploadAll') }}</span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
