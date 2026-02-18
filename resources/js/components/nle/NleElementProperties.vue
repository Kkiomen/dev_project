<template>
    <div class="space-y-4">
        <!-- Name -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">{{ t('nle.inspector.name') }}</label>
            <input
                :value="element.name"
                @input="update('name', $event.target.value)"
                class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1.5 border border-gray-600 focus:border-blue-500 focus:outline-none"
            />
        </div>

        <!-- Type indicator -->
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <span class="px-2 py-0.5 bg-gray-700 rounded capitalize">{{ element.type }}</span>
            <span v-if="element.modification_key" class="px-2 py-0.5 bg-purple-900/50 text-purple-300 rounded">
                {{ element.modification_key }}
            </span>
        </div>

        <!-- Timing -->
        <div>
            <h4 class="text-xs font-medium text-gray-300 mb-2">{{ t('nle.inspector.timing') }}</h4>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.startTime') }}</label>
                    <input
                        type="number"
                        :value="element.time"
                        @input="update('time', parseFloat($event.target.value) || 0)"
                        step="0.1"
                        min="0"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.duration') }}</label>
                    <input
                        type="number"
                        :value="element.duration"
                        @input="update('duration', Math.max(0.1, parseFloat($event.target.value) || 0.1))"
                        step="0.1"
                        min="0.1"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Position -->
        <div>
            <h4 class="text-xs font-medium text-gray-300 mb-2">{{ t('nle.inspector.position') }}</h4>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">X</label>
                    <input
                        :value="element.x"
                        @input="update('x', $event.target.value)"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">Y</label>
                    <input
                        :value="element.y"
                        @input="update('y', $event.target.value)"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Size -->
        <div>
            <h4 class="text-xs font-medium text-gray-300 mb-2">{{ t('nle.inspector.size') }}</h4>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.width') }}</label>
                    <input
                        :value="element.width"
                        @input="update('width', $event.target.value)"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.height') }}</label>
                    <input
                        :value="element.height"
                        @input="update('height', $event.target.value)"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Visual -->
        <div>
            <h4 class="text-xs font-medium text-gray-300 mb-2">{{ t('nle.inspector.visual') }}</h4>
            <div class="space-y-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.opacity') }}</label>
                    <input
                        type="range"
                        :value="element.opacity"
                        @input="update('opacity', parseFloat($event.target.value))"
                        min="0"
                        max="1"
                        step="0.05"
                        class="w-full accent-blue-500"
                    />
                    <span class="text-[10px] text-gray-500">{{ Math.round((element.opacity ?? 1) * 100) }}%</span>
                </div>

                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.rotation') }}</label>
                    <input
                        type="number"
                        :value="element.rotation"
                        @input="update('rotation', parseFloat($event.target.value) || 0)"
                        step="1"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <div v-if="element.type === 'video' || element.type === 'image'">
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.fit') }}</label>
                    <select
                        :value="element.fit"
                        @change="update('fit', $event.target.value)"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    >
                        <option value="cover">Cover</option>
                        <option value="contain">Contain</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Audio (for video/audio types) -->
        <div v-if="element.type === 'video' || element.type === 'audio'">
            <h4 class="text-xs font-medium text-gray-300 mb-2">{{ t('nle.inspector.audio') }}</h4>
            <div class="space-y-2">
                <div>
                    <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.volume') }}</label>
                    <input
                        type="range"
                        :value="element.volume"
                        @input="update('volume', parseFloat($event.target.value))"
                        min="0"
                        max="2"
                        step="0.05"
                        class="w-full accent-blue-500"
                    />
                    <span class="text-[10px] text-gray-500">{{ Math.round((element.volume ?? 1) * 100) }}%</span>
                </div>
            </div>
        </div>

        <!-- Modification Key (for templates) -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">{{ t('nle.inspector.modificationKey') }}</label>
            <input
                :value="element.modification_key"
                @input="update('modification_key', $event.target.value || null)"
                :placeholder="t('nle.inspector.modificationKeyPlaceholder')"
                class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1.5 border border-gray-600 focus:border-blue-500 focus:outline-none"
            />
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
