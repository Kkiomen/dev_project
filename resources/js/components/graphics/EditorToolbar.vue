<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['save', 'export', 'open-fonts', 'toggle-layers', 'toggle-properties', 'add-layer-at', 'open-library', 'add-to-library']);

const authStore = useAuthStore();

const router = useRouter();
const graphicsStore = useGraphicsStore();

// Save status
const saveStatus = computed(() => {
    if (graphicsStore.saving) {
        return { text: t('graphics.editor.saving'), color: 'text-blue-600', icon: 'spinner' };
    }
    if (graphicsStore.isDirty) {
        return { text: t('graphics.editor.unsaved'), color: 'text-amber-600', icon: 'warning' };
    }
    if (graphicsStore.lastSavedAt) {
        return { text: t('graphics.editor.saved'), color: 'text-green-600', icon: 'check' };
    }
    return null;
});

const tools = [
    { id: 'select', icon: 'cursor', label: 'tools.select', shortcut: 'V', draggable: false },
    { id: 'text', icon: 'type', label: 'tools.text', shortcut: 'T', draggable: true },
    { id: 'rectangle', icon: 'square', label: 'tools.rectangle', shortcut: 'R', draggable: true },
    { id: 'ellipse', icon: 'circle', label: 'tools.ellipse', shortcut: 'O', draggable: true },
    { id: 'image', icon: 'image', label: 'tools.image', shortcut: 'I', draggable: false },
];

const draggingTool = ref(null);

const handleToolClick = (toolId) => {
    if (toolId === 'image') {
        // Open file picker for image
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = async (event) => {
                    const layer = await graphicsStore.addLayer('image', {
                        properties: {
                            src: event.target.result,
                            fit: 'cover',
                        },
                    });
                    graphicsStore.setTool('select');
                };
                reader.readAsDataURL(file);
            }
        };
        input.click();
    } else if (toolId !== 'select') {
        graphicsStore.setTool(toolId);
        // Add layer when tool is selected
        emit('add-layer-at', { type: toolId });
        graphicsStore.setTool('select');
    } else {
        graphicsStore.setTool(toolId);
    }
};

// Drag and drop handlers
const handleDragStart = (e, tool) => {
    if (!tool.draggable) {
        e.preventDefault();
        return;
    }
    draggingTool.value = tool.id;
    e.dataTransfer.setData('application/x-graphics-tool', tool.id);
    e.dataTransfer.effectAllowed = 'copy';

    // Create a custom drag image
    const dragImage = e.target.cloneNode(true);
    dragImage.style.position = 'absolute';
    dragImage.style.top = '-1000px';
    dragImage.style.backgroundColor = '#3b82f6';
    dragImage.style.borderRadius = '8px';
    dragImage.style.padding = '8px';
    document.body.appendChild(dragImage);
    e.dataTransfer.setDragImage(dragImage, 20, 20);
    setTimeout(() => document.body.removeChild(dragImage), 0);
};

const handleDragEnd = () => {
    draggingTool.value = null;
};

const handleBack = () => {
    if (graphicsStore.isDirty) {
        if (confirm(t('graphics.editor.unsavedChanges'))) {
            router.back();
        }
    } else {
        router.back();
    }
};
</script>

<template>
    <div class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4">
        <!-- Left: Back button, template name and save status -->
        <div class="flex items-center space-x-4">
            <button
                @click="handleBack"
                class="text-gray-600 hover:text-gray-900 p-1"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </button>

            <div class="flex items-center gap-3">
                <span class="text-gray-900 font-medium">{{ template.name }}</span>

                <!-- Save status indicator -->
                <div v-if="saveStatus" class="flex items-center gap-1.5" :class="saveStatus.color">
                    <!-- Spinner -->
                    <svg v-if="saveStatus.icon === 'spinner'" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <!-- Warning -->
                    <svg v-else-if="saveStatus.icon === 'warning'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <!-- Check -->
                    <svg v-else-if="saveStatus.icon === 'check'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs font-medium">{{ saveStatus.text }}</span>
                </div>
            </div>
        </div>

        <!-- Center: Tools -->
        <div class="flex items-center space-x-1 bg-gray-100 rounded-lg p-1">
            <button
                v-for="tool in tools"
                :key="tool.id"
                @click="handleToolClick(tool.id)"
                :draggable="tool.draggable"
                @dragstart="(e) => handleDragStart(e, tool)"
                @dragend="handleDragEnd"
                :class="[
                    'p-2 rounded transition-colors',
                    tool.draggable ? 'cursor-grab active:cursor-grabbing' : '',
                    graphicsStore.tool === tool.id
                        ? 'bg-blue-600 text-white'
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200',
                    draggingTool === tool.id ? 'opacity-50' : ''
                ]"
                :title="`${t('graphics.' + tool.label)} (${tool.shortcut})${tool.draggable ? ' - ' + t('graphics.editor.dragToCanvas') : ''}`"
            >
                <!-- Cursor icon -->
                <svg v-if="tool.icon === 'cursor'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                </svg>
                <!-- Type icon -->
                <svg v-else-if="tool.icon === 'type'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                <!-- Square icon -->
                <svg v-else-if="tool.icon === 'square'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
                <!-- Circle icon -->
                <svg v-else-if="tool.icon === 'circle'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                </svg>
                <!-- Image icon -->
                <svg v-else-if="tool.icon === 'image'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </button>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center space-x-2">
            <!-- Zoom controls -->
            <div class="flex items-center space-x-1 mr-4">
                <button
                    @click="graphicsStore.zoomOut()"
                    class="p-1 text-gray-600 hover:text-gray-900"
                    :title="t('graphics.editor.zoomOut')"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                    </svg>
                </button>
                <span class="text-gray-700 text-sm w-12 text-center font-medium">
                    {{ Math.round(graphicsStore.zoom * 100) }}%
                </span>
                <button
                    @click="graphicsStore.zoomIn()"
                    class="p-1 text-gray-600 hover:text-gray-900"
                    :title="t('graphics.editor.zoomIn')"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                <button
                    @click="graphicsStore.resetZoom()"
                    class="p-1 text-gray-600 hover:text-gray-900 ml-1"
                    :title="t('graphics.editor.fitToScreen')"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                </button>
            </div>

            <!-- Undo/Redo -->
            <button
                @click="graphicsStore.undo()"
                :disabled="!graphicsStore.canUndo"
                class="p-2 text-gray-600 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('graphics.editor.undo')"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
            </button>
            <button
                @click="graphicsStore.redo()"
                :disabled="!graphicsStore.canRedo"
                class="p-2 text-gray-600 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('graphics.editor.redo')"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/>
                </svg>
            </button>

            <!-- Panel toggles -->
            <button
                @click="$emit('toggle-layers')"
                class="p-2 text-gray-600 hover:text-gray-900"
                :title="t('graphics.layers.title')"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </button>
            <button
                @click="$emit('toggle-properties')"
                class="p-2 text-gray-600 hover:text-gray-900"
                :title="t('graphics.layers.properties')"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
            </button>

            <!-- Fonts -->
            <button
                @click="$emit('open-fonts')"
                class="p-2 text-gray-600 hover:text-gray-900"
                :title="t('graphics.fonts.title')"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                </svg>
            </button>

            <!-- Separator -->
            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- AI Chat - wyróżniony przycisk -->
            <button
                @click="graphicsStore.toggleChatPanel()"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium text-sm transition-all',
                    graphicsStore.chatPanelOpen
                        ? 'shadow-md'
                        : 'border border-purple-200'
                ]"
                :style="{
                    backgroundColor: graphicsStore.chatPanelOpen ? '#9333ea' : '#faf5ff',
                    color: graphicsStore.chatPanelOpen ? 'white' : '#7c3aed'
                }"
                :title="t('graphics.aiChat.toggle')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <span>AI</span>
            </button>

            <!-- Library - wyróżniony przycisk -->
            <button
                @click="$emit('open-library')"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium text-sm bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition-all"
                :title="t('graphics.library.browse')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                </svg>
                <span>{{ t('graphics.library.titleShort') }}</span>
            </button>

            <!-- Add to Library (Admin only) -->
            <button
                v-if="authStore.isAdmin && !template.is_library"
                @click="$emit('add-to-library')"
                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg font-medium text-sm bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 transition-all"
                :title="t('graphics.library.addToLibrary')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>

            <!-- Library badge (if template is in library) -->
            <span
                v-if="template.is_library"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-lg"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ t('graphics.library.title') }}
            </span>

            <!-- Separator -->
            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Save -->
            <Button
                size="md"
                :loading="graphicsStore.saving"
                @click="$emit('save')"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                {{ graphicsStore.saving ? t('graphics.editor.saving') : t('graphics.editor.save') }}
            </Button>

            <!-- Export -->
            <Button
                size="md"
                variant="secondary"
                @click="$emit('export')"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                {{ t('graphics.editor.export') }}
            </Button>
        </div>
    </div>
</template>
