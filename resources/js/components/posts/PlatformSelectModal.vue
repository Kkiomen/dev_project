<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const emit = defineEmits(['close', 'confirm']);

const { t } = useI18n();

const selectedPlatforms = ref(['facebook', 'instagram', 'youtube']);

const platforms = [
    {
        id: 'facebook',
        label: 'Facebook',
        color: 'blue',
        bgColor: 'bg-blue-600',
        borderColor: 'border-blue-500',
        bgLight: 'bg-blue-50',
        textColor: 'text-blue-700',
    },
    {
        id: 'instagram',
        label: 'Instagram',
        color: 'pink',
        bgColor: 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400',
        borderColor: 'border-pink-500',
        bgLight: 'bg-pink-50',
        textColor: 'text-pink-700',
    },
    {
        id: 'youtube',
        label: 'YouTube',
        color: 'red',
        bgColor: 'bg-red-600',
        borderColor: 'border-red-500',
        bgLight: 'bg-red-50',
        textColor: 'text-red-700',
    },
];

const togglePlatform = (platformId) => {
    const index = selectedPlatforms.value.indexOf(platformId);
    if (index === -1) {
        selectedPlatforms.value.push(platformId);
    } else if (selectedPlatforms.value.length > 1) {
        selectedPlatforms.value.splice(index, 1);
    }
};

const confirm = () => {
    if (selectedPlatforms.value.length > 0) {
        emit('confirm', selectedPlatforms.value);
    }
};
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('posts.selectPlatforms') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ t('posts.selectPlatformsDescription') }}
                </p>
            </div>

            <!-- Platforms -->
            <div class="p-6">
                <div class="grid grid-cols-3 gap-4">
                    <button
                        v-for="platform in platforms"
                        :key="platform.id"
                        @click="togglePlatform(platform.id)"
                        class="relative flex flex-col items-center p-4 rounded-xl border-2 transition-all"
                        :class="selectedPlatforms.includes(platform.id)
                            ? [platform.borderColor, platform.bgLight]
                            : 'border-gray-200 hover:border-gray-300 bg-white'"
                    >
                        <!-- Platform icon -->
                        <div
                            class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl font-bold mb-2"
                            :class="platform.bgColor"
                        >
                            {{ platform.label[0] }}
                        </div>
                        <span
                            class="text-sm font-medium"
                            :class="selectedPlatforms.includes(platform.id) ? platform.textColor : 'text-gray-700'"
                        >
                            {{ platform.label }}
                        </span>

                        <!-- Checkmark -->
                        <div
                            v-if="selectedPlatforms.includes(platform.id)"
                            class="absolute top-2 right-2 w-5 h-5 rounded-full flex items-center justify-center"
                            :class="platform.bgColor"
                        >
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3 rounded-b-xl">
                <Button variant="secondary" @click="$emit('close')">
                    {{ t('common.cancel') }}
                </Button>
                <Button
                    :disabled="selectedPlatforms.length === 0"
                    @click="confirm"
                >
                    {{ t('posts.continueToEditor') }}
                </Button>
            </div>
        </div>
    </div>
</template>
