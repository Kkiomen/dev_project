<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePsdEditorStore } from '@/stores/psdEditor';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const store = usePsdEditorStore();

const searchQuery = ref('');

const filteredFiles = computed(() => {
    if (!searchQuery.value) return store.files;
    const query = searchQuery.value.toLowerCase();
    return store.files.filter(file =>
        file.name.toLowerCase().includes(query)
    );
});

const selectFile = async (filename) => {
    await store.selectFile(filename);
};

const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

onMounted(async () => {
    await store.fetchFiles();
});
</script>

<template>
    <div class="h-full flex flex-col bg-white border-r border-gray-200">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">
                {{ t('psd_editor.sidebar.title') }}
            </h3>
        </div>

        <!-- Search -->
        <div class="px-3 py-2 border-b border-gray-100">
            <div class="relative">
                <svg
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('psd_editor.sidebar.search_placeholder')"
                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>
        </div>

        <!-- File list -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading state -->
            <div v-if="store.filesLoading" class="p-4 text-center text-sm text-gray-500">
                <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ t('psd_editor.sidebar.loading') }}
            </div>

            <!-- Empty state -->
            <div v-else-if="filteredFiles.length === 0" class="p-4 text-center text-sm text-gray-500">
                {{ t('psd_editor.sidebar.no_files') }}
            </div>

            <!-- Files -->
            <div v-else class="py-1">
                <button
                    v-for="file in filteredFiles"
                    :key="file.name"
                    @click="selectFile(file.name)"
                    :class="[
                        'w-full px-3 py-2 text-left transition-colors',
                        store.currentFile === file.name
                            ? 'bg-blue-50 border-l-2 border-l-blue-500'
                            : 'hover:bg-gray-50 border-l-2 border-l-transparent'
                    ]"
                >
                    <div class="flex items-start gap-2">
                        <!-- PSD icon -->
                        <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded flex items-center justify-center">
                            <span class="text-white text-[8px] font-bold">PSD</span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                {{ file.name }}
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-gray-500">
                                    {{ formatSize(file.size) }}
                                </span>
                                <span v-if="file.has_tags" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">
                                    <svg class="w-2.5 h-2.5 mr-0.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    Tagged
                                </span>
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
