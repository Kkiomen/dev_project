<template>
    <div class="space-y-4">
        <h4 class="text-xs font-medium text-gray-300">{{ t('nle.inspector.videoProperties') }}</h4>

        <!-- Source Trimming -->
        <div>
            <h5 class="text-[10px] text-gray-500 mb-1.5">{{ t('nle.inspector.trimming') }}</h5>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.trimStart') }}</label>
                    <input
                        type="number"
                        :value="element.trim_start || 0"
                        @input="update('trim_start', Math.max(0, parseFloat($event.target.value) || 0))"
                        step="0.1"
                        min="0"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.trimEnd') }}</label>
                    <input
                        type="number"
                        :value="element.trim_end || 0"
                        @input="update('trim_end', Math.max(0, parseFloat($event.target.value) || 0))"
                        step="0.1"
                        min="0"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Fit Mode -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fit') }}</label>
            <select
                :value="element.fit || 'cover'"
                @change="update('fit', $event.target.value)"
                class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
            >
                <option value="cover">Cover</option>
                <option value="contain">Contain</option>
            </select>
        </div>

        <!-- Volume -->
        <div>
            <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.volume') }}</label>
            <input
                type="range"
                :value="element.volume ?? 1"
                @input="update('volume', parseFloat($event.target.value))"
                min="0"
                max="2"
                step="0.05"
                class="w-full accent-blue-500"
            />
            <span class="text-[10px] text-gray-500">{{ Math.round((element.volume ?? 1) * 100) }}%</span>
        </div>

        <!-- Fade In/Out -->
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fadeIn') }}</label>
                <input
                    type="number"
                    :value="element.fade_in || 0"
                    @input="update('fade_in', Math.max(0, parseFloat($event.target.value) || 0))"
                    step="0.1"
                    min="0"
                    class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                />
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fadeOut') }}</label>
                <input
                    type="number"
                    :value="element.fade_out || 0"
                    @input="update('fade_out', Math.max(0, parseFloat($event.target.value) || 0))"
                    step="0.1"
                    min="0"
                    class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
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
