<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useManagerStore } from '@/stores/manager';
import { useBrandsStore } from '@/stores/brands';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const router = useRouter();
const managerStore = useManagerStore();
const brandsStore = useBrandsStore();

const loading = ref(true);

// --- Step completion checks ---
const isStep1Complete = computed(() => managerStore.accounts.length > 0);
const isStep2Complete = computed(() => !!managerStore.brandKit);
const isStep3Complete = computed(() => managerStore.strategy?.status === 'active');
const isStep4Complete = computed(() => managerStore.contentPlans.length > 0);

const steps = computed(() => [
    {
        id: 1,
        key: 'connectAccounts',
        icon: 'link',
        route: '/app/manager/accounts',
        complete: isStep1Complete.value,
        color: 'indigo',
    },
    {
        id: 2,
        key: 'brandKit',
        icon: 'palette',
        route: '/app/manager/brand',
        complete: isStep2Complete.value,
        color: 'purple',
    },
    {
        id: 3,
        key: 'strategy',
        icon: 'lightbulb',
        route: '/app/manager/strategy',
        complete: isStep3Complete.value,
        color: 'amber',
    },
    {
        id: 4,
        key: 'contentPlan',
        icon: 'calendar',
        route: '/app/manager/content',
        complete: isStep4Complete.value,
        color: 'blue',
    },
    {
        id: 5,
        key: 'reviewApprove',
        icon: 'check',
        route: '/app/manager/approval',
        complete: false,
        color: 'emerald',
    },
    {
        id: 6,
        key: 'monitorEngage',
        icon: 'chat',
        route: '/app/manager/inbox',
        complete: false,
        color: 'pink',
    },
    {
        id: 7,
        key: 'analyzePerformance',
        icon: 'chart',
        route: '/app/manager/analytics',
        complete: false,
        color: 'cyan',
    },
]);

const completedCount = computed(() => steps.value.filter((s) => s.complete).length);
const progressPercentage = computed(() => Math.round((completedCount.value / steps.value.length) * 100));

const firstIncompleteStep = computed(() => {
    const step = steps.value.find((s) => !s.complete);
    return step?.id ?? null;
});

const flowSteps = computed(() => [
    { key: 'strategy', icon: 'lightbulb', color: 'amber' },
    { key: 'contentPlan', icon: 'calendar', color: 'blue' },
    { key: 'aiCreates', icon: 'sparkle', color: 'purple' },
    { key: 'youApprove', icon: 'check', color: 'emerald' },
    { key: 'autoPublish', icon: 'rocket', color: 'indigo' },
    { key: 'aiAnalyzes', icon: 'chart', color: 'cyan' },
    { key: 'repeat', icon: 'refresh', color: 'pink' },
]);

const tips = computed(() => [
    { key: 'fiveMinutes', icon: 'clock', color: 'emerald' },
    { key: 'aiLearns', icon: 'brain', color: 'purple' },
    { key: 'crisisAlerts', icon: 'shield', color: 'red' },
]);

// --- Color utility maps ---
const colorMap = {
    indigo: {
        bg: 'bg-indigo-500/10',
        border: 'border-indigo-500/20',
        iconBg: 'bg-indigo-500/20',
        text: 'text-indigo-400',
        hoverBorder: 'hover:border-indigo-500/40',
        progressBg: 'bg-indigo-500',
        ring: 'ring-indigo-500/30',
    },
    purple: {
        bg: 'bg-purple-500/10',
        border: 'border-purple-500/20',
        iconBg: 'bg-purple-500/20',
        text: 'text-purple-400',
        hoverBorder: 'hover:border-purple-500/40',
        progressBg: 'bg-purple-500',
        ring: 'ring-purple-500/30',
    },
    amber: {
        bg: 'bg-amber-500/10',
        border: 'border-amber-500/20',
        iconBg: 'bg-amber-500/20',
        text: 'text-amber-400',
        hoverBorder: 'hover:border-amber-500/40',
        progressBg: 'bg-amber-500',
        ring: 'ring-amber-500/30',
    },
    blue: {
        bg: 'bg-blue-500/10',
        border: 'border-blue-500/20',
        iconBg: 'bg-blue-500/20',
        text: 'text-blue-400',
        hoverBorder: 'hover:border-blue-500/40',
        progressBg: 'bg-blue-500',
        ring: 'ring-blue-500/30',
    },
    emerald: {
        bg: 'bg-emerald-500/10',
        border: 'border-emerald-500/20',
        iconBg: 'bg-emerald-500/20',
        text: 'text-emerald-400',
        hoverBorder: 'hover:border-emerald-500/40',
        progressBg: 'bg-emerald-500',
        ring: 'ring-emerald-500/30',
    },
    pink: {
        bg: 'bg-pink-500/10',
        border: 'border-pink-500/20',
        iconBg: 'bg-pink-500/20',
        text: 'text-pink-400',
        hoverBorder: 'hover:border-pink-500/40',
        progressBg: 'bg-pink-500',
        ring: 'ring-pink-500/30',
    },
    cyan: {
        bg: 'bg-cyan-500/10',
        border: 'border-cyan-500/20',
        iconBg: 'bg-cyan-500/20',
        text: 'text-cyan-400',
        hoverBorder: 'hover:border-cyan-500/40',
        progressBg: 'bg-cyan-500',
        ring: 'ring-cyan-500/30',
    },
    red: {
        bg: 'bg-red-500/10',
        border: 'border-red-500/20',
        iconBg: 'bg-red-500/20',
        text: 'text-red-400',
        hoverBorder: 'hover:border-red-500/40',
        progressBg: 'bg-red-500',
        ring: 'ring-red-500/30',
    },
};

const getColors = (color) => colorMap[color] || colorMap.indigo;

// --- Actions ---
const navigateToStep = (step) => {
    router.push(step.route);
};

const scrollToFirstIncomplete = () => {
    if (firstIncompleteStep.value === null) return;
    const el = document.getElementById(`tutorial-step-${firstIncompleteStep.value}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

// --- Data Loading ---
onMounted(async () => {
    try {
        await Promise.all([
            managerStore.fetchAccounts(),
            managerStore.fetchBrandKit(),
            managerStore.fetchStrategy(),
            managerStore.fetchContentPlans(),
        ]);
    } catch {
        // Errors handled by store
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-32">
            <LoadingSpinner size="lg" />
        </div>

        <template v-else>
            <!-- Header -->
            <div class="mb-8">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-white">
                            {{ t('manager.tutorial.title') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-400">
                            {{ t('manager.tutorial.subtitle') }}
                        </p>
                    </div>
                    <button
                        v-if="firstIncompleteStep !== null"
                        @click="scrollToFirstIncomplete"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-500 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                        </svg>
                        {{ t('manager.tutorial.startButton') }}
                    </button>
                </div>

                <!-- Progress Bar -->
                <div class="mt-6 rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-medium text-gray-300">
                            {{ t('manager.tutorial.progress') }}
                        </p>
                        <p class="text-sm font-semibold text-white">
                            {{ completedCount }} / {{ steps.length }}
                        </p>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-gray-800">
                        <div
                            class="h-2.5 rounded-full bg-indigo-500 transition-all duration-500"
                            :style="{ width: `${progressPercentage}%` }"
                        ></div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ t('manager.tutorial.progressHint') }}
                    </p>
                </div>
            </div>

            <!-- Steps -->
            <div class="space-y-4 mb-12">
                <button
                    v-for="step in steps"
                    :id="`tutorial-step-${step.id}`"
                    :key="step.id"
                    @click="navigateToStep(step)"
                    class="w-full text-left rounded-xl bg-gray-900 border p-4 sm:p-6 transition-all group"
                    :class="[
                        step.complete
                            ? 'border-emerald-500/30 hover:border-emerald-500/50'
                            : `border-gray-800 ${getColors(step.color).hoverBorder}`,
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <!-- Step number / check indicator -->
                        <div class="shrink-0">
                            <div
                                v-if="step.complete"
                                class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center ring-2 ring-emerald-500/30"
                            >
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <div
                                v-else
                                class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center ring-2"
                                :class="[getColors(step.color).iconBg, getColors(step.color).ring]"
                            >
                                <span class="text-lg sm:text-xl font-bold" :class="getColors(step.color).text">
                                    {{ step.id }}
                                </span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <!-- Icon -->
                                <div
                                    class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                    :class="getColors(step.color).iconBg"
                                >
                                    <!-- Link icon -->
                                    <svg v-if="step.icon === 'link'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                    <!-- Palette icon -->
                                    <svg v-else-if="step.icon === 'palette'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
                                    </svg>
                                    <!-- Lightbulb icon -->
                                    <svg v-else-if="step.icon === 'lightbulb'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                                    </svg>
                                    <!-- Calendar icon -->
                                    <svg v-else-if="step.icon === 'calendar'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <!-- Check circle icon -->
                                    <svg v-else-if="step.icon === 'check'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Chat icon -->
                                    <svg v-else-if="step.icon === 'chat'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                                    </svg>
                                    <!-- Chart icon -->
                                    <svg v-else-if="step.icon === 'chart'" class="w-4 h-4" :class="getColors(step.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                </div>

                                <h3 class="text-base sm:text-lg font-semibold text-white group-hover:text-gray-100">
                                    {{ t(`manager.tutorial.steps.${step.key}.title`) }}
                                </h3>

                                <!-- Completed badge -->
                                <span
                                    v-if="step.complete"
                                    class="hidden sm:inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20"
                                >
                                    {{ t('manager.tutorial.completed') }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-400 mt-1">
                                {{ t(`manager.tutorial.steps.${step.key}.description`) }}
                            </p>

                            <!-- Action button -->
                            <div class="mt-3">
                                <span
                                    class="inline-flex items-center gap-1.5 text-sm font-medium transition-colors"
                                    :class="step.complete ? 'text-emerald-400 group-hover:text-emerald-300' : `${getColors(step.color).text}`"
                                >
                                    {{ t(`manager.tutorial.steps.${step.key}.button`) }}
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </button>
            </div>

            <!-- How It Works Section -->
            <div class="mb-12">
                <h2 class="text-xl sm:text-2xl font-bold text-white mb-6">
                    {{ t('manager.tutorial.howItWorks.title') }}
                </h2>

                <!-- Desktop: horizontal flow -->
                <div class="hidden lg:block rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <div class="flex items-center justify-between">
                        <template v-for="(flowStep, index) in flowSteps" :key="flowStep.key">
                            <!-- Step item -->
                            <div class="flex flex-col items-center text-center flex-1">
                                <div
                                    class="w-12 h-12 rounded-xl flex items-center justify-center mb-2"
                                    :class="getColors(flowStep.color).iconBg"
                                >
                                    <!-- Lightbulb -->
                                    <svg v-if="flowStep.icon === 'lightbulb'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                                    </svg>
                                    <!-- Calendar -->
                                    <svg v-else-if="flowStep.icon === 'calendar'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <!-- Sparkle -->
                                    <svg v-else-if="flowStep.icon === 'sparkle'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                                    </svg>
                                    <!-- Check -->
                                    <svg v-else-if="flowStep.icon === 'check'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Rocket -->
                                    <svg v-else-if="flowStep.icon === 'rocket'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                    </svg>
                                    <!-- Chart -->
                                    <svg v-else-if="flowStep.icon === 'chart'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <!-- Refresh -->
                                    <svg v-else-if="flowStep.icon === 'refresh'" class="w-5 h-5" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium" :class="getColors(flowStep.color).text">
                                    {{ t(`manager.tutorial.howItWorks.steps.${flowStep.key}`) }}
                                </p>
                            </div>

                            <!-- Arrow between steps -->
                            <div v-if="index < flowSteps.length - 1" class="shrink-0 px-1">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Mobile/Tablet: vertical flow -->
                <div class="lg:hidden rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <div class="space-y-3">
                        <template v-for="(flowStep, index) in flowSteps" :key="flowStep.key">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                    :class="getColors(flowStep.color).iconBg"
                                >
                                    <!-- Lightbulb -->
                                    <svg v-if="flowStep.icon === 'lightbulb'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                                    </svg>
                                    <!-- Calendar -->
                                    <svg v-else-if="flowStep.icon === 'calendar'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <!-- Sparkle -->
                                    <svg v-else-if="flowStep.icon === 'sparkle'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                                    </svg>
                                    <!-- Check -->
                                    <svg v-else-if="flowStep.icon === 'check'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Rocket -->
                                    <svg v-else-if="flowStep.icon === 'rocket'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                    </svg>
                                    <!-- Chart -->
                                    <svg v-else-if="flowStep.icon === 'chart'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <!-- Refresh -->
                                    <svg v-else-if="flowStep.icon === 'refresh'" class="w-4 h-4" :class="getColors(flowStep.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium" :class="getColors(flowStep.color).text">
                                    {{ t(`manager.tutorial.howItWorks.steps.${flowStep.key}`) }}
                                </p>
                            </div>

                            <!-- Arrow down between steps -->
                            <div v-if="index < flowSteps.length - 1" class="flex justify-center pl-5">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                </svg>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tips Section -->
            <div class="mb-8">
                <h2 class="text-xl sm:text-2xl font-bold text-white mb-6">
                    {{ t('manager.tutorial.tips.title') }}
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div
                        v-for="tip in tips"
                        :key="tip.key"
                        class="rounded-xl bg-gray-900 border border-gray-800 p-5"
                    >
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center mb-3"
                            :class="getColors(tip.color).iconBg"
                        >
                            <!-- Clock -->
                            <svg v-if="tip.icon === 'clock'" class="w-5 h-5" :class="getColors(tip.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <!-- Brain -->
                            <svg v-else-if="tip.icon === 'brain'" class="w-5 h-5" :class="getColors(tip.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                            </svg>
                            <!-- Shield -->
                            <svg v-else-if="tip.icon === 'shield'" class="w-5 h-5" :class="getColors(tip.color).text" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-white mb-1">
                            {{ t(`manager.tutorial.tips.${tip.key}.title`) }}
                        </h3>
                        <p class="text-xs text-gray-400 leading-relaxed">
                            {{ t(`manager.tutorial.tips.${tip.key}.description`) }}
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
