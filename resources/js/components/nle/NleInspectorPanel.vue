<template>
    <div class="flex flex-col h-full">
        <!-- Tabs -->
        <div class="flex border-b border-gray-700">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                @click="store.inspectorTab = tab.value"
                class="flex-1 px-3 py-2 text-xs font-medium transition-colors"
                :class="store.inspectorTab === tab.value
                    ? 'text-blue-400 border-b-2 border-blue-400'
                    : 'text-gray-500 hover:text-gray-300'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-3">
            <!-- Multi-select message -->
            <div v-if="store.selectedElementIds.length > 1" class="flex flex-col items-center justify-center h-full text-center">
                <svg class="w-10 h-10 text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <p class="text-xs text-gray-400">{{ t('nle.inspector.multiSelection', { count: store.selectedElementIds.length }) }}</p>
            </div>

            <template v-else-if="store.selectedElement">
                <template v-if="store.inspectorTab === 'properties'">
                    <NleElementProperties :element="store.selectedElement" />
                    <div class="border-t border-gray-700 mt-4 pt-4">
                        <NleTextProperties
                            v-if="store.selectedElement.type === 'text'"
                            :element="store.selectedElement"
                        />
                        <NleVideoProperties
                            v-else-if="store.selectedElement.type === 'video'"
                            :element="store.selectedElement"
                        />
                        <NleAudioProperties
                            v-else-if="store.selectedElement.type === 'audio'"
                            :element="store.selectedElement"
                        />
                    </div>
                </template>
                <NleEffectsPanel
                    v-else-if="store.inspectorTab === 'effects'"
                    :element="store.selectedElement"
                />
                <NleCaptionsPanel
                    v-else-if="store.inspectorTab === 'captions'"
                />
            </template>

            <!-- No selection -->
            <div v-else class="flex flex-col items-center justify-center h-full text-center">
                <svg class="w-10 h-10 text-gray-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                </svg>
                <p class="text-xs text-gray-500">{{ t('nle.inspector.noSelection') }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import NleElementProperties from './NleElementProperties.vue';
import NleTextProperties from './NleTextProperties.vue';
import NleVideoProperties from './NleVideoProperties.vue';
import NleAudioProperties from './NleAudioProperties.vue';
import NleEffectsPanel from './NleEffectsPanel.vue';
import NleCaptionsPanel from './NleCaptionsPanel.vue';

const { t } = useI18n();
const store = useVideoEditorStore();

const tabs = computed(() => [
    { value: 'properties', label: t('nle.inspector.properties') },
    { value: 'effects', label: t('nle.inspector.effects') },
    { value: 'captions', label: t('nle.inspector.captions') },
]);
</script>
