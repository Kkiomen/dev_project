<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    count: { type: Number, required: true },
    generating: { type: Boolean, default: false },
});

const emit = defineEmits(['generate', 'generate-and-process', 'clear']);

const { t } = useI18n();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div
                v-if="count > 0"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-gray-900 text-white rounded-xl shadow-2xl px-5 py-3"
            >
                <span class="text-sm font-medium whitespace-nowrap">
                    {{ t('postAutomation.proposals.bulk.selected', { count }) }}
                </span>

                <div class="h-5 w-px bg-gray-600" />

                <button
                    @click="emit('generate')"
                    :disabled="generating"
                    class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-500 disabled:opacity-50 whitespace-nowrap transition-colors"
                >
                    {{ generating ? t('postAutomation.proposals.bulk.generating') : t('postAutomation.proposals.bulk.generatePosts') }}
                </button>
                <button
                    @click="emit('generate-and-process')"
                    :disabled="generating"
                    class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50 whitespace-nowrap transition-colors"
                >
                    {{ generating ? t('postAutomation.proposals.bulk.generatingAndProcessing') : t('postAutomation.proposals.bulk.generateAndProcess') }}
                </button>

                <button
                    @click="emit('clear')"
                    class="p-1 text-gray-400 hover:text-white transition-colors ml-1"
                    :title="t('postAutomation.proposals.bulk.close')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
