<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    taskId: { type: String, required: true },
    attachments: { type: Array, default: () => [] },
});

const emit = defineEmits(['updated']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

const isDragging = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const previewAttachment = ref(null);

const fileInputRef = ref(null);

const sortedAttachments = computed(() => {
    return [...props.attachments].sort((a, b) => a.position - b.position);
});

const handleFileSelect = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        uploadFiles(Array.from(files));
    }
    e.target.value = '';
};

const handleDrop = (e) => {
    e.preventDefault();
    isDragging.value = false;

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadFiles(Array.from(files));
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = () => {
    isDragging.value = false;
};

const uploadFiles = async (files) => {
    uploading.value = true;
    uploadProgress.value = 0;

    const totalFiles = files.length;
    let completedFiles = 0;

    for (const file of files) {
        try {
            await devTasksStore.uploadAttachment(props.taskId, file);
            completedFiles++;
            uploadProgress.value = Math.round((completedFiles / totalFiles) * 100);
        } catch (error) {
            toast.error(t('devTasks.attachments.uploadError', { name: file.name }));
        }
    }

    uploading.value = false;
    uploadProgress.value = 0;
    emit('updated');
};

const handleDelete = async (attachmentId) => {
    try {
        await devTasksStore.deleteAttachment(props.taskId, attachmentId);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.attachments.deleteError'));
    }
};

const openPreview = (attachment) => {
    if (attachment.is_image) {
        previewAttachment.value = attachment;
    } else {
        window.open(attachment.url, '_blank');
    }
};

const closePreview = () => {
    previewAttachment.value = null;
};

const getFileIcon = (mimeType) => {
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType.includes('pdf')) return 'pdf';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'doc';
    if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'sheet';
    if (mimeType.includes('zip') || mimeType.includes('rar')) return 'archive';
    return 'file';
};

const fileIcons = {
    image: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    pdf: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    doc: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    sheet: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
    archive: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
    file: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
};
</script>

<template>
    <div class="attachments-panel">
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-gray-700">
                {{ t('devTasks.attachments.title') }}
            </h4>
            <span v-if="attachments.length" class="text-xs text-gray-500">
                {{ attachments.length }} {{ t('devTasks.attachments.files') }}
            </span>
        </div>

        <!-- Drop zone -->
        <div
            @drop="handleDrop"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            class="border-2 border-dashed rounded-lg p-4 text-center transition-colors mb-3"
            :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
        >
            <input
                ref="fileInputRef"
                type="file"
                multiple
                class="hidden"
                @change="handleFileSelect"
            />

            <div v-if="uploading" class="space-y-2">
                <svg class="w-8 h-8 mx-auto text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all" :style="{ width: `${uploadProgress}%` }"></div>
                </div>
                <p class="text-sm text-gray-500">{{ t('devTasks.attachments.uploading') }}</p>
            </div>

            <div v-else>
                <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="text-sm text-gray-600 mb-1">
                    {{ t('devTasks.attachments.dropHere') }}
                </p>
                <button
                    @click="fileInputRef?.click()"
                    class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                    {{ t('devTasks.attachments.browse') }}
                </button>
            </div>
        </div>

        <!-- Attachments grid -->
        <div v-if="sortedAttachments.length" class="grid grid-cols-2 gap-2">
            <div
                v-for="attachment in sortedAttachments"
                :key="attachment.id"
                class="group relative bg-gray-50 rounded-lg overflow-hidden border border-gray-200 hover:border-gray-300 transition-colors"
            >
                <!-- Image preview -->
                <div
                    v-if="attachment.is_image"
                    @click="openPreview(attachment)"
                    class="aspect-video cursor-pointer"
                >
                    <img
                        :src="attachment.thumbnail_url || attachment.url"
                        :alt="attachment.filename"
                        class="w-full h-full object-cover"
                    />
                </div>

                <!-- File icon -->
                <div
                    v-else
                    @click="openPreview(attachment)"
                    class="aspect-video flex items-center justify-center cursor-pointer hover:bg-gray-100"
                >
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="fileIcons[getFileIcon(attachment.mime_type)]" />
                    </svg>
                </div>

                <!-- File info -->
                <div class="p-2">
                    <p class="text-xs text-gray-700 truncate" :title="attachment.filename">
                        {{ attachment.filename }}
                    </p>
                    <p class="text-xs text-gray-400">
                        {{ attachment.human_size }}
                    </p>
                </div>

                <!-- Delete button -->
                <button
                    @click.stop="handleDelete(attachment.id)"
                    class="absolute top-1 right-1 p-1 bg-white/80 rounded-full text-gray-400 hover:text-red-600 hover:bg-white opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div v-else class="text-sm text-gray-400 text-center py-4">
            {{ t('devTasks.attachments.empty') }}
        </div>

        <!-- Preview modal -->
        <Teleport to="body">
            <div
                v-if="previewAttachment"
                @click="closePreview"
                class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
            >
                <button
                    @click.stop="closePreview"
                    class="absolute top-4 right-4 p-2 text-white/80 hover:text-white"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <img
                    :src="previewAttachment.url"
                    :alt="previewAttachment.filename"
                    class="max-w-full max-h-full object-contain"
                    @click.stop
                />
            </div>
        </Teleport>
    </div>
</template>
