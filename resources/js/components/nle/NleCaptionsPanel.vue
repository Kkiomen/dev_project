<template>
    <div class="space-y-4">
        <h4 class="text-xs font-medium text-gray-300">{{ t('nle.inspector.captions') }}</h4>

        <div v-if="captions">
            <!-- Enable toggle -->
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs text-gray-400">{{ t('nle.captions.enabled') }}</span>
                <button
                    @click="updateCaptions('enabled', !captions.enabled)"
                    class="relative w-9 h-5 rounded-full transition-colors"
                    :class="captions.enabled ? 'bg-blue-600' : 'bg-gray-600'"
                >
                    <span
                        class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform"
                        :class="captions.enabled ? 'translate-x-4' : 'translate-x-0.5'"
                    />
                </button>
            </div>

            <template v-if="captions.enabled">
                <!-- Style -->
                <div>
                    <label class="block text-[10px] text-gray-500 mb-1">{{ t('nle.captions.style') }}</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="style in captionStyles"
                            :key="style"
                            @click="updateCaptions('style', style)"
                            class="px-2 py-1.5 text-[11px] rounded capitalize transition-colors"
                            :class="captions.style === style
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-700 text-gray-400 hover:bg-gray-600'"
                        >
                            {{ style }}
                        </button>
                    </div>
                </div>

                <!-- Position -->
                <div>
                    <label class="block text-[10px] text-gray-500 mb-1">{{ t('nle.captions.position') }}</label>
                    <select
                        :value="captions.settings?.position || 'bottom'"
                        @change="updateSetting('position', $event.target.value)"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    >
                        <option value="top">Top</option>
                        <option value="center">Center</option>
                        <option value="bottom">Bottom</option>
                    </select>
                </div>

                <!-- Font Size -->
                <div>
                    <label class="block text-[10px] text-gray-500 mb-1">{{ t('nle.captions.fontSize') }}</label>
                    <input
                        type="number"
                        :value="captions.settings?.font_size || 48"
                        @input="updateSetting('font_size', parseInt($event.target.value) || 48)"
                        min="16"
                        max="128"
                        class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <!-- Highlight Keywords -->
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400">{{ t('nle.captions.highlightKeywords') }}</span>
                    <button
                        @click="updateSetting('highlight_keywords', !(captions.settings?.highlight_keywords))"
                        class="relative w-9 h-5 rounded-full transition-colors"
                        :class="captions.settings?.highlight_keywords ? 'bg-blue-600' : 'bg-gray-600'"
                    >
                        <span
                            class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform"
                            :class="captions.settings?.highlight_keywords ? 'translate-x-4' : 'translate-x-0.5'"
                        />
                    </button>
                </div>

                <!-- Segments count -->
                <div class="text-xs text-gray-500">
                    {{ t('nle.captions.segments', { count: captions.segments?.length || 0 }) }}
                </div>
            </template>
        </div>

        <div v-else class="text-xs text-gray-500">
            {{ t('nle.captions.noCaptions') }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const { t } = useI18n();
const store = useVideoEditorStore();

const captions = computed(() => store.captions);

const captionStyles = ['clean', 'hormozi', 'mrbeast', 'bold', 'neon'];

function updateCaptions(key, value) {
    if (!store.composition?.captions) return;
    store.composition.captions[key] = value;
    store.markDirty();
}

function updateSetting(key, value) {
    if (!store.composition?.captions?.settings) return;
    store.composition.captions.settings[key] = value;
    store.markDirty();
}
</script>
