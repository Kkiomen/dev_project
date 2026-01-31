<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'imported']);

const { t } = useI18n();

const file = ref(null);
const templateName = ref('');
const addToLibrary = ref(true);
const uploading = ref(false);
const uploadProgress = ref(0);
const error = ref(null);
const isDragging = ref(false);
const analyzing = ref(false);
const analysis = ref(null);

const canUpload = computed(() => {
    return file.value && !uploading.value && !analyzing.value;
});

const handleFileSelect = (event) => {
    const selectedFile = event.target.files?.[0];
    if (selectedFile) {
        validateAndSetFile(selectedFile);
    }
};

const handleDrop = (event) => {
    isDragging.value = false;
    const droppedFile = event.dataTransfer?.files?.[0];
    if (droppedFile) {
        validateAndSetFile(droppedFile);
    }
};

const validateAndSetFile = async (selectedFile) => {
    error.value = null;
    analysis.value = null;

    // Check file extension
    const extension = selectedFile.name.split('.').pop()?.toLowerCase();
    if (extension !== 'psd') {
        error.value = t('graphics.psd.errors.invalidFormat');
        return;
    }

    // Check file size (100MB)
    if (selectedFile.size > 100 * 1024 * 1024) {
        error.value = t('graphics.psd.errors.fileTooLarge');
        return;
    }

    file.value = selectedFile;

    // Auto-generate template name from filename if not set
    if (!templateName.value) {
        const baseName = selectedFile.name.replace(/\.psd$/i, '');
        templateName.value = baseName.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    // Analyze PSD structure
    await analyzeFile(selectedFile);
};

const analyzeFile = async (selectedFile) => {
    analyzing.value = true;

    const formData = new FormData();
    formData.append('file', selectedFile);

    try {
        const response = await axios.post('/api/v1/psd/analyze', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        analysis.value = response.data;
    } catch (err) {
        console.error('PSD analysis failed:', err);
        // Don't show error - analysis is optional, import can still proceed
    } finally {
        analyzing.value = false;
    }
};

const handleDragOver = (event) => {
    isDragging.value = true;
};

const handleDragLeave = () => {
    isDragging.value = false;
};

const clearFile = () => {
    file.value = null;
    templateName.value = '';
    error.value = null;
    analysis.value = null;
};

const handleUpload = async () => {
    if (!file.value) return;

    uploading.value = true;
    uploadProgress.value = 0;
    error.value = null;

    const formData = new FormData();
    formData.append('file', file.value);
    if (templateName.value) {
        formData.append('name', templateName.value);
    }
    formData.append('add_to_library', addToLibrary.value ? '1' : '0');

    try {
        const response = await axios.post('/api/v1/psd/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                }
            },
        });

        emit('imported', response.data.data);
        handleClose();
    } catch (err) {
        console.error('PSD upload failed:', err);
        error.value = err.response?.data?.message || err.response?.data?.error || t('graphics.psd.errors.uploadFailed');
    } finally {
        uploading.value = false;
    }
};

const handleClose = () => {
    if (!uploading.value && !analyzing.value) {
        file.value = null;
        templateName.value = '';
        addToLibrary.value = true;
        uploadProgress.value = 0;
        error.value = null;
        analysis.value = null;
        emit('close');
    }
};

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="handleClose"
        >
            <div class="flex min-h-full items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="handleClose"></div>

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ t('graphics.psd.uploadTitle') }}
                        </h2>
                        <button
                            @click="handleClose"
                            :disabled="uploading"
                            class="text-gray-400 hover:text-gray-600 disabled:opacity-50"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <!-- Dropzone -->
                        <div
                            v-if="!file"
                            @drop.prevent="handleDrop"
                            @dragover.prevent="handleDragOver"
                            @dragleave="handleDragLeave"
                            :class="[
                                'border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer',
                                isDragging ? 'border-purple-500 bg-purple-50' : 'border-gray-300 hover:border-gray-400'
                            ]"
                            @click="$refs.fileInput.click()"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".psd"
                                class="hidden"
                                @change="handleFileSelect"
                            />

                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>

                            <p class="text-gray-700 font-medium mb-1">
                                {{ t('graphics.psd.selectFile') }}
                            </p>
                            <p class="text-gray-500 text-sm">
                                {{ t('graphics.psd.orDragDrop') }}
                            </p>
                            <p class="text-gray-400 text-xs mt-2">
                                {{ t('graphics.psd.maxSize') }}
                            </p>
                        </div>

                        <!-- Selected file -->
                        <div v-else class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-gray-900 font-medium text-sm truncate max-w-[200px]">
                                            {{ file.name }}
                                        </p>
                                        <p class="text-gray-500 text-xs">
                                            {{ formatFileSize(file.size) }}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    v-if="!uploading"
                                    @click="clearFile"
                                    class="text-gray-400 hover:text-red-600"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Progress bar -->
                            <div v-if="uploading" class="mt-3">
                                <div class="flex justify-between text-xs text-gray-600 mb-1">
                                    <span>{{ t('graphics.psd.processing') }}</span>
                                    <span>{{ uploadProgress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        class="bg-purple-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: uploadProgress + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Analyzing indicator -->
                            <div v-if="analyzing" class="mt-3 flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ t('graphics.psd.analyzing') }}</span>
                            </div>

                            <!-- Analysis results -->
                            <div v-if="analysis && !analyzing" class="mt-3 p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">{{ t('graphics.psd.dimensions') }}:</span>
                                    <span class="font-medium text-gray-900">{{ analysis.width }} x {{ analysis.height }}px</span>
                                </div>
                                <div class="flex items-center justify-between text-sm mt-1">
                                    <span class="text-gray-600">{{ t('graphics.psd.layersToImport') }}:</span>
                                    <span class="font-medium text-gray-900">
                                        {{ analysis.visible_layers }}
                                        <span class="text-gray-400 font-normal">/ {{ analysis.total_layers }}</span>
                                    </span>
                                </div>
                                <p v-if="analysis.visible_layers !== analysis.total_layers" class="text-xs text-gray-500 mt-2">
                                    {{ t('graphics.psd.hiddenLayersInfo') }}
                                </p>
                            </div>
                        </div>

                        <!-- Template name input -->
                        <div v-if="file" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('common.name') }}
                            </label>
                            <input
                                v-model="templateName"
                                type="text"
                                :disabled="uploading"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 disabled:bg-gray-50"
                                :placeholder="t('graphics.templates.namePlaceholder')"
                            />
                        </div>

                        <!-- Add to library checkbox -->
                        <div v-if="file" class="mt-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input
                                    v-model="addToLibrary"
                                    type="checkbox"
                                    :disabled="uploading"
                                    class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500 disabled:opacity-50"
                                />
                                <span class="text-sm text-gray-700">{{ t('graphics.psd.addToLibrary') }}</span>
                            </label>
                        </div>

                        <!-- Error message -->
                        <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm text-red-700">{{ error }}</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <Button
                            variant="secondary"
                            @click="handleClose"
                            :disabled="uploading"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            @click="handleUpload"
                            :disabled="!canUpload"
                            :loading="uploading"
                            class="bg-purple-600 hover:bg-purple-700"
                        >
                            {{ t('graphics.psd.import') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
