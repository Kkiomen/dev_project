<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    message: {
        type: String,
        default: '',
    },
    showClearButton: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['clear']);
const { t } = useI18n();
</script>

<template>
    <div class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
        <div class="w-14 h-14 rounded-full bg-gray-800 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>
        <h3 class="text-base font-semibold text-white mb-1">{{ t('common.noResults') }}</h3>
        <p class="text-sm text-gray-400 max-w-md mb-5">
            {{ message || t('common.noResultsDescription') }}
        </p>
        <button
            v-if="showClearButton"
            @click="emit('clear')"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
            {{ t('common.clearFilters') }}
        </button>
    </div>
</template>
