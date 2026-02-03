<script setup>
import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { storeToRefs } from 'pinia';
import Input from '@/components/common/Input.vue';

const { t } = useI18n();
const brandsStore = useBrandsStore();
const { onboardingData } = storeToRefs(brandsStore);
const dark = inject('onboardingDark', false);

const MIN_DESCRIPTION_LENGTH = 50;

const industries = [
    'marketing',
    'technology',
    'finance',
    'healthcare',
    'education',
    'ecommerce',
    'real_estate',
    'fitness',
    'food',
    'travel',
    'fashion',
    'entertainment',
    'consulting',
    'legal',
    'non_profit',
    'other',
];

const languages = [
    { code: 'pl', name: 'Polski' },
    { code: 'en', name: 'English' },
    { code: 'de', name: 'Deutsch' },
    { code: 'es', name: 'Español' },
    { code: 'fr', name: 'Français' },
    { code: 'it', name: 'Italiano' },
    { code: 'pt', name: 'Português' },
    { code: 'nl', name: 'Nederlands' },
    { code: 'ru', name: 'Русский' },
    { code: 'uk', name: 'Українська' },
    { code: 'cs', name: 'Čeština' },
    { code: 'sk', name: 'Slovenčina' },
    { code: 'hu', name: 'Magyar' },
    { code: 'ro', name: 'Română' },
    { code: 'bg', name: 'Български' },
    { code: 'hr', name: 'Hrvatski' },
    { code: 'sl', name: 'Slovenščina' },
    { code: 'sr', name: 'Српски' },
    { code: 'lt', name: 'Lietuvių' },
    { code: 'lv', name: 'Latviešu' },
    { code: 'et', name: 'Eesti' },
    { code: 'fi', name: 'Suomi' },
    { code: 'sv', name: 'Svenska' },
    { code: 'no', name: 'Norsk' },
    { code: 'da', name: 'Dansk' },
    { code: 'el', name: 'Ελληνικά' },
    { code: 'tr', name: 'Türkçe' },
    { code: 'ar', name: 'العربية' },
    { code: 'he', name: 'עברית' },
    { code: 'ja', name: '日本語' },
    { code: 'ko', name: '한국어' },
    { code: 'zh', name: '中文 (简体)' },
    { code: 'zh-TW', name: '中文 (繁體)' },
    { code: 'th', name: 'ไทย' },
    { code: 'vi', name: 'Tiếng Việt' },
    { code: 'id', name: 'Bahasa Indonesia' },
    { code: 'ms', name: 'Bahasa Melayu' },
    { code: 'hi', name: 'हिन्दी' },
];

const name = computed({
    get: () => onboardingData.value.name,
    set: (value) => brandsStore.updateOnboardingData({ name: value }),
});

const industry = computed({
    get: () => onboardingData.value.industry,
    set: (value) => brandsStore.updateOnboardingData({ industry: value }),
});

const description = computed({
    get: () => onboardingData.value.description,
    set: (value) => brandsStore.updateOnboardingData({ description: value }),
});

const language = computed({
    get: () => onboardingData.value.language,
    set: (value) => brandsStore.updateOnboardingData({ language: value }),
});

const descriptionLength = computed(() => description.value?.length || 0);

const isDescriptionStarted = computed(() => descriptionLength.value > 0);

const isDescriptionValid = computed(() => descriptionLength.value >= MIN_DESCRIPTION_LENGTH);

const remainingChars = computed(() => MIN_DESCRIPTION_LENGTH - descriptionLength.value);
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold mb-2" :class="dark ? 'text-white' : 'text-gray-900'">
            {{ t('brands.onboarding.step1.title') }}
        </h2>
        <p class="mb-6" :class="dark ? 'text-gray-400' : 'text-gray-600'">
            {{ t('brands.onboarding.step1.description') }}
        </p>

        <div class="space-y-6">
            <Input
                v-model="name"
                :label="t('brands.onboarding.step1.brandName')"
                :placeholder="t('brands.onboarding.step1.brandNamePlaceholder')"
                required
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                        {{ t('brands.onboarding.step1.industry') }}
                    </label>
                    <select
                        v-model="industry"
                        class="block w-full rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        :class="dark ? 'bg-gray-800 border-gray-600 text-white' : 'border-gray-300'"
                    >
                        <option value="">{{ t('brands.onboarding.step1.selectIndustry') }}</option>
                        <option v-for="ind in industries" :key="ind" :value="ind">
                            {{ t(`brands.industries.${ind}`) }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                        {{ t('brands.onboarding.step1.language') }}
                    </label>
                    <select
                        v-model="language"
                        class="block w-full rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        :class="dark ? 'bg-gray-800 border-gray-600 text-white' : 'border-gray-300'"
                    >
                        <option v-for="lang in languages" :key="lang.code" :value="lang.code">
                            {{ lang.name }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs" :class="dark ? 'text-gray-500' : 'text-gray-500'">
                        {{ t('brands.onboarding.step1.languageHint') }}
                    </p>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                        {{ t('brands.onboarding.step1.descriptionLabel') }}
                    </label>
                    <span
                        class="text-xs"
                        :class="{
                            'text-gray-400': !isDescriptionStarted,
                            'text-amber-600': isDescriptionStarted && !isDescriptionValid,
                            'text-green-600': isDescriptionValid,
                        }"
                    >
                        {{ descriptionLength }} / {{ MIN_DESCRIPTION_LENGTH }}
                    </span>
                </div>
                <textarea
                    v-model="description"
                    :placeholder="t('brands.onboarding.step1.descriptionPlaceholder')"
                    rows="4"
                    class="block w-full rounded-lg shadow-sm sm:text-sm transition-colors"
                    :class="[
                        dark ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500' : '',
                        (!isDescriptionStarted || isDescriptionValid)
                            ? (dark ? 'focus:border-blue-500 focus:ring-blue-500' : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500')
                            : 'border-amber-300 focus:border-amber-500 focus:ring-amber-500',
                    ]"
                />

                <!-- Hint message -->
                <div class="mt-2">
                    <p
                        v-if="!isDescriptionStarted"
                        class="text-xs flex items-center gap-1"
                        :class="dark ? 'text-gray-500' : 'text-gray-500'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ t('brands.onboarding.step1.descriptionHint') }}
                    </p>
                    <p
                        v-else-if="!isDescriptionValid"
                        class="text-xs text-amber-600 flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        {{ t('brands.onboarding.step1.descriptionTooShort', { remaining: remainingChars }) }}
                    </p>
                    <p
                        v-else
                        class="text-xs text-green-600 flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ t('brands.onboarding.step1.descriptionReady') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
