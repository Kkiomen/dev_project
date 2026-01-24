<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import OnboardingStep1 from '@/components/brand/OnboardingStep1.vue';
import OnboardingStep2 from '@/components/brand/OnboardingStep2.vue';
import OnboardingStep3 from '@/components/brand/OnboardingStep3.vue';
import OnboardingStep4 from '@/components/brand/OnboardingStep4.vue';
import OnboardingStep5 from '@/components/brand/OnboardingStep5.vue';
import AutomationPanel from '@/components/brand/AutomationPanel.vue';

const props = defineProps({
    brandId: {
        type: [String, Number],
        required: true,
    },
});

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const toast = useToast();

const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const activeTab = ref('basics');
const brandName = ref('');

const tabs = [
    { key: 'basics', icon: 'info', component: OnboardingStep1 },
    { key: 'audience', icon: 'users', component: OnboardingStep2 },
    { key: 'voice', icon: 'mic', component: OnboardingStep3 },
    { key: 'pillars', icon: 'layers', component: OnboardingStep4 },
    { key: 'platforms', icon: 'share', component: OnboardingStep5 },
    { key: 'automation', icon: 'automation', component: AutomationPanel, passBrandId: true },
];

const currentTabComponent = computed(() => {
    return tabs.find(t => t.key === activeTab.value)?.component;
});

const tabIcons = {
    info: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
    users: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>`,
    mic: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>`,
    layers: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>`,
    share: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>`,
    automation: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>`,
};

const currentTab = computed(() => {
    return tabs.find(t => t.key === activeTab.value);
});

const loadBrand = async () => {
    loading.value = true;
    error.value = null;

    try {
        const brand = await brandsStore.fetchBrand(props.brandId);
        brandName.value = brand.name || '';

        // Populate onboarding data from brand
        brandsStore.updateOnboardingData({
            name: brand.name || '',
            industry: brand.industry || '',
            description: brand.description || '',
            ageRange: brand.target_audience?.age_range || '25-40',
            gender: brand.target_audience?.gender || 'all',
            interests: brand.target_audience?.interests || [],
            painPoints: brand.target_audience?.pain_points || [],
            tone: brand.voice?.tone || 'professional',
            personality: brand.voice?.personality || [],
            language: brand.voice?.language || 'pl',
            emojiUsage: brand.voice?.emoji_usage || 'sometimes',
            contentPillars: brand.content_pillars || [],
            platforms: brand.platforms || {
                facebook: { enabled: false },
                instagram: { enabled: false },
                youtube: { enabled: false },
            },
            frequency: brand.posting_preferences?.frequency || {
                facebook: 3,
                instagram: 5,
                youtube: 1,
            },
            bestTimes: brand.posting_preferences?.best_times || {
                facebook: ['09:00', '18:00'],
                instagram: ['12:00', '20:00'],
                youtube: ['17:00'],
            },
            autoSchedule: brand.posting_preferences?.auto_schedule ?? true,
        });
    } catch (e) {
        error.value = e.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
};

onMounted(loadBrand);

const handleSave = async () => {
    saving.value = true;
    error.value = null;

    try {
        const data = brandsStore.onboardingData;
        const brandData = {
            name: data.name,
            industry: data.industry,
            description: data.description,
            target_audience: {
                age_range: data.ageRange,
                gender: data.gender,
                interests: data.interests,
                pain_points: data.painPoints,
            },
            voice: {
                tone: data.tone,
                personality: data.personality,
                language: data.language,
                emoji_usage: data.emojiUsage,
            },
            content_pillars: data.contentPillars,
            posting_preferences: {
                frequency: data.frequency,
                best_times: data.bestTimes,
                auto_schedule: data.autoSchedule,
            },
            platforms: data.platforms,
        };

        await brandsStore.updateBrand(props.brandId, brandData);
        toast.success(t('brands.brandUpdated'));
        router.push({ name: 'brands' });
    } catch (e) {
        error.value = e.response?.data?.message || t('common.error');
    } finally {
        saving.value = false;
    }
};

const handleCancel = () => {
    brandsStore.resetOnboarding();
    router.push({ name: 'brands' });
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <RouterLink
                            :to="{ name: 'brands' }"
                            class="p-2 -ml-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </RouterLink>
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">
                                {{ t('brands.editBrand') }}
                            </h1>
                            <p v-if="brandName" class="mt-0.5 text-sm text-gray-500">
                                {{ brandName }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <Button variant="secondary" @click="handleCancel">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button @click="handleSave" :loading="saving" :disabled="saving">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <div v-if="loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <div v-else-if="error && !brandsStore.onboardingData.name" class="text-center py-20">
                <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-red-600 mb-4">{{ error }}</p>
                <RouterLink :to="{ name: 'brands' }">
                    <Button variant="secondary">
                        {{ t('common.back') }}
                    </Button>
                </RouterLink>
            </div>

            <div v-else class="max-w-5xl mx-auto">
                <div class="flex gap-6">
                    <!-- Sidebar Navigation -->
                    <nav class="w-56 flex-shrink-0">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden sticky top-24">
                            <ul class="divide-y divide-gray-100">
                                <li v-for="tab in tabs" :key="tab.key">
                                    <button
                                        @click="activeTab = tab.key"
                                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium transition-colors text-left"
                                        :class="[
                                            activeTab === tab.key
                                                ? 'bg-blue-50 text-blue-700 border-l-2 border-blue-600'
                                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-2 border-transparent'
                                        ]"
                                    >
                                        <span
                                            :class="activeTab === tab.key ? 'text-blue-600' : 'text-gray-400'"
                                            v-html="tabIcons[tab.icon]"
                                        />
                                        {{ t(`brands.onboarding.steps.${tab.key}`) }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </nav>

                    <!-- Tab Content -->
                    <div class="flex-1 min-w-0">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <Transition
                                mode="out-in"
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0 translate-x-4"
                                enter-to-class="opacity-100 translate-x-0"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100 translate-x-0"
                                leave-to-class="opacity-0 -translate-x-4"
                            >
                                <component
                                    :is="currentTabComponent"
                                    :key="activeTab"
                                    v-bind="currentTab?.passBrandId ? { brandId: props.brandId } : {}"
                                />
                            </Transition>

                            <!-- Error message -->
                            <div v-if="error" class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm text-red-600">{{ error }}</p>
                            </div>
                        </div>

                        <!-- Bottom Actions (mobile-friendly) -->
                        <div class="mt-6 flex items-center justify-end gap-3 sm:hidden">
                            <Button variant="secondary" @click="handleCancel">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button @click="handleSave" :loading="saving" :disabled="saving">
                                {{ t('common.save') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
