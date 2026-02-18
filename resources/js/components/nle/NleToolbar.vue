<template>
    <div class="flex items-center justify-between h-12 px-4 bg-gray-900 border-b border-gray-700 shrink-0">
        <!-- Left: Back + Title + Workspace Preset -->
        <div class="flex items-center gap-3">
            <button
                @click="$router.back()"
                class="text-gray-400 hover:text-white transition-colors"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <input
                v-if="store.project"
                v-model="title"
                @blur="updateTitle"
                @keydown.enter="($event.target).blur()"
                class="bg-transparent text-white text-sm font-medium border-none focus:outline-none focus:ring-1 focus:ring-blue-500 rounded px-2 py-1 max-w-[200px]"
            />

            <!-- Workspace Preset Dropdown -->
            <div class="relative" ref="presetRef">
                <button
                    @click="showPresets = !showPresets"
                    class="flex items-center gap-1.5 px-2 py-1 text-[11px] text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                    </svg>
                    <span>{{ currentPresetLabel }}</span>
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div
                    v-if="showPresets"
                    class="absolute top-full left-0 mt-1 bg-gray-800 border border-gray-600 rounded shadow-lg z-20 min-w-[200px]"
                >
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        @click="applyPreset(preset)"
                        class="flex items-center justify-between w-full px-3 py-2 text-xs hover:bg-gray-700 transition-colors"
                        :class="isActivePreset(preset) ? 'text-blue-400' : 'text-gray-300'"
                    >
                        <span>{{ t('nle.toolbar.preset.' + preset.key) }}</span>
                        <span class="text-gray-500 text-[10px]">{{ preset.width }}×{{ preset.height }}</span>
                    </button>

                    <!-- Custom -->
                    <div class="border-t border-gray-700 px-3 py-2">
                        <p class="text-[10px] text-gray-500 mb-1.5">{{ t('nle.toolbar.preset.custom') }}</p>
                        <div class="flex items-center gap-1.5">
                            <input
                                v-model.number="customWidth"
                                type="number"
                                min="100"
                                max="7680"
                                class="w-16 px-1.5 py-1 text-[11px] bg-gray-900 border border-gray-600 rounded text-white focus:outline-none focus:border-blue-500"
                                :placeholder="t('nle.inspector.width')"
                                @keydown.stop
                            />
                            <span class="text-gray-500 text-[10px]">×</span>
                            <input
                                v-model.number="customHeight"
                                type="number"
                                min="100"
                                max="7680"
                                class="w-16 px-1.5 py-1 text-[11px] bg-gray-900 border border-gray-600 rounded text-white focus:outline-none focus:border-blue-500"
                                :placeholder="t('nle.inspector.height')"
                                @keydown.stop
                            />
                            <button
                                @click="applyCustomSize"
                                :disabled="!customWidth || !customHeight"
                                class="px-2 py-1 text-[10px] bg-blue-600 hover:bg-blue-500 text-white rounded disabled:opacity-40 transition-colors"
                            >
                                {{ t('nle.toolbar.preset.apply') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Center: Undo/Redo -->
        <div class="flex items-center gap-1">
            <button
                @click="$emit('undo')"
                :disabled="!canUndo"
                class="p-2 text-gray-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :title="t('nle.toolbar.undo')"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4m-4 4l4 4" />
                </svg>
            </button>
            <button
                @click="$emit('redo')"
                :disabled="!canRedo"
                class="p-2 text-gray-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :title="t('nle.toolbar.redo')"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4" />
                </svg>
            </button>
        </div>

        <!-- Right: Save + Render -->
        <div class="flex items-center gap-2">
            <span v-if="store.saving" class="text-xs text-gray-500">{{ t('nle.toolbar.saving') }}</span>
            <span v-else-if="store.isDirty" class="text-xs text-yellow-500">{{ t('nle.toolbar.unsaved') }}</span>

            <button
                @click="$emit('save')"
                :disabled="store.saving || !store.isDirty"
                class="px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-700 hover:bg-gray-600 rounded disabled:opacity-40 transition-colors"
            >
                {{ t('nle.toolbar.save') }}
            </button>

            <button
                @click="$emit('render')"
                class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded transition-colors"
            >
                {{ t('nle.toolbar.render') }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const { t } = useI18n();
const store = useVideoEditorStore();

defineProps({
    canUndo: { type: Boolean, default: false },
    canRedo: { type: Boolean, default: false },
});

defineEmits(['undo', 'redo', 'save', 'render']);

const title = ref('');
const showPresets = ref(false);
const presetRef = ref(null);
const customWidth = ref(1080);
const customHeight = ref(1920);

const presets = [
    { key: 'reel', width: 1080, height: 1920 },
    { key: 'youtube', width: 1920, height: 1080 },
    { key: 'square', width: 1080, height: 1080 },
    { key: 'youtube4k', width: 3840, height: 2160 },
];

const currentPresetLabel = computed(() => {
    const w = store.compositionWidth;
    const h = store.compositionHeight;
    const match = presets.find(p => p.width === w && p.height === h);
    if (match) return t('nle.toolbar.preset.' + match.key);
    return `${w}×${h}`;
});

function isActivePreset(preset) {
    return store.compositionWidth === preset.width && store.compositionHeight === preset.height;
}

function applyPreset(preset) {
    store.setCompositionSize(preset.width, preset.height);
    showPresets.value = false;
}

function applyCustomSize() {
    if (customWidth.value >= 100 && customHeight.value >= 100) {
        store.setCompositionSize(customWidth.value, customHeight.value);
        showPresets.value = false;
    }
}

watch(() => store.project?.title, (val) => {
    if (val) title.value = val;
}, { immediate: true });

function updateTitle() {
    if (title.value && title.value !== store.project?.title) {
        store.project.title = title.value;
        store.markDirty();
    }
}

function handleClickOutside(event) {
    if (presetRef.value && !presetRef.value.contains(event.target)) {
        showPresets.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>
