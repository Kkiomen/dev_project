<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoProjectsStore } from '@/stores/videoProjects';
import { useBrandsStore } from '@/stores/brands';
import Modal from '@/components/common/Modal.vue';

const emit = defineEmits(['close', 'uploaded']);

const { t } = useI18n();
const videoStore = useVideoProjectsStore();
const brandsStore = useBrandsStore();

const file = ref(null);
const title = ref('');
const language = ref('');
const captionStyle = ref('clean');
const dragOver = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);

const supportedFormats = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska'];

function onFileSelect(e) {
    const selected = e.target.files?.[0];
    if (selected && supportedFormats.includes(selected.type)) {
        file.value = selected;
        if (!title.value) {
            title.value = selected.name.replace(/\.[^/.]+$/, '');
        }
    }
}

function onDrop(e) {
    dragOver.value = false;
    const dropped = e.dataTransfer?.files?.[0];
    if (dropped && supportedFormats.includes(dropped.type)) {
        file.value = dropped;
        if (!title.value) {
            title.value = dropped.name.replace(/\.[^/.]+$/, '');
        }
    }
}

async function upload() {
    if (!file.value) return;

    uploading.value = true;
    const formData = new FormData();
    formData.append('video', file.value);
    formData.append('title', title.value || file.value.name);
    formData.append('caption_style', captionStyle.value);
    if (language.value) formData.append('language', language.value);
    if (brandsStore.currentBrand?.id) formData.append('brand_id', brandsStore.currentBrand.id);

    try {
        const project = await videoStore.uploadVideo(formData);
        emit('uploaded', project);
    } catch (error) {
        // Error handled by store
    } finally {
        uploading.value = false;
    }
}

function formatSize(bytes) {
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <Modal @close="$emit('close')">
        <template #header>
            <h2 class="text-lg font-semibold">{{ t('videoProjects.uploadModal.title') }}</h2>
        </template>

        <template #default>
            <div class="space-y-4">
                <!-- Drop Zone -->
                <div
                    @dragover.prevent="dragOver = true"
                    @dragleave="dragOver = false"
                    @drop.prevent="onDrop"
                    :class="dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                    class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
                >
                    <div v-if="!file">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p class="text-sm text-gray-600 mb-2">{{ t('videoProjects.uploadModal.dropHere') }}</p>
                        <label class="inline-block px-4 py-2 bg-blue-600 text-white text-sm rounded-lg cursor-pointer hover:bg-blue-700 transition-colors">
                            {{ t('videoProjects.uploadModal.browse') }}
                            <input type="file" accept="video/*" class="hidden" @change="onFileSelect" />
                        </label>
                        <p class="text-xs text-gray-400 mt-2">MP4, MOV, AVI, WebM, MKV ({{ t('videoProjects.uploadModal.maxSize') }})</p>
                    </div>
                    <div v-else class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <div class="flex-1 text-left">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ file.name }}</p>
                            <p class="text-xs text-gray-500">{{ formatSize(file.size) }}</p>
                        </div>
                        <button @click="file = null" class="text-gray-400 hover:text-red-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('videoProjects.uploadModal.videoTitle') }}</label>
                    <input
                        v-model="title"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :placeholder="t('videoProjects.uploadModal.titlePlaceholder')"
                    />
                </div>

                <!-- Language -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('videoProjects.uploadModal.language') }}</label>
                    <select
                        v-model="language"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">{{ t('videoProjects.uploadModal.autoDetect') }}</option>
                        <option value="pl">Polski</option>
                        <option value="en">English</option>
                        <option value="de">Deutsch</option>
                        <option value="fr">Français</option>
                        <option value="es">Español</option>
                    </select>
                </div>

                <!-- Caption Style -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('videoProjects.uploadModal.captionStyle') }}</label>
                    <div class="grid grid-cols-5 gap-2">
                        <button
                            v-for="style in ['clean', 'hormozi', 'mrbeast', 'bold', 'neon']"
                            :key="style"
                            @click="captionStyle = style"
                            :class="captionStyle === style ? 'ring-2 ring-blue-500 border-blue-500' : 'border-gray-200'"
                            class="p-2 border rounded-lg text-center text-xs font-medium capitalize transition-all"
                        >
                            {{ style }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button
                    @click="$emit('close')"
                    class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    {{ t('videoProjects.uploadModal.cancel') }}
                </button>
                <button
                    @click="upload"
                    :disabled="!file || uploading"
                    class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <div v-if="uploading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                    {{ uploading ? t('videoProjects.uploadModal.uploading') : t('videoProjects.uploadModal.upload') }}
                </button>
            </div>
        </template>
    </Modal>
</template>
