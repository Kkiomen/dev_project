<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useToast } from '@/composables/useToast';
import { storeToRefs } from 'pinia';

const { t } = useI18n();
const brandsStore = useBrandsStore();
const toast = useToast();
const { onboardingData } = storeToRefs(brandsStore);

const generatingPillars = ref(false);

const MIN_DESCRIPTION_LENGTH = 50;

const hasDescription = computed(() => {
    return onboardingData.value.description && onboardingData.value.description.length >= MIN_DESCRIPTION_LENGTH;
});

const contentPillars = computed({
    get: () => onboardingData.value.contentPillars,
    set: (value) => brandsStore.updateOnboardingData({ contentPillars: value }),
});

const totalPercentage = computed(() => {
    return contentPillars.value.reduce((sum, pillar) => sum + (pillar.percentage || 0), 0);
});

const isValidPercentage = computed(() => {
    return contentPillars.value.length === 0 || totalPercentage.value === 100;
});

const addPillar = () => {
    const remaining = 100 - totalPercentage.value;
    contentPillars.value = [
        ...contentPillars.value,
        { name: '', description: '', percentage: Math.max(0, remaining) }
    ];
};

const removePillar = (index) => {
    contentPillars.value = contentPillars.value.filter((_, i) => i !== index);
};

const updatePillar = (index, field, value) => {
    const pillars = [...contentPillars.value];
    pillars[index] = { ...pillars[index], [field]: value };
    contentPillars.value = pillars;
};

const distributeEvenly = () => {
    if (contentPillars.value.length === 0) return;

    const basePercentage = Math.floor(100 / contentPillars.value.length);
    const remainder = 100 - (basePercentage * contentPillars.value.length);

    contentPillars.value = contentPillars.value.map((pillar, index) => ({
        ...pillar,
        percentage: basePercentage + (index < remainder ? 1 : 0),
    }));
};

// Suggested pillars for quick start
const suggestedPillars = [
    { name: 'Educational content', percentage: 30 },
    { name: 'Industry news', percentage: 20 },
    { name: 'Behind the scenes', percentage: 15 },
    { name: 'Tips & tricks', percentage: 20 },
    { name: 'Case studies', percentage: 15 },
];

const applySuggested = () => {
    contentPillars.value = suggestedPillars.map(p => ({ ...p, description: '' }));
};

const generateWithAi = async () => {
    if (!hasDescription.value || generatingPillars.value) return;

    generatingPillars.value = true;
    try {
        const result = await brandsStore.generateAiSuggestions('contentPillars');
        if (result.contentPillars && result.contentPillars.length > 0) {
            contentPillars.value = result.contentPillars;
            toast.success(t('brands.onboarding.step4.aiPillarsGenerated'));
        }
    } catch (error) {
        console.error('Failed to generate content pillars:', error);
        toast.error(error.message || t('common.error'));
    } finally {
        generatingPillars.value = false;
    }
};
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
            {{ t('brands.onboarding.step4.title') }}
        </h2>
        <p class="text-gray-600 mb-6">
            {{ t('brands.onboarding.step4.description') }}
        </p>

        <!-- Quick start options -->
        <div v-if="contentPillars.length === 0" class="mb-6 space-y-3">
            <!-- AI Generate -->
            <div class="p-4 bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-900">
                            {{ t('brands.onboarding.step4.generateWithAi') }}
                        </p>
                        <p v-if="hasDescription" class="text-xs text-purple-600 mt-0.5">
                            {{ t('brands.onboarding.step4.description') }}
                        </p>
                        <p v-else class="text-xs text-amber-600 mt-0.5 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ t('brands.onboarding.step4.aiRequiresDescription') }}
                        </p>
                    </div>
                    <button
                        @click="generateWithAi"
                        :disabled="!hasDescription || generatingPillars"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all"
                        :class="[
                            hasDescription
                                ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700 shadow-sm hover:shadow'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                        ]"
                    >
                        <svg
                            v-if="generatingPillars"
                            class="w-4 h-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        {{ generatingPillars ? t('brands.onboarding.step4.aiGenerating') : t('brands.onboarding.step4.generateWithAi') }}
                    </button>
                </div>
            </div>

            <!-- Use suggested -->
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-blue-700">
                        {{ t('brands.onboarding.step4.suggestedHint') }}
                    </p>
                    <button
                        @click="applySuggested"
                        class="px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-100 rounded-lg transition-colors"
                    >
                        {{ t('brands.onboarding.step4.useSuggested') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Pillars list -->
        <div class="space-y-4 mb-4">
            <TransitionGroup
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-for="(pillar, index) in contentPillars"
                    :key="index"
                    class="p-4 bg-gray-50 rounded-lg border border-gray-200"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex-1 space-y-3">
                            <input
                                :value="pillar.name"
                                @input="updatePillar(index, 'name', $event.target.value)"
                                :placeholder="t('brands.onboarding.step4.pillarName')"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            />
                            <textarea
                                :value="pillar.description"
                                @input="updatePillar(index, 'description', $event.target.value)"
                                :placeholder="t('brands.onboarding.step4.pillarDescription')"
                                rows="2"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-20">
                                <input
                                    type="number"
                                    :value="pillar.percentage"
                                    @input="updatePillar(index, 'percentage', parseInt($event.target.value) || 0)"
                                    min="0"
                                    max="100"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm text-center"
                                />
                                <span class="text-xs text-gray-500 text-center block mt-1">%</span>
                            </div>
                            <button
                                @click="removePillar(index)"
                                class="p-2 text-gray-400 hover:text-red-500 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>

        <!-- Add button and total -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button
                    @click="addPillar"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('brands.onboarding.step4.addPillar') }}
                </button>

                <!-- AI Generate button (when pillars exist) -->
                <button
                    v-if="contentPillars.length > 0"
                    @click="generateWithAi"
                    :disabled="!hasDescription || generatingPillars"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all"
                    :class="[
                        hasDescription
                            ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700 shadow-sm hover:shadow'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                    ]"
                    :title="!hasDescription ? t('brands.onboarding.step4.aiRequiresDescription') : ''"
                >
                    <svg
                        v-if="generatingPillars"
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
                    {{ t('brands.onboarding.step4.generateWithAi') }}
                </button>
            </div>

            <div v-if="contentPillars.length > 0" class="flex items-center gap-4">
                <button
                    @click="distributeEvenly"
                    class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                >
                    {{ t('brands.onboarding.step4.distributeEvenly') }}
                </button>
                <span
                    class="text-sm font-medium px-3 py-1 rounded-full"
                    :class="{
                        'bg-green-100 text-green-700': isValidPercentage && contentPillars.length > 0,
                        'bg-red-100 text-red-700': !isValidPercentage,
                    }"
                >
                    {{ t('brands.onboarding.step4.total') }}: {{ totalPercentage }}%
                </span>
            </div>
        </div>

        <!-- Validation message -->
        <p v-if="!isValidPercentage" class="mt-2 text-sm text-red-600 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ t('brands.onboarding.step4.percentageError') }}
        </p>
    </div>
</template>
