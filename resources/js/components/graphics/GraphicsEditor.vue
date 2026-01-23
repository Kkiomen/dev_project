<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useGraphicsStore } from '@/stores/graphics';
import { useGoogleFonts } from '@/composables/useGoogleFonts';
import EditorToolbar from './EditorToolbar.vue';
import EditorCanvas from './EditorCanvas.vue';
import LayersPanel from './LayersPanel.vue';
import PropertiesPanel from './PropertiesPanel.vue';
import AiChatPanel from './AiChatPanel.vue';
import ExportModal from './modals/ExportModal.vue';
import FontUploadModal from './modals/FontUploadModal.vue';
import TemplateLibraryModal from './TemplateLibraryModal.vue';
import AddToLibraryModal from './AddToLibraryModal.vue';

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
const showLibraryModal = ref(false);
const showAddToLibraryModal = ref(false);

// Resizable panel
const DEFAULT_PANEL_WIDTH = 384; // w-96 = 24rem = 384px
const MIN_PANEL_WIDTH = 280;
const MAX_PANEL_WIDTH = 600;
const propertiesPanelWidth = ref(DEFAULT_PANEL_WIDTH);
const isResizing = ref(false);

const startResize = (e) => {
    isResizing.value = true;
    document.addEventListener('mousemove', handleResize);
    document.addEventListener('mouseup', stopResize);
    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';
};

const handleResize = (e) => {
    if (!isResizing.value) return;
    const newWidth = window.innerWidth - e.clientX;
    propertiesPanelWidth.value = Math.min(MAX_PANEL_WIDTH, Math.max(MIN_PANEL_WIDTH, newWidth));
};

const stopResize = () => {
    isResizing.value = false;
    document.removeEventListener('mousemove', handleResize);
    document.removeEventListener('mouseup', stopResize);
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
};

const handleTemplateCopied = (newTemplate) => {
    showLibraryModal.value = false;
    // Redirect to the new template
    window.location.href = `/graphics/${newTemplate.id}`;
};

const handleAppliedToCurrent = (updatedTemplate) => {
    showLibraryModal.value = false;
    // Reload the page to get fresh template data with new layers
    window.location.reload();
};

const handleAddedToLibrary = (template) => {
    showAddToLibraryModal.value = false;
    // Optionally refresh template data
};

const handleUnlinkFromLibrary = async () => {
    try {
        await axios.post(`/api/v1/templates/${props.template.id}/unlink-from-library`);
        window.location.reload();
    } catch (error) {
        console.error('Failed to unlink from library:', error);
    }
};

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
        if (e.key === 'l') graphicsStore.setTool('line');
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
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Toolbar -->
        <EditorToolbar
            :template="template"
            @save="handleSave"
            @export="handleExport"
            @open-fonts="handleOpenFonts"
            @toggle-layers="showLayersPanel = !showLayersPanel"
            @toggle-properties="showPropertiesPanel = !showPropertiesPanel"
            @open-library="showLibraryModal = true"
            @add-to-library="showAddToLibraryModal = true"
            @unlink-from-library="handleUnlinkFromLibrary"
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

            <!-- Properties panel / AI Chat panel -->
            <div
                v-if="showPropertiesPanel"
                class="flex-shrink-0 bg-white border-l border-gray-200 overflow-hidden relative"
                :style="{ width: graphicsStore.chatPanelOpen ? propertiesPanelWidth + 'px' : '384px' }"
            >
                <!-- Resize handle (only for AI chat) -->
                <div
                    v-if="graphicsStore.chatPanelOpen"
                    class="absolute left-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-purple-400 transition-colors z-10"
                    :style="{ backgroundColor: isResizing ? '#a855f7' : 'transparent' }"
                    @mousedown="startResize"
                ></div>

                <AiChatPanel
                    v-if="graphicsStore.chatPanelOpen"
                    @close="graphicsStore.closeChatPanel()"
                />
                <div v-else class="h-full overflow-y-auto">
                    <PropertiesPanel />
                </div>
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

        <!-- Template Library modal -->
        <TemplateLibraryModal
            :show="showLibraryModal"
            :current-template-id="template.id"
            @close="showLibraryModal = false"
            @template-copied="handleTemplateCopied"
            @applied-to-current="handleAppliedToCurrent"
        />

        <!-- Add to Library modal (admin only) -->
        <AddToLibraryModal
            :show="showAddToLibraryModal"
            :template-id="template.id"
            :canvas-ref="canvasRef"
            @close="showAddToLibraryModal = false"
            @added="handleAddedToLibrary"
        />
    </div>
</template>
