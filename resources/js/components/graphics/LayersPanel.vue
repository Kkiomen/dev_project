<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import LayerTreeItem from './LayerTreeItem.vue';

const { t } = useI18n();
const graphicsStore = useGraphicsStore();

// Use layer tree for hierarchical display (reversed for top-to-bottom order)
const layerTree = computed(() => {
    return [...graphicsStore.layerTree].reverse();
});

// Drag and drop state
const draggedLayerId = ref(null);
const dragOverLayerId = ref(null);

const handleDragStart = (e, layer) => {
    draggedLayerId.value = layer.id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', layer.id);
};

const handleDragOver = (e, layer) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    dragOverLayerId.value = layer.id;
};

const handleDragLeave = () => {
    dragOverLayerId.value = null;
};

const handleDrop = async (e, targetLayer) => {
    e.preventDefault();

    const draggedId = draggedLayerId.value;
    if (!draggedId || draggedId === targetLayer.id) {
        draggedLayerId.value = null;
        dragOverLayerId.value = null;
        return;
    }

    const draggedLayer = graphicsStore.layers.find(l => l.id === draggedId);
    if (!draggedLayer) return;

    const newPosition = targetLayer.position;

    try {
        await graphicsStore.reorderLayer(draggedId, newPosition);
    } catch (error) {
        console.error('Failed to reorder layer:', error);
    }

    draggedLayerId.value = null;
    dragOverLayerId.value = null;
};

const handleDragEnd = () => {
    draggedLayerId.value = null;
    dragOverLayerId.value = null;
};

const handleSelect = (layer) => {
    graphicsStore.selectLayer(layer.id);
};

const handleToggleVisibility = (layer) => {
    graphicsStore.toggleLayerVisibility(layer.id);
};

const handleToggleLock = (layer) => {
    graphicsStore.updateLayerLocally(layer.id, {
        locked: !layer.locked,
    });
};

const handleDelete = (layer) => {
    graphicsStore.deleteLayer(layer.id);
};

const handleToggleGroup = (layer) => {
    graphicsStore.toggleGroupExpanded(layer.id);
};
</script>

<template>
    <div class="flex flex-col h-full bg-white">
        <!-- Header -->
        <div class="px-3 py-2.5 border-b border-gray-200">
            <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                {{ t('graphics.layers.title') }}
            </span>
        </div>

        <!-- Layers list -->
        <div class="flex-1 overflow-y-auto">
            <!-- Empty state -->
            <div
                v-if="graphicsStore.layers.length === 0"
                class="p-4 text-center text-gray-400 text-sm"
            >
                <p>{{ t('graphics.layers.noLayers') }}</p>
                <p class="mt-1">{{ t('graphics.layers.addFirstLayer') }}</p>
            </div>

            <!-- Layers tree -->
            <div v-else class="py-1">
                <LayerTreeItem
                    v-for="layer in layerTree"
                    :key="layer.id"
                    :layer="layer"
                    :depth="0"
                    :draggedLayerId="draggedLayerId"
                    :dragOverLayerId="dragOverLayerId"
                    @select="handleSelect"
                    @toggle-visibility="handleToggleVisibility"
                    @toggle-lock="handleToggleLock"
                    @toggle-group="handleToggleGroup"
                    @delete="handleDelete"
                    @dragstart="handleDragStart"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                    @dragend="handleDragEnd"
                />
            </div>
        </div>
    </div>
</template>
