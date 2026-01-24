<script setup>
import { onMounted } from 'vue';
import { useBrandsStore } from '@/stores/brands';
import OnboardingWizard from '@/components/brand/OnboardingWizard.vue';

const brandsStore = useBrandsStore();

onMounted(() => {
    // Try to restore draft from localStorage
    const hasLoadedDraft = brandsStore.loadOnboardingFromStorage();

    // If no draft, reset to defaults
    if (!hasLoadedDraft) {
        brandsStore.resetOnboarding();
    }
});
</script>

<template>
    <OnboardingWizard />
</template>
