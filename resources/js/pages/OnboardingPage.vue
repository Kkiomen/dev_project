<script setup>
import { ref, computed, onMounted, provide, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useBrandsStore } from '@/stores/brands';
import { useAuthStore } from '@/stores/auth';
import OnboardingRoleStep from '@/components/onboarding/OnboardingRoleStep.vue';
import OnboardingPurposeStep from '@/components/onboarding/OnboardingPurposeStep.vue';
import OnboardingReferralStep from '@/components/onboarding/OnboardingReferralStep.vue';
import BrandStep1 from '@/components/brand/OnboardingStep1.vue';
import BrandStep2 from '@/components/brand/OnboardingStep2.vue';
import BrandStep3 from '@/components/brand/OnboardingStep3.vue';
import BrandStep4 from '@/components/brand/OnboardingStep4.vue';
import BrandStep5 from '@/components/brand/OnboardingStep5.vue';

const STORAGE_KEY = 'user_onboarding_progress';

provide('onboardingDark', true);

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const authStore = useAuthStore();

const currentStep = ref(1);
const saving = ref(false);
const error = ref(null);

// User onboarding data
const role = ref('');
const purpose = ref([]);
const referralSource = ref('');

const totalSteps = 8;

const isFirstStep = computed(() => currentStep.value === 1);
const isLastStep = computed(() => currentStep.value === totalSteps);
const isBrandPhase = computed(() => currentStep.value >= 4);

const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return role.value !== '';
        case 2:
            return purpose.value.length > 0;
        case 3:
            return referralSource.value !== '';
        case 4:
            return brandsStore.onboardingData.name && brandsStore.onboardingData.name.trim().length > 0;
        case 5:
            return true; // Target audience is optional
        case 6:
            return brandsStore.onboardingData.tone;
        case 7: {
            const pillars = brandsStore.onboardingData.contentPillars;
            if (pillars.length === 0) return true;
            const total = pillars.reduce((sum, p) => sum + (p.percentage || 0), 0);
            return total === 100;
        }
        case 8:
            return Object.values(brandsStore.onboardingData.platforms).some(p => p.enabled);
        default:
            return false;
    }
});

// localStorage persistence
const saveProgress = () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            step: currentStep.value,
            role: role.value,
            purpose: purpose.value,
            referralSource: referralSource.value,
            savedAt: Date.now(),
        }));
    } catch (e) {
        // Silently fail
    }
};

const loadProgress = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) return false;

        const data = JSON.parse(stored);

        // Check if data is not too old (24 hours)
        const maxAge = 24 * 60 * 60 * 1000;
        if (Date.now() - data.savedAt > maxAge) {
            localStorage.removeItem(STORAGE_KEY);
            return false;
        }

        if (data.step) currentStep.value = data.step;
        if (data.role) role.value = data.role;
        if (data.purpose) purpose.value = data.purpose;
        if (data.referralSource) referralSource.value = data.referralSource;

        return true;
    } catch (e) {
        return false;
    }
};

const clearProgress = () => {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        // Silently fail
    }
};

// Watch for changes and save progress
watch([currentStep, role, purpose, referralSource], () => {
    saveProgress();
}, { deep: true });

// Navigation
const handleNext = () => {
    if (canProceed.value && !isLastStep.value) {
        currentStep.value++;
    }
};

const handlePrevious = () => {
    if (!isFirstStep.value) {
        currentStep.value--;
    }
};

// Submit everything
const handleSubmit = async () => {
    if (!canProceed.value || saving.value) return;

    saving.value = true;
    error.value = null;

    try {
        // Save user onboarding data
        await axios.post('/api/user/onboarding', {
            role: role.value,
            purpose: purpose.value,
            referral_source: referralSource.value,
        });

        // Save brand
        await brandsStore.saveOnboardingBrand();

        // Complete user onboarding
        await axios.post('/api/user/onboarding/complete');
        await authStore.fetchUser();

        // Clear all localStorage
        clearProgress();

        router.push('/dashboard');
    } catch (e) {
        error.value = e.response?.data?.message || t('onboarding.error');
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    const loaded = loadProgress();

    if (loaded) {
        // Also try to load brand data from its storage
        brandsStore.loadOnboardingFromStorage();
    } else {
        brandsStore.resetOnboarding();
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-900 flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-2xl">
            <!-- Phase indicators -->
            <div class="flex items-center justify-center gap-3 mb-6">
                <span
                    class="text-xs font-medium px-3 py-1 rounded-full transition-colors"
                    :class="!isBrandPhase
                        ? 'bg-blue-500/20 text-blue-400 ring-1 ring-blue-500/30'
                        : 'bg-gray-800 text-gray-500'"
                >
                    {{ t('onboarding.phases.aboutYou') }}
                </span>
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span
                    class="text-xs font-medium px-3 py-1 rounded-full transition-colors"
                    :class="isBrandPhase
                        ? 'bg-blue-500/20 text-blue-400 ring-1 ring-blue-500/30'
                        : 'bg-gray-800 text-gray-500'"
                >
                    {{ t('onboarding.phases.yourBrand') }}
                </span>
            </div>

            <!-- Progress bar -->
            <div class="mb-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-400">
                        {{ t('onboarding.stepOf', { current: currentStep, total: totalSteps }) }}
                    </span>
                </div>
                <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-blue-500 rounded-full transition-all duration-500 ease-out"
                        :style="{ width: `${(currentStep / totalSteps) * 100}%` }"
                    />
                </div>
            </div>

            <!-- Step content -->
            <div class="mb-10">
                <Transition
                    mode="out-in"
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 translate-x-4"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 -translate-x-4"
                >
                    <!-- Step 1: Role -->
                    <OnboardingRoleStep
                        v-if="currentStep === 1"
                        v-model="role"
                        key="role"
                    />

                    <!-- Step 2: Purpose -->
                    <OnboardingPurposeStep
                        v-else-if="currentStep === 2"
                        v-model="purpose"
                        key="purpose"
                    />

                    <!-- Step 3: Referral -->
                    <OnboardingReferralStep
                        v-else-if="currentStep === 3"
                        v-model="referralSource"
                        key="referral"
                    />

                    <!-- Step 4: Brand basics (with intro) -->
                    <div v-else-if="currentStep === 4" key="brand-basics">
                        <!-- Brand intro card -->
                        <div class="mb-8 p-6 bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-xl">
                            <h3 class="text-lg font-semibold text-white mb-2">
                                {{ t('onboarding.brandIntro.title') }}
                            </h3>
                            <p class="text-gray-400 text-sm mb-4">
                                {{ t('onboarding.brandIntro.subtitle') }}
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ t('onboarding.brandIntro.benefit1Title') }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ t('onboarding.brandIntro.benefit1Description') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ t('onboarding.brandIntro.benefit2Title') }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ t('onboarding.brandIntro.benefit2Description') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ t('onboarding.brandIntro.benefit3Title') }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ t('onboarding.brandIntro.benefit3Description') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <BrandStep1 />
                    </div>

                    <!-- Step 5: Target audience -->
                    <BrandStep2 v-else-if="currentStep === 5" key="brand-audience" />

                    <!-- Step 6: Voice -->
                    <BrandStep3 v-else-if="currentStep === 6" key="brand-voice" />

                    <!-- Step 7: Content pillars -->
                    <BrandStep4 v-else-if="currentStep === 7" key="brand-pillars" />

                    <!-- Step 8: Platforms -->
                    <BrandStep5 v-else-if="currentStep === 8" key="brand-platforms" />
                </Transition>
            </div>

            <!-- Error message -->
            <div v-if="error" class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg">
                <p class="text-sm text-red-400">{{ error }}</p>
            </div>

            <!-- Navigation -->
            <div class="flex items-center justify-between">
                <button
                    v-if="!isFirstStep"
                    @click="handlePrevious"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ t('common.back') }}
                </button>
                <div v-else></div>

                <button
                    @click="isLastStep ? handleSubmit() : handleNext()"
                    :disabled="!canProceed || saving"
                    class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="canProceed
                        ? 'bg-blue-600 text-white hover:bg-blue-700'
                        : 'bg-gray-700 text-gray-400'"
                >
                    <svg
                        v-if="saving"
                        class="animate-spin -ml-1 mr-2 h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    <template v-if="isLastStep">{{ t('common.finish') }}</template>
                    <template v-else>
                        {{ t('common.next') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
