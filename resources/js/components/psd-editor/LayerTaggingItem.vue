<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({
    name: 'LayerTaggingItem',
});

const { t } = useI18n();

const props = defineProps({
    layer: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    expandedGroups: { type: Set, required: true },
    semanticTagOptions: { type: Array, required: true },
});

const emit = defineEmits(['toggle-variant', 'set-tag', 'toggle-expand', 'variant-selected']);

const isGroup = computed(() => props.layer.type === 'group' || props.layer.children?.length > 0);
const isExpanded = computed(() => props.expandedGroups.has(props.layer._path));
const hasChildren = computed(() => props.layer.children?.length > 0);
const isVariant = computed(() => props.layer._is_variant);
const semanticTag = computed(() => props.layer._semantic_tag || '');
const paddingLeft = computed(() => (props.depth * 16 + 8) + 'px');

const handleVariantClick = () => {
    emit('toggle-variant', props.layer._path, isGroup.value);
    if (!isVariant.value && isGroup.value) {
        emit('variant-selected', props.layer._path);
    }
};

const handleTagChange = (e) => {
    emit('set-tag', props.layer._path, e.target.value);
};

const handleToggleExpand = () => {
    emit('toggle-expand', props.layer._path);
};
</script>

<template>
    <div>
        <div
            :class="[
                'px-2 py-1.5 flex items-center gap-1.5 border-l-2 transition-colors',
                isVariant ? 'bg-purple-50 border-l-purple-500' : 'hover:bg-gray-50 border-l-transparent'
            ]"
            :style="{ paddingLeft }"
        >
            <!-- Expand/collapse for groups -->
            <button
                v-if="hasChildren"
                @click="handleToggleExpand"
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

            <!-- Variant checkbox (only for groups) -->
            <label v-if="isGroup" class="flex items-center flex-shrink-0">
                <input
                    type="checkbox"
                    :checked="isVariant"
                    @change="handleVariantClick"
                    class="w-3.5 h-3.5 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                />
            </label>
            <div v-else class="w-3.5"></div>

            <!-- Type icon -->
            <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-gray-500">
                <svg v-if="layer.type === 'group'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <svg v-else-if="layer.type === 'text'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                <svg v-else-if="layer.type === 'image'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
            </div>

            <!-- Layer name -->
            <span class="flex-1 min-w-0 text-xs font-medium text-gray-900 truncate">
                {{ layer.name }}
            </span>

            <!-- Semantic tag selector (not for groups) -->
            <select
                v-if="!isGroup"
                :value="semanticTag"
                @change="handleTagChange"
                class="flex-shrink-0 text-[10px] px-1 py-0.5 border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 max-w-[80px]"
            >
                <option v-for="opt in semanticTagOptions" :key="opt.value" :value="opt.value">
                    {{ t(opt.label) }}
                </option>
            </select>
        </div>

        <!-- Children -->
        <div v-if="hasChildren && isExpanded">
            <LayerTaggingItem
                v-for="child in layer.children"
                :key="child._path"
                :layer="child"
                :depth="depth + 1"
                :expanded-groups="expandedGroups"
                :semantic-tag-options="semanticTagOptions"
                @toggle-variant="(path, isG) => emit('toggle-variant', path, isG)"
                @set-tag="(path, tag) => emit('set-tag', path, tag)"
                @toggle-expand="(path) => emit('toggle-expand', path)"
                @variant-selected="(path) => emit('variant-selected', path)"
            />
        </div>
    </div>
</template>
