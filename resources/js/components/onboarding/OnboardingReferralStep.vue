<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const sources = [
    { id: 'social_media', icon: 'M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z' },
    { id: 'google', icon: 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z' },
    { id: 'friend', icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
    { id: 'youtube', icon: 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z' },
    { id: 'blog', icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25' },
    { id: 'other', icon: 'M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z' },
];

const selectSource = (sourceId) => {
    emit('update:modelValue', sourceId);
};
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-white mb-2">{{ t('onboarding.referral.title') }}</h2>
        <p class="text-gray-400 mb-8">{{ t('onboarding.referral.subtitle') }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
                v-for="source in sources"
                :key="source.id"
                @click="selectSource(source.id)"
                class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer text-left"
                :class="modelValue === source.id
                    ? 'border-blue-500 bg-blue-500/10'
                    : 'border-gray-700 bg-gray-800/50 hover:border-gray-500 hover:bg-gray-800'"
            >
                <div
                    class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center"
                    :class="modelValue === source.id ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-700 text-gray-400'"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="source.icon" />
                    </svg>
                </div>
                <span class="text-sm font-medium" :class="modelValue === source.id ? 'text-white' : 'text-gray-300'">
                    {{ t(`onboarding.referral.options.${source.id}`) }}
                </span>
            </button>
        </div>
    </div>
</template>
