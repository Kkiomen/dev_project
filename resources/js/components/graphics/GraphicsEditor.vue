<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import { useGoogleFonts } from '@/composables/useGoogleFonts';
import EditorToolbar from './EditorToolbar.vue';
import EditorCanvas from './EditorCanvas.vue';
import LayersPanel from './LayersPanel.vue';
import PropertiesPanel from './PropertiesPanel.vue';
import ExportModal from './modals/ExportModal.vue';
import FontUploadModal from './modals/FontUploadModal.vue';
import ApiDocsModal from './modals/ApiDocsModal.vue';

const { t } = useI18n();
const { loadFont } = useGoogleFonts();

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

const graphicsStore = useGraphicsStore();

const canvasRef = ref(null);
const showLayersPanel = ref(true);
const showPropertiesPanel = ref(true);
const showExportModal = ref(false);
const showFontModal = ref(false);
const showApiDocsModal = ref(false);

// Auto-save interval (30 seconds)
const AUTO_SAVE_INTERVAL = 30000;
let autoSaveTimer = null;

// Auto-save when dirty
const startAutoSave = () => {
    if (autoSaveTimer) return;

    autoSaveTimer = setInterval(async () => {
        if (graphicsStore.isDirty && !graphicsStore.saving) {
            try {
                await handleSave();
            } catch (error) {
                console.error('Auto-save failed:', error);
            }
        }
    }, AUTO_SAVE_INTERVAL);
};

const stopAutoSave = () => {
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
        autoSaveTimer = null;
    }
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    // Don't handle if user is typing in an input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
        return;
    }

    // Ctrl/Cmd + S: Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        handleSave();
    }

    // Ctrl/Cmd + Z: Undo
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        graphicsStore.undo();
    }

    // Ctrl/Cmd + Shift + Z or Ctrl/Cmd + Y: Redo
    if ((e.ctrlKey || e.metaKey) && (e.shiftKey && e.key === 'z' || e.key === 'y')) {
        e.preventDefault();
        graphicsStore.redo();
    }

    // Ctrl/Cmd + C: Copy
    if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
        if (graphicsStore.selectedLayerId) {
            e.preventDefault();
            graphicsStore.copyLayer();
        }
    }

    // Ctrl/Cmd + V: Paste
    if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
        if (graphicsStore.clipboard) {
            e.preventDefault();
            graphicsStore.pasteLayer();
        }
    }

    // Ctrl/Cmd + D: Duplicate
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        if (graphicsStore.selectedLayerId) {
            e.preventDefault();
            graphicsStore.duplicateLayer();
        }
    }

    // Delete/Backspace: Delete selected layer
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (graphicsStore.selectedLayerId) {
            e.preventDefault();
            graphicsStore.deleteLayer(graphicsStore.selectedLayerId);
        }
    }

    // Escape: Deselect
    if (e.key === 'Escape') {
        graphicsStore.deselectLayer();
        graphicsStore.setTool('select');
    }

    // Ctrl/Cmd + A: Select all layers
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        graphicsStore.selectAllLayers();
    }

    // Tool shortcuts (only when no modifier keys)
    if (!e.ctrlKey && !e.metaKey && !e.altKey) {
        if (e.key === 'v') graphicsStore.setTool('select');
        if (e.key === 't') graphicsStore.setTool('text');
        if (e.key === 'r') graphicsStore.setTool('rectangle');
        if (e.key === 'o') graphicsStore.setTool('ellipse');
        if (e.key === 'i') graphicsStore.setTool('image');
    }

    // Bracket keys for layer order
    if (e.key === ']' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        if (e.shiftKey) {
            graphicsStore.bringToFront();
        } else {
            graphicsStore.bringForward();
        }
    }

    if (e.key === '[' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        if (e.shiftKey) {
            graphicsStore.sendToBack();
        } else {
            graphicsStore.sendBackward();
        }
    }
};

// Load fonts used by text layers
watch(
    () => graphicsStore.layers,
    (layers) => {
        const textLayers = layers.filter((l) => l.type === 'text');
        const fonts = new Set(textLayers.map((l) => l.properties?.fontFamily).filter(Boolean));
        fonts.forEach((font) => loadFont(font));
    },
    { immediate: true, deep: true }
);

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    startAutoSave();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    stopAutoSave();
});

const handleSave = async () => {
    try {
        await graphicsStore.saveAllLayers();
        await graphicsStore.updateTemplate(props.template.id, {
            name: props.template.name,
        });
    } catch (error) {
        console.error('Failed to save:', error);
    }
};

const handleExport = () => {
    showExportModal.value = true;
};

const handleOpenFonts = () => {
    showFontModal.value = true;
};

const handleOpenApiDocs = () => {
    showApiDocsModal.value = true;
};
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Toolbar -->
        <EditorToolbar
            :template="template"
            @save="handleSave"
            @export="handleExport"
            @open-fonts="handleOpenFonts"
            @open-api-docs="handleOpenApiDocs"
            @toggle-layers="showLayersPanel = !showLayersPanel"
            @toggle-properties="showPropertiesPanel = !showPropertiesPanel"
        />

        <!-- Main content -->
        <div class="flex-1 flex min-h-0 overflow-hidden">
            <!-- Layers panel -->
            <div
                v-if="showLayersPanel"
                class="w-64 flex-shrink-0 bg-white border-r border-gray-200 overflow-y-auto"
            >
                <LayersPanel />
            </div>

            <!-- Canvas area -->
            <div class="flex-1 bg-gray-100 min-w-0 flex flex-col">
                <EditorCanvas
                    ref="canvasRef"
                    :template="template"
                    class="flex-1"
                />
            </div>

            <!-- Properties panel -->
            <div
                v-if="showPropertiesPanel"
                class="w-72 flex-shrink-0 bg-white border-l border-gray-200 overflow-y-auto"
            >
                <PropertiesPanel />
            </div>
        </div>

        <!-- Export modal -->
        <ExportModal
            :show="showExportModal"
            :template="template"
            :stage-ref="canvasRef"
            @close="showExportModal = false"
        />

        <!-- Font upload modal -->
        <FontUploadModal
            :show="showFontModal"
            :template-id="template.id"
            @close="showFontModal = false"
        />

        <!-- API docs modal -->
        <ApiDocsModal
            :show="showApiDocsModal"
            :template="template"
            @close="showApiDocsModal = false"
        />
    </div>
</template>
