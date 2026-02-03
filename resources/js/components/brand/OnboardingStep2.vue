<script setup>
import { computed, ref, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useToast } from '@/composables/useToast';
import { storeToRefs } from 'pinia';

const { t } = useI18n();
const brandsStore = useBrandsStore();
const toast = useToast();
const { onboardingData } = storeToRefs(brandsStore);
const dark = inject('onboardingDark', false);

const newInterest = ref('');
const newPainPoint = ref('');
const generatingInterests = ref(false);
const generatingPainPoints = ref(false);

const ageRanges = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
const genders = ['all', 'male', 'female'];

const MIN_DESCRIPTION_LENGTH = 50;

const hasDescription = computed(() => {
    return onboardingData.value.description && onboardingData.value.description.length >= MIN_DESCRIPTION_LENGTH;
});

const ageRange = computed({
    get: () => onboardingData.value.ageRange,
    set: (value) => brandsStore.updateOnboardingData({ ageRange: value }),
});

const gender = computed({
    get: () => onboardingData.value.gender,
    set: (value) => brandsStore.updateOnboardingData({ gender: value }),
});

const interests = computed({
    get: () => onboardingData.value.interests,
    set: (value) => brandsStore.updateOnboardingData({ interests: value }),
});

const painPoints = computed({
    get: () => onboardingData.value.painPoints,
    set: (value) => brandsStore.updateOnboardingData({ painPoints: value }),
});

const addInterest = () => {
    if (newInterest.value.trim() && !interests.value.includes(newInterest.value.trim())) {
        interests.value = [...interests.value, newInterest.value.trim()];
        newInterest.value = '';
    }
};

const removeInterest = (index) => {
    interests.value = interests.value.filter((_, i) => i !== index);
};

const addPainPoint = () => {
    if (newPainPoint.value.trim() && !painPoints.value.includes(newPainPoint.value.trim())) {
        painPoints.value = [...painPoints.value, newPainPoint.value.trim()];
        newPainPoint.value = '';
    }
};

const removePainPoint = (index) => {
    painPoints.value = painPoints.value.filter((_, i) => i !== index);
};

const handleKeydown = (event, type) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        if (type === 'interest') {
            addInterest();
        } else {
            addPainPoint();
        }
    }
};

const generateInterests = async () => {
    if (!hasDescription.value || generatingInterests.value) return;

    generatingInterests.value = true;
    try {
        const result = await brandsStore.generateAiSuggestions('interests');
        if (result.interests && result.interests.length > 0) {
            const newInterests = result.interests.filter(
                (i) => !interests.value.includes(i)
            );
            interests.value = [...interests.value, ...newInterests];
            toast.success(t('brands.onboarding.step2.aiInterestsGenerated'));
        }
    } catch (error) {
        console.error('Failed to generate interests:', error);
        toast.error(error.message || t('common.error'));
    } finally {
        generatingInterests.value = false;
    }
};

const generatePainPoints = async () => {
    if (!hasDescription.value || generatingPainPoints.value) return;

    generatingPainPoints.value = true;
    try {
        const result = await brandsStore.generateAiSuggestions('painPoints');
        if (result.painPoints && result.painPoints.length > 0) {
            const newPainPoints = result.painPoints.filter(
                (p) => !painPoints.value.includes(p)
            );
            painPoints.value = [...painPoints.value, ...newPainPoints];
            toast.success(t('brands.onboarding.step2.aiPainPointsGenerated'));
        }
    } catch (error) {
        console.error('Failed to generate pain points:', error);
        toast.error(error.message || t('common.error'));
    } finally {
        generatingPainPoints.value = false;
    }
};
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold mb-2" :class="dark ? 'text-white' : 'text-gray-900'">
            {{ t('brands.onboarding.step2.title') }}
        </h2>
        <p class="mb-6" :class="dark ? 'text-gray-400' : 'text-gray-600'">
            {{ t('brands.onboarding.step2.description') }}
        </p>

        <div class="space-y-6">
            <!-- Age Range -->
            <div>
                <label class="block text-sm font-medium mb-2" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                    {{ t('brands.onboarding.step2.ageRange') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="range in ageRanges"
                        :key="range"
                        @click="ageRange = range"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="{
                            'bg-blue-600 text-white border-blue-600': ageRange === range,
                            [dark ? 'bg-gray-800 text-gray-300 border-gray-600 hover:bg-gray-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']: ageRange !== range,
                        }"
                    >
                        {{ range }}
                    </button>
                </div>
            </div>

            <!-- Gender -->
            <div>
                <label class="block text-sm font-medium mb-2" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                    {{ t('brands.onboarding.step2.gender') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="g in genders"
                        :key="g"
                        @click="gender = g"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="{
                            'bg-blue-600 text-white border-blue-600': gender === g,
                            [dark ? 'bg-gray-800 text-gray-300 border-gray-600 hover:bg-gray-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']: gender !== g,
                        }"
                    >
                        {{ t(`brands.onboarding.step2.genders.${g}`) }}
                    </button>
                </div>
            </div>

            <!-- Interests -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                        {{ t('brands.onboarding.step2.interests') }}
                    </label>
                    <button
                        @click="generateInterests"
                        :disabled="!hasDescription || generatingInterests"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all"
                        :class="[
                            hasDescription
                                ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700 shadow-sm hover:shadow'
                                : (dark ? 'bg-gray-700 text-gray-500 cursor-not-allowed' : 'bg-gray-100 text-gray-400 cursor-not-allowed')
                        ]"
                        :title="!hasDescription ? t('brands.onboarding.step2.aiRequiresDescription') : ''"
                    >
                        <svg
                            v-if="generatingInterests"
                            class="w-3.5 h-3.5 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        {{ generatingInterests ? t('brands.onboarding.step2.aiGenerating') : t('brands.onboarding.step2.generateWithAi') }}
                    </button>
                </div>

                <!-- AI hint -->
                <p v-if="!hasDescription" class="text-xs text-amber-600 mb-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ t('brands.onboarding.step2.aiRequiresDescription') }}
                </p>

                <div class="flex gap-2 mb-2">
                    <input
                        v-model="newInterest"
                        @keydown="handleKeydown($event, 'interest')"
                        :placeholder="t('brands.onboarding.step2.interestPlaceholder')"
                        class="flex-1 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        :class="dark ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500' : 'border-gray-300'"
                    />
                    <button
                        @click="addInterest"
                        :disabled="!newInterest.trim()"
                        class="px-4 py-2 rounded-lg disabled:opacity-50 transition-colors"
                        :class="dark ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        {{ t('common.add') }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="(interest, index) in interests"
                        :key="index"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm animate-fadeIn"
                        :class="dark ? 'bg-blue-500/20 text-blue-400' : 'bg-blue-100 text-blue-700'"
                    >
                        {{ interest }}
                        <button @click="removeInterest(index)" class="transition-colors" :class="dark ? 'hover:text-blue-300' : 'hover:text-blue-900'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                </div>
            </div>

            <!-- Pain Points -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium" :class="dark ? 'text-gray-300' : 'text-gray-700'">
                        {{ t('brands.onboarding.step2.painPoints') }}
                    </label>
                    <button
                        @click="generatePainPoints"
                        :disabled="!hasDescription || generatingPainPoints"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all"
                        :class="[
                            hasDescription
                                ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700 shadow-sm hover:shadow'
                                : (dark ? 'bg-gray-700 text-gray-500 cursor-not-allowed' : 'bg-gray-100 text-gray-400 cursor-not-allowed')
                        ]"
                        :title="!hasDescription ? t('brands.onboarding.step2.aiRequiresDescription') : ''"
                    >
                        <svg
                            v-if="generatingPainPoints"
                            class="w-3.5 h-3.5 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        {{ generatingPainPoints ? t('brands.onboarding.step2.aiGenerating') : t('brands.onboarding.step2.generateWithAi') }}
                    </button>
                </div>

                <!-- AI hint -->
                <p v-if="!hasDescription" class="text-xs text-amber-600 mb-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ t('brands.onboarding.step2.aiRequiresDescription') }}
                </p>

                <div class="flex gap-2 mb-2">
                    <input
                        v-model="newPainPoint"
                        @keydown="handleKeydown($event, 'painPoint')"
                        :placeholder="t('brands.onboarding.step2.painPointPlaceholder')"
                        class="flex-1 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        :class="dark ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500' : 'border-gray-300'"
                    />
                    <button
                        @click="addPainPoint"
                        :disabled="!newPainPoint.trim()"
                        class="px-4 py-2 rounded-lg disabled:opacity-50 transition-colors"
                        :class="dark ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        {{ t('common.add') }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="(point, index) in painPoints"
                        :key="index"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm animate-fadeIn"
                        :class="dark ? 'bg-orange-500/20 text-orange-400' : 'bg-orange-100 text-orange-700'"
                    >
                        {{ point }}
                        <button @click="removePainPoint(index)" class="transition-colors" :class="dark ? 'hover:text-orange-300' : 'hover:text-orange-900'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-fadeIn {
    animation: fadeIn 0.2s ease-out;
}
</style>
