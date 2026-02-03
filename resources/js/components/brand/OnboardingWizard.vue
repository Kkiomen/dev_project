<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useBrandsStore } from '@/stores/brands';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import axios from 'axios';
import OnboardingStep1 from './OnboardingStep1.vue';
import OnboardingStep2 from './OnboardingStep2.vue';
import OnboardingStep3 from './OnboardingStep3.vue';
import OnboardingStep4 from './OnboardingStep4.vue';
import OnboardingStep5 from './OnboardingStep5.vue';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const authStore = useAuthStore();
const { onboardingStep, onboardingData, saving } = storeToRefs(brandsStore);

const isUserOnboarding = computed(() => !authStore.isOnboarded);

const error = ref(null);

const steps = [
    { number: 1, key: 'basics', component: OnboardingStep1 },
    { number: 2, key: 'audience', component: OnboardingStep2 },
    { number: 3, key: 'voice', component: OnboardingStep3 },
    { number: 4, key: 'pillars', component: OnboardingStep4 },
    { number: 5, key: 'platforms', component: OnboardingStep5 },
];

const currentStepComponent = computed(() => {
    return steps.find(s => s.number === onboardingStep.value)?.component;
});

const isFirstStep = computed(() => onboardingStep.value === 1);
const isLastStep = computed(() => onboardingStep.value === 5);

const canProceed = computed(() => {
    const data = onboardingData.value;
    switch (onboardingStep.value) {
        case 1:
            return data.name && data.name.trim().length > 0;
        case 2:
            return true; // Target audience is optional
        case 3:
            return data.tone;
        case 4:
            // Content pillars should sum to 100% if any are defined
            if (data.contentPillars.length === 0) return true;
            const total = data.contentPillars.reduce((sum, p) => sum + (p.percentage || 0), 0);
            return total === 100;
        case 5:
            // At least one platform should be enabled
            return Object.values(data.platforms).some(p => p.enabled);
        default:
            return true;
    }
});

const handleNext = () => {
    if (canProceed.value && !isLastStep.value) {
        brandsStore.nextStep();
    }
};

const handlePrevious = () => {
    if (!isFirstStep.value) {
        brandsStore.previousStep();
    }
};

const handleSubmit = async () => {
    if (!canProceed.value) return;

    error.value = null;
    try {
        await brandsStore.saveOnboardingBrand();

        // Complete user onboarding if this is the first brand creation during onboarding
        if (isUserOnboarding.value) {
            await axios.post('/api/user/onboarding/complete');
            await authStore.fetchUser();
        }

        router.push('/dashboard');
    } catch (e) {
        error.value = e.response?.data?.message || t('brands.onboarding.error');
    }
};

const handleCancel = () => {
    brandsStore.resetOnboarding();
    router.push('/brands');
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            <!-- Progress indicator -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <template v-for="(step, index) in steps" :key="step.number">
                        <div class="flex items-center">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
                                :class="{
                                    'bg-blue-600 text-white': step.number <= onboardingStep,
                                    'bg-gray-200 text-gray-600': step.number > onboardingStep,
                                }"
                            >
                                <svg
                                    v-if="step.number < onboardingStep"
                                    class="w-5 h-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span v-else>{{ step.number }}</span>
                            </div>
                            <span
                                class="ml-2 text-sm font-medium hidden sm:block"
                                :class="{
                                    'text-blue-600': step.number === onboardingStep,
                                    'text-gray-900': step.number < onboardingStep,
                                    'text-gray-400': step.number > onboardingStep,
                                }"
                            >
                                {{ t(`brands.onboarding.steps.${step.key}`) }}
                            </span>
                        </div>
                        <div
                            v-if="index < steps.length - 1"
                            class="flex-1 h-0.5 mx-4"
                            :class="{
                                'bg-blue-600': step.number < onboardingStep,
                                'bg-gray-200': step.number >= onboardingStep,
                            }"
                        />
                    </template>
                </div>
            </div>

            <!-- Step content -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                <Transition
                    mode="out-in"
                    enter-active-class="transition-opacity duration-200"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <component :is="currentStepComponent" />
                </Transition>

                <!-- Error message -->
                <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600">{{ error }}</p>
                </div>
            </div>

            <!-- Navigation buttons -->
            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="!isFirstStep"
                    @click="handlePrevious"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ t('common.back') }}
                </button>
                <button
                    v-else-if="!isUserOnboarding"
                    @click="handleCancel"
                    class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700"
                >
                    {{ t('common.cancel') }}
                </button>
                <div v-else></div>

                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500">
                        {{ t('brands.onboarding.stepOf', { current: onboardingStep, total: steps.length }) }}
                    </span>
                    <Button
                        v-if="!isLastStep"
                        @click="handleNext"
                        :disabled="!canProceed"
                    >
                        {{ t('common.next') }}
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Button>
                    <Button
                        v-else
                        @click="handleSubmit"
                        :disabled="!canProceed || saving"
                        :loading="saving"
                    >
                        {{ t('brands.onboarding.finish') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
