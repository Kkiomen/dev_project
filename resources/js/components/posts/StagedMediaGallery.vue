<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    media: {
        type: Array,
        default: () => [],
    },
    showUploadArea: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['upload', 'remove', 'reorder', 'open-templates']);

const { t } = useI18n();

const dragOver = ref(false);
const selectedMedia = ref(null);
const showPreviewModal = ref(false);

// File type filters
const acceptedTypes = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime';

const handleFileSelect = (event) => {
    const files = event.target.files;
    if (!files?.length) return;

    emit('upload', Array.from(files));
    event.target.value = '';
};

const handleDrop = (event) => {
    event.preventDefault();
    dragOver.value = false;

    const files = event.dataTransfer.files;
    if (!files?.length) return;

    // Filter valid files
    const validFiles = Array.from(files).filter(file => {
        return file.type.startsWith('image/') || file.type.startsWith('video/');
    });

    if (validFiles.length > 0) {
        emit('upload', validFiles);
    }
};

const handleRemove = (mediaId) => {
    emit('remove', mediaId);
};

// Drag and drop reorder
let draggedIndex = null;

const handleDragStart = (event, index) => {
    draggedIndex = index;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', index);
};

const handleDragOver = (event, index) => {
    event.preventDefault();
    if (draggedIndex !== null && draggedIndex !== index) {
        emit('reorder', draggedIndex, index);
        draggedIndex = index;
    }
};

const handleDragEnd = () => {
    draggedIndex = null;
};

// Move media up/down
const moveMedia = (index, direction) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= props.media.length) return;
    emit('reorder', index, newIndex);
};

// Open preview modal
const openPreview = (item) => {
    selectedMedia.value = item;
    showPreviewModal.value = true;
};

// Set as cover (first position)
const setAsCover = (index) => {
    if (index === 0) return;
    emit('reorder', index, 0);
};

// Format file size
const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

// Stats
const stats = computed(() => {
    const images = props.media.filter(m => m.is_image).length;
    const videos = props.media.filter(m => m.is_video).length;
    return { images, videos, total: props.media.length };
});

// Get preview URL for media item
const getPreviewUrl = (item) => {
    return item.preview_url || item.thumbnail_url || item.url;
};
</script>

<template>
    <div>
        <!-- Upload area -->
        <div v-if="showUploadArea" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- File upload -->
            <div
                @dragover.prevent="dragOver = true"
                @dragleave="dragOver = false"
                @drop="handleDrop"
                class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                :class="dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'"
            >
                <input
                    type="file"
                    id="staged-media-upload"
                    class="hidden"
                    multiple
                    :accept="acceptedTypes"
                    @change="handleFileSelect"
                />
                <label for="staged-media-upload" class="cursor-pointer">
                    <div class="flex flex-col items-center">
                        <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-900">
                            {{ t('posts.media.dragOrClick') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('posts.media.supportedFormats') }}
                        </p>
                    </div>
                </label>
            </div>

            <!-- From templates -->
            <button
                @click="emit('open-templates')"
                class="border-2 border-dashed border-gray-300 hover:border-purple-400 rounded-lg p-6 text-center transition-colors hover:bg-purple-50 group"
            >
                <div class="flex flex-col items-center">
                    <svg class="h-10 w-10 text-gray-400 group-hover:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                    <p class="mt-2 text-sm font-medium text-gray-900 group-hover:text-purple-700">
                        {{ t('posts.media.fromTemplates') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 group-hover:text-purple-600">
                        {{ t('graphics.library.browse') }}
                    </p>
                </div>
            </button>
        </div>

        <!-- Info about local storage -->
        <div v-if="media.length > 0 && showUploadArea" class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-amber-700">
                    {{ t('posts.media.stagedInfo') }}
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div v-if="media.length > 0" class="mt-4 flex items-center justify-between">
            <div class="flex items-center space-x-4 text-sm text-gray-500">
                <span>{{ t('posts.media.total') }}: {{ stats.total }}</span>
                <span v-if="stats.images">{{ t('posts.media.images') }}: {{ stats.images }}</span>
                <span v-if="stats.videos">{{ t('posts.media.videos') }}: {{ stats.videos }}</span>
            </div>
        </div>

        <!-- Media grid -->
        <div v-if="media.length > 0" class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <div
                v-for="(item, index) in media"
                :key="item.id"
                draggable="true"
                @dragstart="handleDragStart($event, index)"
                @dragover="handleDragOver($event, index)"
                @dragend="handleDragEnd"
                class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-move border-2 border-transparent hover:border-blue-400 transition-colors"
                :class="{ 'ring-2 ring-blue-500': index === 0 }"
            >
                <!-- Image/Video preview -->
                <img
                    v-if="item.is_image"
                    :src="getPreviewUrl(item)"
                    :alt="item.filename"
                    class="w-full h-full object-cover"
                    @click="openPreview(item)"
                />
                <div v-else-if="item.is_video" class="relative w-full h-full" @click="openPreview(item)">
                    <video
                        v-if="item.preview_url"
                        :src="item.preview_url"
                        class="w-full h-full object-cover"
                        muted
                    />
                    <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-12 h-12 bg-black bg-opacity-50 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Staged badge -->
                <span class="absolute top-2 left-2 px-2 py-1 bg-amber-500 text-white text-xs rounded flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ t('posts.media.staged') }}
                </span>

                <!-- Overlay controls -->
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all">
                    <!-- Top controls -->
                    <div class="absolute top-2 right-2 flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <!-- Delete button -->
                        <button
                            @click.stop="handleRemove(item.id)"
                            class="p-1.5 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Bottom controls -->
                    <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between opacity-0 group-hover:opacity-100 transition-opacity">
                        <!-- Reorder buttons -->
                        <div class="flex space-x-1">
                            <button
                                v-if="index > 0"
                                @click.stop="moveMedia(index, -1)"
                                class="p-1.5 bg-white text-gray-700 rounded hover:bg-gray-100 transition-colors"
                                :title="t('posts.media.moveLeft')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <button
                                v-if="index < media.length - 1"
                                @click.stop="moveMedia(index, 1)"
                                class="p-1.5 bg-white text-gray-700 rounded hover:bg-gray-100 transition-colors"
                                :title="t('posts.media.moveRight')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Set as cover -->
                        <button
                            v-if="index > 0"
                            @click.stop="setAsCover(index)"
                            class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors"
                        >
                            {{ t('posts.media.setAsCover') }}
                        </button>
                    </div>
                </div>

                <!-- Cover badge -->
                <span
                    v-if="index === 0"
                    class="absolute bottom-2 left-2 bg-blue-600 text-white text-xs px-2 py-1 rounded group-hover:opacity-0 transition-opacity"
                >
                    {{ t('posts.media.cover') }}
                </span>

                <!-- Video badge -->
                <span
                    v-if="item.is_video"
                    class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs px-2 py-1 rounded flex items-center group-hover:opacity-0 transition-opacity"
                >
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    {{ t('posts.media.video') }}
                </span>

                <!-- File info on hover -->
                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <p class="text-white text-xs truncate">{{ item.filename }}</p>
                    <p class="text-white/70 text-xs">{{ formatSize(item.size) }}</p>
                </div>
            </div>
        </div>

        <p v-if="media.length > 0" class="mt-3 text-xs text-gray-500">
            {{ t('posts.media.reorderHint') }}
        </p>

        <!-- Preview Modal -->
        <teleport to="body">
            <div
                v-if="showPreviewModal && selectedMedia"
                class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50"
                @click.self="showPreviewModal = false"
            >
                <button
                    @click="showPreviewModal = false"
                    class="absolute top-4 right-4 text-white hover:text-gray-300"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <div class="max-w-4xl max-h-[90vh] overflow-hidden">
                    <img
                        v-if="selectedMedia.is_image"
                        :src="getPreviewUrl(selectedMedia)"
                        :alt="selectedMedia.filename"
                        class="max-w-full max-h-[90vh] object-contain"
                    />
                    <video
                        v-else-if="selectedMedia.is_video && selectedMedia.preview_url"
                        :src="selectedMedia.preview_url"
                        class="max-w-full max-h-[90vh]"
                        controls
                        autoplay
                    />
                </div>

                <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-center">
                    <p class="font-medium">{{ selectedMedia.filename }}</p>
                    <p class="text-sm text-gray-400">{{ formatSize(selectedMedia.size) }}</p>
                </div>
            </div>
        </teleport>
    </div>
</template>
