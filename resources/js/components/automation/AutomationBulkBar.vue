<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    count: { type: Number, required: true },
    bulkGeneratingText: { type: Boolean, default: false },
    bulkGeneratingImage: { type: Boolean, default: false },
    bulkDeleting: { type: Boolean, default: false },
});

const emit = defineEmits([
    'bulk-generate-text',
    'bulk-generate-image',
    'bulk-approve',
    'bulk-delete',
    'clear',
]);

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
                    {{ t('postAutomation.bulk.selected', { count }) }}
                </span>

                <div class="h-5 w-px bg-gray-600" />

                <div class="flex items-center gap-2">
                    <button
                        @click="emit('bulk-generate-text')"
                        :disabled="bulkGeneratingText"
                        class="px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-500 disabled:opacity-50 whitespace-nowrap transition-colors"
                    >
                        {{ bulkGeneratingText ? t('postAutomation.actions.generatingText') : t('postAutomation.actions.bulkGenerateText') }}
                    </button>
                    <button
                        @click="emit('bulk-generate-image')"
                        :disabled="bulkGeneratingImage"
                        class="px-3 py-1.5 text-xs font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-500 disabled:opacity-50 whitespace-nowrap transition-colors"
                    >
                        {{ bulkGeneratingImage ? t('postAutomation.actions.generatingImage') : t('postAutomation.actions.bulkGenerateImage') }}
                    </button>
                    <button
                        @click="emit('bulk-approve')"
                        class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-500 whitespace-nowrap transition-colors"
                    >
                        {{ t('postAutomation.actions.bulkApprove') }}
                    </button>
                    <button
                        @click="emit('bulk-delete')"
                        :disabled="bulkDeleting"
                        class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-500 disabled:opacity-50 whitespace-nowrap transition-colors"
                    >
                        {{ bulkDeleting ? t('postAutomation.actions.deleting') : t('postAutomation.actions.bulkDelete') }}
                    </button>
                </div>

                <button
                    @click="emit('clear')"
                    class="p-1 text-gray-400 hover:text-white transition-colors ml-1"
                    :title="t('postAutomation.bulk.close')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
