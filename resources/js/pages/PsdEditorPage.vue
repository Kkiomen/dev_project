<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePsdEditorStore } from '@/stores/psdEditor';
import { usePhotopea } from '@/composables/usePhotopea';
import { useToast } from '@/composables/useToast';
import { useI18n } from 'vue-i18n';
import PsdFileSidebar from '@/components/psd-editor/PsdFileSidebar.vue';
import LayerTaggingPanel from '@/components/psd-editor/LayerTaggingPanel.vue';
import PreviewPanel from '@/components/psd-editor/PreviewPanel.vue';
import VariantPreviewModal from '@/components/psd-editor/VariantPreviewModal.vue';

const { t } = useI18n();
const store = usePsdEditorStore();
const toast = useToast();

// Preview modal
const showPreviewModal = ref(false);

// Check if file has variants for preview
const canShowPreview = computed(() => {
    return store.currentFile && store.variants.length > 0;
});

// Photopea iframe
const photopeaIframe = ref(null);
const photopea = usePhotopea(photopeaIframe);

// Layout state
const leftPanelWidth = ref(200);
const rightPanelWidth = ref(300);
const layersPanelWidth = ref(280);

// Photopea URL with configuration
const photopeaUrl = computed(() => {
    return photopea.buildPhotopeaUrl({
        config: {
            environment: {
                customIO: true,
            },
        },
    });
});

// Load file into Photopea when selected
const loadFileInPhotopea = async () => {
    if (!store.currentFile || !photopea.isReady.value) return;

    try {
        const fileUrl = await store.getFileUrl(store.currentFile);
        const response = await fetch(fileUrl, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
            },
        });
        const arrayBuffer = await response.arrayBuffer();
        await photopea.loadFile(arrayBuffer, store.currentFile);
    } catch (error) {
        console.error('Failed to load file in Photopea:', error);
        toast.error(t('psd_editor.errors.photopea_load_failed'));
    }
};

// Save file from Photopea
const saveFromPhotopea = async () => {
    if (!store.currentFile || !photopea.isReady.value) return;

    try {
        const psdData = await photopea.saveDocument('psd');
        if (psdData) {
            await store.saveFile(store.currentFile, new Blob([psdData]));
            toast.success(t('psd_editor.messages.file_saved'));
            // Re-parse the file to get updated layers
            await store.parseFile(store.currentFile);
        }
    } catch (error) {
        console.error('Failed to save from Photopea:', error);
        toast.error(t('psd_editor.errors.save_failed'));
    }
};

// Refresh parsed data
const refreshParsedData = async () => {
    if (!store.currentFile) return;
    await store.parseFile(store.currentFile);
};

// Handle variant selection from layer panel
const handleVariantSelected = (variantPath) => {
    store.selectVariant(variantPath);
};

// Handle import completion
const handleImportComplete = (templates) => {
    toast.success(t('psd_editor.messages.import_success', { count: templates.length }));
};

// Cleanup
onUnmounted(() => {
    store.reset();
});
</script>

<template>
    <div class="h-screen flex flex-col bg-gray-100">
        <!-- Toolbar -->
        <div class="flex-shrink-0 px-4 py-2 bg-white border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-lg font-semibold text-gray-900">
                    {{ t('psd_editor.title') }}
                </h1>
                <span v-if="store.currentFile" class="text-sm text-gray-500">
                    {{ store.currentFile }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="refreshParsedData"
                    :disabled="!store.currentFile || store.parsingLoading"
                    class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors flex items-center gap-1.5"
                >
                    <svg
                        :class="['w-4 h-4', store.parsingLoading && 'animate-spin']"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    {{ t('psd_editor.toolbar.refresh') }}
                </button>

                <button
                    @click="loadFileInPhotopea"
                    :disabled="!store.currentFile || !photopea.isReady.value"
                    class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    {{ t('psd_editor.photopea.load_file') }}
                </button>

                <button
                    @click="saveFromPhotopea"
                    :disabled="!store.currentFile || !photopea.isReady.value"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed rounded-md transition-colors flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    {{ t('psd_editor.toolbar.save_psd') }}
                </button>

                <!-- Preview with custom data button -->
                <button
                    @click="showPreviewModal = true"
                    :disabled="!canShowPreview"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 disabled:bg-purple-400 disabled:cursor-not-allowed rounded-md transition-colors flex items-center gap-1.5"
                    :title="!canShowPreview ? t('psd_editor.variant_preview.no_variants_hint') : ''"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    {{ t('psd_editor.toolbar.preview_with_data') }}
                    <span v-if="store.variants.length > 0" class="ml-1 px-1.5 py-0.5 text-xs bg-purple-500 rounded-full">
                        {{ store.variants.length }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Left sidebar - File list -->
            <div
                class="flex-shrink-0 overflow-hidden"
                :style="{ width: leftPanelWidth + 'px' }"
            >
                <PsdFileSidebar />
            </div>

            <!-- Layers panel -->
            <div
                class="flex-shrink-0 overflow-hidden border-l border-gray-200"
                :style="{ width: layersPanelWidth + 'px' }"
            >
                <LayerTaggingPanel @variant-selected="handleVariantSelected" />
            </div>

            <!-- Photopea iframe -->
            <div class="flex-1 overflow-hidden bg-gray-900">
                <div v-if="!photopea.isReady.value" class="h-full flex items-center justify-center text-white">
                    <div class="text-center">
                        <svg class="animate-spin h-8 w-8 mx-auto mb-3 text-blue-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm text-gray-400">{{ t('psd_editor.photopea.loading') }}</span>
                    </div>
                </div>
                <iframe
                    ref="photopeaIframe"
                    :src="photopeaUrl"
                    class="w-full h-full border-0"
                    allow="clipboard-write"
                />
            </div>

            <!-- Right sidebar - Preview -->
            <div
                class="flex-shrink-0 overflow-hidden"
                :style="{ width: rightPanelWidth + 'px' }"
            >
                <PreviewPanel @import="handleImportComplete" />
            </div>
        </div>

        <!-- Error toast -->
        <div
            v-if="store.error"
            class="fixed bottom-4 right-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg shadow-lg flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm">{{ store.error }}</span>
            <button
                @click="store.clearError()"
                class="text-red-500 hover:text-red-700"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Variant Preview Modal -->
        <VariantPreviewModal
            v-if="showPreviewModal"
            @close="showPreviewModal = false"
        />
    </div>
</template>
