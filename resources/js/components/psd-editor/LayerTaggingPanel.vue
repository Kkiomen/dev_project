<script setup>
import { computed } from 'vue';
import { usePsdEditorStore } from '@/stores/psdEditor';
import { useI18n } from 'vue-i18n';
import LayerTaggingItem from './LayerTaggingItem.vue';

const { t } = useI18n();
const store = usePsdEditorStore();

const emit = defineEmits(['variant-selected']);

// Semantic tag options
const semanticTagOptions = [
    { value: '', label: 'psd_editor.layers.no_tag' },
    { value: 'header', label: 'graphics.semantic_tags.header' },
    { value: 'subtitle', label: 'graphics.semantic_tags.subtitle' },
    { value: 'paragraph', label: 'graphics.semantic_tags.paragraph' },
    { value: 'url', label: 'graphics.semantic_tags.url' },
    { value: 'social_handle', label: 'graphics.semantic_tags.social_handle' },
    { value: 'main_image', label: 'graphics.semantic_tags.main_image' },
    { value: 'logo', label: 'graphics.semantic_tags.logo' },
    { value: 'cta', label: 'graphics.semantic_tags.cta' },
    { value: 'primary_color', label: 'graphics.semantic_tags.primary_color' },
    { value: 'secondary_color', label: 'graphics.semantic_tags.secondary_color' },
];

// Count variants and groups
const variantCount = computed(() => store.variants.length);
const groupCount = computed(() => {
    let count = 0;
    const countGroups = (layers) => {
        for (const layer of layers) {
            if (layer.type === 'group' || layer.children?.length > 0) {
                count++;
            }
            if (layer.children) {
                countGroups(layer.children);
            }
        }
    };
    countGroups(store.layerTree);
    return count;
});

const toggleVariant = (layerPath, isGroup) => {
    if (!isGroup) return;
    const currentValue = store.tags[layerPath]?.is_variant || false;
    store.setLayerVariant(layerPath, !currentValue);

    if (!currentValue) {
        emit('variant-selected', layerPath);
    }
};

const setTag = (layerPath, tag) => {
    store.setLayerTag(layerPath, tag);
};

const toggleExpand = (path) => {
    store.toggleGroupExpanded(path);
};

// Mark all top-level groups as variants
const markAllTopLevelAsVariants = () => {
    for (const layer of store.layerTree) {
        if (layer.type === 'group' || layer.children?.length > 0) {
            if (!store.tags[layer._path]?.is_variant) {
                store.setLayerVariant(layer._path, true);
            }
        }
    }
};

const saveTags = async () => {
    try {
        await store.saveTags();
    } catch (error) {
        console.error('Failed to save tags:', error);
    }
};
</script>

<template>
    <div class="h-full flex flex-col bg-white">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-gray-900">
                    {{ t('psd_editor.layers.title') }}
                </h3>
                <div class="flex items-center gap-1">
                    <button
                        @click="store.expandAll()"
                        class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
                        :title="t('psd_editor.layers.expand_all')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                    </button>
                    <button
                        @click="store.collapseAll()"
                        class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
                        :title="t('psd_editor.layers.collapse_all')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Variant counter and quick action -->
            <div v-if="store.layerTree.length > 0" class="flex items-center justify-between text-xs">
                <span class="text-gray-500">
                    {{ t('psd_editor.layers.variants_selected', { count: variantCount }) }}
                </span>
                <button
                    v-if="groupCount > 0 && variantCount < groupCount"
                    @click="markAllTopLevelAsVariants"
                    class="text-purple-600 hover:text-purple-700 font-medium"
                >
                    {{ t('psd_editor.layers.mark_all_variants') }}
                </button>
            </div>
        </div>

        <!-- Help tip -->
        <div v-if="store.layerTree.length > 0 && variantCount === 0" class="px-4 py-2 bg-blue-50 border-b border-blue-100">
            <p class="text-xs text-blue-700">
                <svg class="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ t('psd_editor.layers.help_tip') }}
            </p>
        </div>

        <!-- Layer tree -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading state -->
            <div v-if="store.parsingLoading" class="p-4 text-center text-sm text-gray-500">
                <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ t('psd_editor.layers.loading') }}
            </div>

            <!-- No file selected -->
            <div v-else-if="!store.currentFile" class="p-4 text-center text-sm text-gray-500">
                {{ t('psd_editor.toolbar.select_file') }}
            </div>

            <!-- No layers -->
            <div v-else-if="store.layerTree.length === 0" class="p-4 text-center text-sm text-gray-500">
                {{ t('psd_editor.layers.no_layers') }}
            </div>

            <!-- Layer tree -->
            <div v-else class="py-1">
                <LayerTaggingItem
                    v-for="layer in store.layerTree"
                    :key="layer._path"
                    :layer="layer"
                    :depth="0"
                    :expanded-groups="store.expandedGroups"
                    :semantic-tag-options="semanticTagOptions"
                    @toggle-variant="toggleVariant"
                    @set-tag="setTag"
                    @toggle-expand="toggleExpand"
                    @variant-selected="(path) => emit('variant-selected', path)"
                />
            </div>
        </div>

        <!-- Save button -->
        <div class="px-4 py-3 border-t border-gray-200">
            <button
                @click="saveTags"
                :disabled="store.tagsSaving"
                class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-md transition-colors flex items-center justify-center gap-2"
            >
                <svg v-if="store.tagsSaving" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ t('psd_editor.messages.tags_saved') }}
            </button>
        </div>
    </div>
</template>
