<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoManagerStore } from '@/stores/videoManager';
import { useToast } from '@/composables/useToast';

const { t } = useI18n();
const videoManagerStore = useVideoManagerStore();
const toast = useToast();

const saving = ref(false);

const settings = ref({ ...videoManagerStore.settings });

const captionStyles = [
    { value: 'clean', label: 'Clean' },
    { value: 'hormozi', label: 'Hormozi' },
    { value: 'mrbeast', label: 'MrBeast' },
    { value: 'bold', label: 'Bold' },
    { value: 'neon', label: 'Neon' },
];

const positions = [
    { value: 'bottom', label: 'videoManager.settings.bottom' },
    { value: 'center', label: 'videoManager.settings.center' },
    { value: 'top', label: 'videoManager.settings.top' },
];

const languages = [
    { value: '', label: 'videoManager.settings.autoDetect' },
    { value: 'pl', label: 'Polski' },
    { value: 'en', label: 'English' },
    { value: 'de', label: 'Deutsch' },
    { value: 'es', label: 'Espanol' },
    { value: 'fr', label: 'Francais' },
];

const saveSettings = async () => {
    saving.value = true;
    try {
        videoManagerStore.settings = { ...settings.value };
        toast.success(t('videoManager.settings.saved'));
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl space-y-8">
        <!-- Header -->
        <div>
            <h1 class="text-xl font-bold text-white">{{ t('videoManager.settings.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('videoManager.settings.subtitle') }}</p>
        </div>

        <!-- Defaults Section -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5 space-y-5">
            <h2 class="text-sm font-semibold text-white">{{ t('videoManager.settings.defaults') }}</h2>

            <!-- Default Caption Style -->
            <div>
                <label class="block text-sm text-gray-300 mb-2">{{ t('videoManager.settings.defaultCaptionStyle') }}</label>
                <select
                    v-model="settings.defaultCaptionStyle"
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-violet-500"
                >
                    <option v-for="style in captionStyles" :key="style.value" :value="style.value">{{ style.label }}</option>
                </select>
            </div>

            <!-- Default Language -->
            <div>
                <label class="block text-sm text-gray-300 mb-2">{{ t('videoManager.settings.defaultLanguage') }}</label>
                <select
                    v-model="settings.defaultLanguage"
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-violet-500"
                >
                    <option v-for="lang in languages" :key="lang.value" :value="lang.value || null">
                        {{ lang.label.startsWith('videoManager.') ? t(lang.label) : lang.label }}
                    </option>
                </select>
            </div>

            <!-- Default Position -->
            <div>
                <label class="block text-sm text-gray-300 mb-2">{{ t('videoManager.settings.defaultPosition') }}</label>
                <div class="flex gap-2">
                    <button
                        v-for="pos in positions"
                        :key="pos.value"
                        @click="settings.defaultPosition = pos.value"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                        :class="settings.defaultPosition === pos.value
                            ? 'bg-violet-600 text-white'
                            : 'bg-gray-800 text-gray-400 hover:text-white'"
                    >
                        {{ t(pos.label) }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Dictionary Section -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5 space-y-3">
            <h2 class="text-sm font-semibold text-white">{{ t('videoManager.settings.dictionary') }}</h2>
            <p class="text-xs text-gray-500">{{ t('videoManager.settings.dictionaryDesc') }}</p>
            <textarea
                v-model="settings.dictionary"
                rows="6"
                :placeholder="t('videoManager.settings.dictionaryPlaceholder')"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-600 focus:outline-none focus:border-violet-500 resize-none"
            />
        </div>

        <!-- Auto-processing Section -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5 space-y-3">
            <h2 class="text-sm font-semibold text-white">{{ t('videoManager.settings.autoProcessing') }}</h2>
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    v-model="settings.autoTranscribe"
                    type="checkbox"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-violet-600 focus:ring-violet-500"
                />
                <div>
                    <span class="text-sm text-gray-300">{{ t('videoManager.settings.autoTranscribe') }}</span>
                    <p class="text-xs text-gray-500">{{ t('videoManager.settings.autoTranscribeDesc') }}</p>
                </div>
            </label>
        </div>

        <!-- Save -->
        <div class="flex justify-end">
            <button
                @click="saveSettings"
                :disabled="saving"
                class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
            >
                {{ saving ? t('videoManager.settings.saving') : t('videoManager.settings.save') }}
            </button>
        </div>
    </div>
</template>
