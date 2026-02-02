<script setup>
import { computed } from 'vue';
import { useGraphicsStore } from '@/stores/graphics';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    layer: {
        type: Object,
        required: true,
    },
    depth: {
        type: Number,
        default: 0,
    },
    draggedLayerId: {
        type: [String, Number],
        default: null,
    },
    dragOverLayerId: {
        type: [String, Number],
        default: null,
    },
});

const emit = defineEmits([
    'select',
    'toggle-visibility',
    'toggle-lock',
    'toggle-group',
    'delete',
    'dragstart',
    'dragover',
    'dragleave',
    'drop',
    'dragend',
]);

const graphicsStore = useGraphicsStore();

const isSelected = computed(() => graphicsStore.selectedLayerId === props.layer.id);
const isExpanded = computed(() => props.layer.properties?.expanded ?? true);
const isGroup = computed(() => props.layer.type === 'group');
const hasChildren = computed(() => isGroup.value && props.layer.children?.length > 0);

const isEffectivelyVisible = computed(() => {
    return graphicsStore.isLayerEffectivelyVisible(props.layer.id);
});

const reversedChildren = computed(() => {
    if (!props.layer.children) return [];
    return [...props.layer.children].reverse();
});

// Semantic tag helpers
const contentTagValues = ['header', 'subtitle', 'paragraph', 'url', 'social_handle', 'main_image', 'logo', 'cta'];
const styleTagValues = ['primary_color', 'secondary_color', 'text_primary_color', 'text_secondary_color'];

const semanticTags = computed(() => {
    return props.layer.properties?.semanticTags || [];
});

const hasContentTag = computed(() => {
    return semanticTags.value.some(t => contentTagValues.includes(t));
});

const hasStyleTag = computed(() => {
    return semanticTags.value.some(t => styleTagValues.includes(t));
});

const hasSemanticTag = computed(() => {
    return hasContentTag.value || hasStyleTag.value;
});

// Determine tag indicator category
const tagCategory = computed(() => {
    if (hasContentTag.value && hasStyleTag.value) return 'both';
    if (hasContentTag.value) return 'content';
    if (hasStyleTag.value) return 'style';
    return null;
});

// Get tag names for tooltip
const tagTooltip = computed(() => {
    const tags = semanticTags.value;
    if (tags.length === 0) return '';

    const tagLabels = tags.map(tag => {
        const key = `graphics.semantic_tags.${tag}`;
        return t(key);
    });

    return tagLabels.join(', ');
});

const getIcon = () => {
    switch (props.layer.type) {
        case 'text': return 'type';
        case 'image': return 'image';
        case 'rectangle': return 'square';
        case 'ellipse': return 'circle';
        case 'group': return 'folder';
        case 'textbox': return 'square-text';
        case 'line': return 'line';
        default: return 'square';
    }
};

const paddingLeft = computed(() => (props.depth * 16 + 8) + 'px');
</script>

<template>
    <div>
        <!-- Layer row -->
        <div
            draggable="true"
            :class="[
                'px-2 py-1.5 flex items-center space-x-1.5 cursor-pointer transition-colors border-l-3',
                isSelected
                    ? 'bg-blue-50 border-l-blue-500'
                    : hasSemanticTag
                        ? tagCategory === 'both'
                            ? 'hover:bg-gray-50 border-l-purple-500'
                            : tagCategory === 'content'
                                ? 'hover:bg-gray-50 border-l-blue-400'
                                : 'hover:bg-gray-50 border-l-green-500'
                        : 'hover:bg-gray-50 border-l-transparent',
                draggedLayerId === layer.id ? 'opacity-50' : '',
                dragOverLayerId === layer.id && draggedLayerId !== layer.id ? 'border-t-2 border-t-blue-500' : ''
            ]"
            :style="{ paddingLeft }"
            :title="tagTooltip"
            @click="emit('select', layer)"
            @dragstart="(e) => emit('dragstart', e, layer)"
            @dragover="(e) => emit('dragover', e, layer)"
            @dragleave="emit('dragleave')"
            @drop="(e) => emit('drop', e, layer)"
            @dragend="emit('dragend')"
        >
            <!-- Group expand/collapse toggle -->
            <button
                v-if="isGroup"
                @click.stop="emit('toggle-group', layer)"
                class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-gray-400 hover:text-gray-600"
            >
                <svg v-if="isExpanded" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
            <div v-else class="w-4"></div>

            <!-- Type icon -->
            <div :class="[
                'flex-shrink-0 w-5 h-5 flex items-center justify-center rounded',
                isSelected ? 'text-blue-600' : 'text-gray-500'
            ]">
                <!-- Folder icon for groups -->
                <svg v-if="getIcon() === 'folder'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <!-- Type icon -->
                <svg v-else-if="getIcon() === 'type'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                <!-- Square icon -->
                <svg v-else-if="getIcon() === 'square'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
                <!-- Circle icon -->
                <svg v-else-if="getIcon() === 'circle'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                </svg>
                <!-- Image icon -->
                <svg v-else-if="getIcon() === 'image'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <!-- Line icon -->
                <svg v-else-if="getIcon() === 'line'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 20L20 4"/>
                </svg>
                <!-- Default icon -->
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
            </div>

            <!-- Name -->
            <span
                :class="[
                    'flex-1 min-w-0 text-xs font-medium truncate flex items-center gap-1',
                    isEffectivelyVisible ? 'text-gray-900' : 'text-gray-400'
                ]"
            >
                {{ layer.name }}
                <!-- Semantic tag indicator -->
                <span
                    v-if="hasSemanticTag"
                    :class="[
                        'inline-flex items-center justify-center w-3.5 h-3.5 rounded-full flex-shrink-0',
                        tagCategory === 'both' ? 'bg-purple-100 text-purple-600' :
                        tagCategory === 'content' ? 'bg-blue-100 text-blue-600' :
                        'bg-green-100 text-green-600'
                    ]"
                    :title="tagTooltip"
                >
                    <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </span>
            </span>

            <!-- Actions -->
            <div class="flex items-center space-x-0.5">
                <!-- Visibility toggle -->
                <button
                    @click.stop="emit('toggle-visibility', layer)"
                    :class="[
                        'p-1 rounded hover:bg-gray-200 transition-colors',
                        layer.visible ? 'text-gray-600 hover:text-gray-900' : 'text-gray-400'
                    ]"
                >
                    <svg v-if="layer.visible" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>

                <!-- Lock toggle (not for groups) -->
                <button
                    v-if="!isGroup"
                    @click.stop="emit('toggle-lock', layer)"
                    :class="[
                        'p-1 rounded hover:bg-gray-200 transition-colors',
                        layer.locked ? 'text-yellow-600' : 'text-gray-600 hover:text-gray-900'
                    ]"
                >
                    <svg v-if="layer.locked" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                    </svg>
                </button>

                <!-- Delete -->
                <button
                    @click.stop="emit('delete', layer)"
                    class="p-1 rounded hover:bg-gray-200 text-gray-600 hover:text-red-600 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Children (if group is expanded) -->
        <div v-if="isGroup && isExpanded && hasChildren">
            <LayerTreeItem
                v-for="child in reversedChildren"
                :key="child.id"
                :layer="child"
                :depth="depth + 1"
                :draggedLayerId="draggedLayerId"
                :dragOverLayerId="dragOverLayerId"
                @select="(l) => emit('select', l)"
                @toggle-visibility="(l) => emit('toggle-visibility', l)"
                @toggle-lock="(l) => emit('toggle-lock', l)"
                @toggle-group="(l) => emit('toggle-group', l)"
                @delete="(l) => emit('delete', l)"
                @dragstart="(e, l) => emit('dragstart', e, l)"
                @dragover="(e, l) => emit('dragover', e, l)"
                @dragleave="emit('dragleave')"
                @drop="(e, l) => emit('drop', e, l)"
                @dragend="emit('dragend')"
            />
        </div>
    </div>
</template>
