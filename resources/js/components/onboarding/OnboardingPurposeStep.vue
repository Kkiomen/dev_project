<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const purposes = [
    { id: 'post_automation', icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75' },
    { id: 'graphics', icon: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v13.5A1.5 1.5 0 003.75 21z' },
    { id: 'brand_management', icon: 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42' },
    { id: 'content_planning', icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5' },
    { id: 'everything', icon: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z' },
    { id: 'other', icon: 'M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z' },
];

const togglePurpose = (purposeId) => {
    const current = [...props.modelValue];
    const index = current.indexOf(purposeId);
    if (index === -1) {
        current.push(purposeId);
    } else {
        current.splice(index, 1);
    }
    emit('update:modelValue', current);
};

const isSelected = (purposeId) => props.modelValue.includes(purposeId);
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-white mb-2">{{ t('onboarding.purpose.title') }}</h2>
        <p class="text-gray-400 mb-8">{{ t('onboarding.purpose.subtitle') }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
                v-for="purpose in purposes"
                :key="purpose.id"
                @click="togglePurpose(purpose.id)"
                class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer text-left"
                :class="isSelected(purpose.id)
                    ? 'border-blue-500 bg-blue-500/10'
                    : 'border-gray-700 bg-gray-800/50 hover:border-gray-500 hover:bg-gray-800'"
            >
                <div
                    class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center"
                    :class="isSelected(purpose.id) ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-700 text-gray-400'"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="purpose.icon" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium" :class="isSelected(purpose.id) ? 'text-white' : 'text-gray-300'">
                        {{ t(`onboarding.purpose.options.${purpose.id}`) }}
                    </span>
                </div>
                <div
                    class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                    :class="isSelected(purpose.id)
                        ? 'border-blue-500 bg-blue-500'
                        : 'border-gray-600'"
                >
                    <svg v-if="isSelected(purpose.id)" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>
        </div>
    </div>
</template>
