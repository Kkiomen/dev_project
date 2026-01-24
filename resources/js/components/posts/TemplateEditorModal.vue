<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useGraphicsStore } from '@/stores/graphics';
import EditorCanvas from '@/components/graphics/EditorCanvas.vue';
import LayersPanel from '@/components/graphics/LayersPanel.vue';
import PropertiesPanel from '@/components/graphics/PropertiesPanel.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
    isLibrary: {
        type: Boolean,
        default: false,
    },
    resumeTemplateId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['close', 'add-to-post', 'save-for-later']);

const { t } = useI18n();
const graphicsStore = useGraphicsStore();

const loading = ref(true);
const exporting = ref(false);
const canvasRef = ref(null);
const templateData = ref(null);
const showLayersPanel = ref(true);
const showPropertiesPanel = ref(true);

// Export settings
const exportFormat = ref('png');
const exportScale = ref(2);

const formats = [
    { value: 'png', label: 'PNG', mimeType: 'image/png' },
    { value: 'jpeg', label: 'JPEG', mimeType: 'image/jpeg' },
];

const scales = [
    { value: 1, label: '1x' },
    { value: 2, label: '2x' },
    { value: 3, label: '3x' },
];

const selectedFormat = computed(() => {
    return formats.find(f => f.value === exportFormat.value);
});

const exportDimensions = computed(() => {
    if (!templateData.value) return { width: 0, height: 0 };
    return {
        width: templateData.value.width * exportScale.value,
        height: templateData.value.height * exportScale.value,
    };
});

const loadTemplate = async () => {
    loading.value = true;
    try {
        let response;

        if (props.resumeTemplateId) {
            // Resume editing an existing template session
            response = await axios.get(`/api/v1/templates/${props.resumeTemplateId}`);
            templateData.value = response.data.data;
        } else if (props.isLibrary) {
            // For library templates, first copy to user's collection
            response = await axios.post(`/api/v1/library/templates/${props.template.id}/copy`);
            templateData.value = response.data.data;
        } else {
            // For user's own templates, duplicate to avoid modifying original
            response = await axios.post(`/api/v1/templates/${props.template.id}/duplicate`);
            templateData.value = response.data.data;
        }

        // Set the template and layers in the store
        graphicsStore.setCurrentTemplate(templateData.value);
        graphicsStore.setLayers(templateData.value.layers || []);
        graphicsStore.setFonts(templateData.value.fonts || []);

        await nextTick();
    } catch (error) {
        console.error('Failed to load template:', error);
        emit('close');
    } finally {
        loading.value = false;
    }
};

const handleAddToPost = async () => {
    if (!canvasRef.value?.exportToBlob) {
        console.error('Export function not available');
        return;
    }

    exporting.value = true;

    try {
        const blob = await canvasRef.value.exportToBlob({
            pixelRatio: exportScale.value,
            format: selectedFormat.value.mimeType,
            quality: exportFormat.value === 'png' ? 1 : 0.9,
        });

        if (!blob) {
            throw new Error('Failed to generate image');
        }

        // Create a File object from the blob
        const filename = `${templateData.value.name || 'graphic'}.${exportFormat.value}`;
        const file = new File([blob], filename, { type: selectedFormat.value.mimeType });

        // Clean up the temporary template
        try {
            await axios.delete(`/api/v1/templates/${templateData.value.id}`);
        } catch (e) {
            console.warn('Failed to cleanup temporary template:', e);
        }

        emit('add-to-post', file);
    } catch (error) {
        console.error('Export failed:', error);
    } finally {
        exporting.value = false;
    }
};

const handleSaveForLater = () => {
    // Save the session data so user can resume later
    emit('save-for-later', {
        templateId: templateData.value.id,
        originalTemplateId: props.template.id,
        originalTemplateName: props.template.name,
        isLibrary: props.isLibrary,
    });
    emit('close');
};

const handleDiscard = async () => {
    // Clean up the temporary template
    if (templateData.value?.id) {
        try {
            await axios.delete(`/api/v1/templates/${templateData.value.id}`);
        } catch (e) {
            console.warn('Failed to cleanup temporary template:', e);
        }
    }
    emit('close');
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
        return;
    }

    // Ctrl/Cmd + Z: Undo
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        graphicsStore.undo();
    }

    // Ctrl/Cmd + Shift + Z: Redo
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'z') {
        e.preventDefault();
        graphicsStore.redo();
    }

    // Delete: Delete selected layer
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (graphicsStore.selectedLayerId) {
            e.preventDefault();
            graphicsStore.deleteLayer(graphicsStore.selectedLayerId);
        }
    }

    // Escape: Deselect or close
    if (e.key === 'Escape') {
        if (graphicsStore.selectedLayerId) {
            graphicsStore.deselectLayer();
        } else {
            handleSaveForLater();
        }
    }
};

onMounted(() => {
    loadTemplate();
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    graphicsStore.reset();
});
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white w-full h-full flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center space-x-4">
                    <button
                        @click="handleSaveForLater"
                        class="text-gray-500 hover:text-gray-700"
                        :title="t('posts.media.saveForLater')"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ t('posts.media.editTemplate') }}
                    </h2>
                    <span v-if="templateData" class="text-sm text-gray-500">
                        {{ templateData.name }}
                    </span>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Undo/Redo -->
                    <div class="flex items-center space-x-1">
                        <button
                            @click="graphicsStore.undo"
                            :disabled="!graphicsStore.canUndo"
                            class="p-2 text-gray-500 hover:text-gray-700 disabled:opacity-40"
                            :title="t('graphics.editor.undo')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                        </button>
                        <button
                            @click="graphicsStore.redo"
                            :disabled="!graphicsStore.canRedo"
                            class="p-2 text-gray-500 hover:text-gray-700 disabled:opacity-40"
                            :title="t('graphics.editor.redo')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/>
                            </svg>
                        </button>
                    </div>

                    <div class="h-6 w-px bg-gray-300"></div>

                    <!-- Panel toggles -->
                    <div class="flex items-center space-x-1">
                        <button
                            @click="showLayersPanel = !showLayersPanel"
                            class="p-2 rounded-lg transition-colors"
                            :class="showLayersPanel ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:bg-gray-100'"
                            :title="t('graphics.layers.title')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </button>
                        <button
                            @click="showPropertiesPanel = !showPropertiesPanel"
                            class="p-2 rounded-lg transition-colors"
                            :class="showPropertiesPanel ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:bg-gray-100'"
                            :title="t('graphics.layers.properties')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                        </button>
                    </div>

                    <div class="h-6 w-px bg-gray-300"></div>

                    <!-- Export settings -->
                    <div class="flex items-center space-x-2">
                        <select
                            v-model="exportFormat"
                            class="px-2 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="f in formats" :key="f.value" :value="f.value">
                                {{ f.label }}
                            </option>
                        </select>
                        <select
                            v-model="exportScale"
                            class="px-2 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="s in scales" :key="s.value" :value="s.value">
                                {{ s.label }}
                            </option>
                        </select>
                        <span class="text-xs text-gray-500">
                            {{ exportDimensions.width }}×{{ exportDimensions.height }}
                        </span>
                    </div>

                    <div class="h-6 w-px bg-gray-300"></div>

                    <!-- Actions -->
                    <Button variant="ghost" @click="handleDiscard" class="text-red-600 hover:text-red-700 hover:bg-red-50">
                        {{ t('posts.media.discardChanges') }}
                    </Button>
                    <Button variant="secondary" @click="handleSaveForLater">
                        {{ t('posts.media.saveForLater') }}
                    </Button>
                    <Button
                        :loading="exporting"
                        @click="handleAddToPost"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        {{ t('posts.media.addToPost') }}
                    </Button>
                </div>
            </div>

            <!-- Editor content -->
            <div v-if="loading" class="flex-1 flex items-center justify-center bg-gray-100">
                <LoadingSpinner size="lg" />
            </div>

            <div v-else class="flex-1 flex overflow-hidden">
                <!-- Layers panel -->
                <div
                    v-show="showLayersPanel"
                    class="w-64 border-r border-gray-200 bg-white overflow-y-auto"
                >
                    <LayersPanel />
                </div>

                <!-- Canvas -->
                <div class="flex-1 bg-gray-100 overflow-hidden">
                    <EditorCanvas
                        ref="canvasRef"
                        :template="templateData"
                    />
                </div>

                <!-- Properties panel -->
                <div
                    v-show="showPropertiesPanel"
                    class="w-80 border-l border-gray-200 bg-white overflow-y-auto"
                >
                    <PropertiesPanel />
                </div>
            </div>
        </div>
    </div>
</template>
