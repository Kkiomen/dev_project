<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';

const { t } = useI18n();
const graphicsStore = useGraphicsStore();

const reversedLayers = computed(() => {
    return [...graphicsStore.sortedLayers].reverse();
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

    // Calculate new position based on drop target
    // Since layers are reversed in display, we need to swap the position
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

const getLayerIcon = (type) => {
    switch (type) {
        case 'text':
            return 'type';
        case 'image':
            return 'image';
        case 'rectangle':
            return 'square';
        case 'ellipse':
            return 'circle';
        default:
            return 'square';
    }
};

const handleSelect = (layer) => {
    graphicsStore.selectLayer(layer.id);
};

const handleToggleVisibility = (layer) => {
    graphicsStore.updateLayerLocally(layer.id, {
        visible: !layer.visible,
    });
};

const handleToggleLock = (layer) => {
    graphicsStore.updateLayerLocally(layer.id, {
        locked: !layer.locked,
    });
};

const handleDelete = (layer) => {
    graphicsStore.deleteLayer(layer.id);
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

            <!-- Layers -->
            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="layer in reversedLayers"
                    :key="layer.id"
                    draggable="true"
                    :class="[
                        'px-3 py-2.5 flex items-center space-x-2.5 cursor-pointer transition-colors border-l-2',
                        graphicsStore.selectedLayerId === layer.id
                            ? 'bg-blue-50 border-l-blue-500'
                            : 'hover:bg-gray-50 border-l-transparent',
                        draggedLayerId === layer.id ? 'opacity-50' : '',
                        dragOverLayerId === layer.id && draggedLayerId !== layer.id ? 'border-t-2 border-t-blue-500' : ''
                    ]"
                    @click="handleSelect(layer)"
                    @dragstart="(e) => handleDragStart(e, layer)"
                    @dragover="(e) => handleDragOver(e, layer)"
                    @dragleave="handleDragLeave"
                    @drop="(e) => handleDrop(e, layer)"
                    @dragend="handleDragEnd"
                >
                    <!-- Type icon -->
                    <div :class="[
                        'flex-shrink-0 w-5 h-5 flex items-center justify-center rounded',
                        graphicsStore.selectedLayerId === layer.id ? 'text-blue-600' : 'text-gray-500'
                    ]">
                        <!-- Type icon -->
                        <svg v-if="getLayerIcon(layer.type) === 'type'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
                        </svg>
                        <!-- Square icon -->
                        <svg v-else-if="getLayerIcon(layer.type) === 'square'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                        </svg>
                        <!-- Circle icon -->
                        <svg v-else-if="getLayerIcon(layer.type) === 'circle'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                        </svg>
                        <!-- Image icon -->
                        <svg v-else-if="getLayerIcon(layer.type) === 'image'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>

                    <!-- Name -->
                    <span
                        :class="[
                            'flex-1 text-xs font-medium truncate',
                            layer.visible ? 'text-gray-900' : 'text-gray-400'
                        ]"
                    >
                        {{ layer.name }}
                    </span>

                    <!-- Actions -->
                    <div class="flex items-center space-x-0.5">
                        <!-- Visibility toggle -->
                        <button
                            @click.stop="handleToggleVisibility(layer)"
                            :class="[
                                'p-1 rounded hover:bg-gray-200 transition-colors',
                                layer.visible ? 'text-gray-600 hover:text-gray-900' : 'text-gray-400'
                            ]"
                            :title="t('graphics.layers.visible')"
                        >
                            <svg v-if="layer.visible" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>

                        <!-- Lock toggle -->
                        <button
                            @click.stop="handleToggleLock(layer)"
                            :class="[
                                'p-1 rounded hover:bg-gray-200 transition-colors',
                                layer.locked ? 'text-yellow-600' : 'text-gray-600 hover:text-gray-900'
                            ]"
                            :title="t('graphics.layers.locked')"
                        >
                            <svg v-if="layer.locked" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                            </svg>
                        </button>

                        <!-- Delete -->
                        <button
                            @click.stop="handleDelete(layer)"
                            class="p-1 rounded hover:bg-gray-200 text-gray-600 hover:text-red-600 transition-colors"
                            :title="t('graphics.layers.delete')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
