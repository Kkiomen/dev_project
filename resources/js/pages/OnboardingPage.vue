<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import axios from 'axios';
import OnboardingRoleStep from '@/components/onboarding/OnboardingRoleStep.vue';
import OnboardingPurposeStep from '@/components/onboarding/OnboardingPurposeStep.vue';
import OnboardingReferralStep from '@/components/onboarding/OnboardingReferralStep.vue';

const { t } = useI18n();
const router = useRouter();

const currentStep = ref(1);
const saving = ref(false);
const error = ref(null);

const role = ref('');
const purpose = ref([]);
const referralSource = ref('');

const totalSteps = 3;

const steps = [
    { number: 1, key: 'role' },
    { number: 2, key: 'purpose' },
    { number: 3, key: 'referral' },
];

const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return role.value !== '';
        case 2:
            return purpose.value.length > 0;
        case 3:
            return referralSource.value !== '';
        default:
            return false;
    }
});

const isFirstStep = computed(() => currentStep.value === 1);
const isLastStep = computed(() => currentStep.value === totalSteps);

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

const handleSubmit = async () => {
    if (!canProceed.value || saving.value) return;

    saving.value = true;
    error.value = null;

    try {
        await axios.post('/api/user/onboarding', {
            role: role.value,
            purpose: purpose.value,
            referral_source: referralSource.value,
        });

        router.push('/brands/new');
    } catch (e) {
        error.value = e.response?.data?.message || t('onboarding.error');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-900 flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-2xl">
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
                    <OnboardingRoleStep
                        v-if="currentStep === 1"
                        v-model="role"
                    />
                    <OnboardingPurposeStep
                        v-else-if="currentStep === 2"
                        v-model="purpose"
                    />
                    <OnboardingReferralStep
                        v-else-if="currentStep === 3"
                        v-model="referralSource"
                    />
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
