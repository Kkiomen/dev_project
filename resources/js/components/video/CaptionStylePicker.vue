<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    currentStyle: { type: String, default: 'clean' },
    captionSettings: { type: Object, default: () => ({}) },
    styles: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:style', 'update:settings']);

const { t } = useI18n();

const highlightKeywords = ref(props.captionSettings.highlight_keywords ?? false);
const position = ref(props.captionSettings.position ?? 'bottom');
const fontSize = ref(props.captionSettings.font_size ?? 48);

const builtInStyles = [
    {
        id: 'clean',
        name: 'Clean',
        description: 'videoEditor.captionStyles.cleanDesc',
        previewBg: 'bg-gray-800',
        previewText: 'text-white',
    },
    {
        id: 'hormozi',
        name: 'Hormozi',
        description: 'videoEditor.captionStyles.hormoziDesc',
        previewBg: 'bg-gray-900',
        previewText: 'text-white font-black uppercase',
    },
    {
        id: 'mrbeast',
        name: 'MrBeast',
        description: 'videoEditor.captionStyles.mrbeastDesc',
        previewBg: 'bg-gray-900',
        previewText: 'text-yellow-400 font-black uppercase',
    },
    {
        id: 'bold',
        name: 'Bold',
        description: 'videoEditor.captionStyles.boldDesc',
        previewBg: 'bg-gray-800',
        previewText: 'text-white font-black',
    },
    {
        id: 'neon',
        name: 'Neon',
        description: 'videoEditor.captionStyles.neonDesc',
        previewBg: 'bg-gray-950',
        previewText: 'text-cyan-400 font-bold',
    },
];

function selectStyle(styleId) {
    emit('update:style', styleId);
}

function updateSettings() {
    emit('update:settings', {
        highlight_keywords: highlightKeywords.value,
        position: position.value,
        font_size: fontSize.value,
    });
}
</script>

<template>
    <div class="p-4 space-y-6">
        <!-- Style Grid -->
        <div>
            <h3 class="text-sm font-medium text-gray-700 mb-3">{{ t('videoEditor.captionStyles.title') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <button
                    v-for="style in builtInStyles"
                    :key="style.id"
                    @click="selectStyle(style.id)"
                    :class="currentStyle === style.id
                        ? 'ring-2 ring-blue-500 border-blue-500'
                        : 'border-gray-200 hover:border-gray-300'"
                    class="border rounded-xl overflow-hidden text-left transition-all"
                >
                    <!-- Preview -->
                    <div :class="style.previewBg" class="px-4 py-6 flex items-center justify-center">
                        <span :class="style.previewText" class="text-lg">
                            {{ t('videoEditor.captionStyles.sampleText') }}
                        </span>
                    </div>
                    <!-- Label -->
                    <div class="p-3">
                        <p class="text-sm font-medium text-gray-900">{{ style.name }}</p>
                        <p class="text-xs text-gray-500">{{ t(style.description) }}</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Settings -->
        <div class="space-y-4">
            <h3 class="text-sm font-medium text-gray-700">{{ t('videoEditor.captionStyles.settings') }}</h3>

            <!-- Position -->
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ t('videoEditor.captionStyles.position') }}</label>
                <div class="flex gap-2">
                    <button
                        v-for="pos in ['bottom', 'center', 'top']"
                        :key="pos"
                        @click="position = pos; updateSettings()"
                        :class="position === pos ? 'bg-blue-100 text-blue-700 border-blue-300' : 'bg-white text-gray-600 border-gray-200'"
                        class="px-3 py-1.5 text-xs border rounded-lg transition-colors capitalize"
                    >
                        {{ t(`videoEditor.captionStyles.position_${pos}`) }}
                    </button>
                </div>
            </div>

            <!-- Font Size -->
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    {{ t('videoEditor.captionStyles.fontSize') }}: {{ fontSize }}px
                </label>
                <input
                    v-model.number="fontSize"
                    @change="updateSettings"
                    type="range"
                    min="24"
                    max="96"
                    step="2"
                    class="w-full"
                />
            </div>

            <!-- Highlight Keywords -->
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    v-model="highlightKeywords"
                    @change="updateSettings"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="text-sm text-gray-700">{{ t('videoEditor.captionStyles.highlightKeywords') }}</span>
            </label>
        </div>
    </div>
</template>
