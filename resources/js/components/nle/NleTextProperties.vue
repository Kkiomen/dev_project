<template>
    <div class="space-y-4">
        <h4 class="text-xs font-medium text-gray-300">{{ t('nle.inspector.textProperties') }}</h4>

        <!-- Text content -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.text') }}</label>
            <textarea
                :value="element.text"
                @input="update('text', $event.target.value)"
                rows="3"
                class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1.5 border border-gray-600 focus:border-blue-500 focus:outline-none resize-none"
            />
        </div>

        <!-- Font Family -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fontFamily') }}</label>
            <select
                :value="element.font_family || 'sans-serif'"
                @change="update('font_family', $event.target.value)"
                class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
            >
                <option value="sans-serif">Sans Serif</option>
                <option value="serif">Serif</option>
                <option value="monospace">Monospace</option>
                <option value="Arial">Arial</option>
                <option value="Helvetica">Helvetica</option>
                <option value="Georgia">Georgia</option>
            </select>
        </div>

        <!-- Font Size + Weight -->
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fontSize') }}</label>
                <input
                    type="number"
                    :value="element.font_size || 48"
                    @input="update('font_size', parseInt($event.target.value) || 48)"
                    min="8"
                    max="256"
                    class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                />
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fontWeight') }}</label>
                <select
                    :value="element.font_weight || 'bold'"
                    @change="update('font_weight', $event.target.value)"
                    class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                >
                    <option value="normal">Normal</option>
                    <option value="bold">Bold</option>
                    <option value="100">Thin</option>
                    <option value="300">Light</option>
                    <option value="500">Medium</option>
                    <option value="700">Bold</option>
                    <option value="900">Black</option>
                </select>
            </div>
        </div>

        <!-- Color -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.color') }}</label>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    :value="element.color || '#ffffff'"
                    @input="update('color', $event.target.value)"
                    class="w-8 h-8 rounded cursor-pointer border-0"
                />
                <input
                    :value="element.color || '#ffffff'"
                    @input="update('color', $event.target.value)"
                    class="flex-1 bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                />
            </div>
        </div>

        <!-- Text Align -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.textAlign') }}</label>
            <div class="flex gap-1">
                <button
                    v-for="align in ['left', 'center', 'right']"
                    :key="align"
                    @click="update('text_align', align)"
                    class="flex-1 py-1.5 text-xs rounded transition-colors"
                    :class="(element.text_align || 'center') === align
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-700 text-gray-400 hover:bg-gray-600'"
                >
                    {{ align }}
                </button>
            </div>
        </div>

        <!-- Stroke -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.stroke') }}</label>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    :value="element.stroke_color || '#000000'"
                    @input="update('stroke_color', $event.target.value)"
                    class="w-8 h-8 rounded cursor-pointer border-0"
                />
                <input
                    type="number"
                    :value="element.stroke_width || 0"
                    @input="update('stroke_width', parseInt($event.target.value) || 0)"
                    min="0"
                    max="20"
                    class="flex-1 bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const { t } = useI18n();
const store = useVideoEditorStore();

const props = defineProps({
    element: { type: Object, required: true },
});

function update(key, value) {
    store.updateElementProperty(props.element.id, key, value);
}
</script>
