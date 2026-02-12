<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useBrandsStore } from '@/stores/brands';
import { useManagerStore } from '@/stores/manager';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const managerStore = useManagerStore();

const loading = ref(true);
const workflowDismissed = ref(false);

const brandName = computed(() => brandsStore.currentBrand?.name || '');

// --- Crisis Alert ---
const hasCrisisAlerts = computed(() => managerStore.unresolvedCrisisAlerts.length > 0);
const crisisAlertCount = computed(() => managerStore.unresolvedCrisisAlerts.length);

// --- Quick Stats ---
const scheduledTodayCount = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    return managerStore.scheduledPosts.filter((post) => {
        const scheduledDate = new Date(post.scheduled_at);
        return scheduledDate >= today && scheduledDate < tomorrow;
    }).length;
});

const totalFollowers = computed(() => {
    return managerStore.analyticsDashboard?.total_followers ?? 0;
});

const engagementRate = computed(() => {
    const rate = managerStore.analyticsDashboard?.avg_engagement_rate;
    if (rate == null) return '--';
    return `${(rate * 100).toFixed(1)}%`;
});

const quickStats = computed(() => [
    {
        label: t('manager.dashboard.stats.pendingApproval'),
        value: managerStore.pendingApprovalCount,
        color: 'text-amber-400',
        bgColor: 'bg-amber-500/10',
        borderColor: 'border-amber-500/20',
        iconBg: 'bg-amber-500/20',
        route: '/app/manager/approval',
        icon: 'clipboard',
    },
    {
        label: t('manager.dashboard.stats.scheduledToday'),
        value: scheduledTodayCount.value,
        color: 'text-blue-400',
        bgColor: 'bg-blue-500/10',
        borderColor: 'border-blue-500/20',
        iconBg: 'bg-blue-500/20',
        route: '/app/manager/calendar',
        icon: 'clock',
    },
    {
        label: t('manager.dashboard.stats.publishedThisWeek'),
        value: totalFollowers.value,
        color: 'text-emerald-400',
        bgColor: 'bg-emerald-500/10',
        borderColor: 'border-emerald-500/20',
        iconBg: 'bg-emerald-500/20',
        route: '/app/manager/analytics',
        icon: 'chart',
    },
    {
        label: t('manager.dashboard.stats.engagementRate'),
        value: engagementRate.value,
        color: 'text-purple-400',
        bgColor: 'bg-purple-500/10',
        borderColor: 'border-purple-500/20',
        iconBg: 'bg-purple-500/20',
        route: '/app/manager/analytics',
        icon: 'sparkle',
    },
]);

// --- Upcoming Posts ---
const upcomingPosts = computed(() => {
    return managerStore.scheduledPosts
        .filter((post) => post.approval_status === 'approved')
        .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
        .slice(0, 5);
});

// --- Platform Overview ---
const platformSnapshots = computed(() => {
    return managerStore.analyticsDashboard?.snapshots_by_platform ?? [];
});

// --- Quick Actions ---
const quickActions = computed(() => [
    {
        label: t('manager.dashboard.actions.reviewPosts'),
        description: t('manager.dashboard.actions.reviewPostsDesc'),
        icon: 'approval',
        route: '/app/manager/approval',
        color: 'text-amber-400',
        bgColor: 'bg-amber-500/20',
        hoverBorder: 'hover:border-amber-500/50',
    },
    {
        label: t('manager.dashboard.actions.viewCalendar'),
        description: t('manager.dashboard.actions.viewCalendarDesc'),
        icon: 'calendar',
        route: '/app/manager/calendar',
        color: 'text-blue-400',
        bgColor: 'bg-blue-500/20',
        hoverBorder: 'hover:border-blue-500/50',
    },
    {
        label: t('manager.dashboard.actions.viewAnalytics'),
        description: t('manager.dashboard.actions.viewAnalyticsDesc'),
        icon: 'analytics',
        route: '/app/manager/analytics',
        color: 'text-emerald-400',
        bgColor: 'bg-emerald-500/20',
        hoverBorder: 'hover:border-emerald-500/50',
    },
    {
        label: t('manager.dashboard.actions.manageAccounts'),
        description: t('manager.dashboard.actions.manageAccountsDesc'),
        icon: 'accounts',
        route: '/app/manager/accounts',
        color: 'text-purple-400',
        bgColor: 'bg-purple-500/20',
        hoverBorder: 'hover:border-purple-500/50',
    },
]);

// --- Workflow Guidance ---
const needsWorkflowGuidance = computed(() => {
    if (workflowDismissed.value) return false;
    const plan = managerStore.currentPlan;
    if (!plan?.slots?.length) return false;
    const hasPlanned = plan.slots.some(s => s.status === 'planned');
    const hasOtherStatus = plan.slots.some(s => s.status !== 'planned');
    return hasPlanned && !hasOtherStatus;
});

const plannedSlotsCount = computed(() => {
    return managerStore.currentPlan?.slots?.filter(s => s.status === 'planned').length || 0;
});

// --- Helpers ---
const platformColors = {
    instagram: 'bg-pink-500',
    facebook: 'bg-blue-600',
    tiktok: 'bg-gray-100',
    linkedin: 'bg-blue-500',
    x: 'bg-gray-300',
    youtube: 'bg-red-600',
    twitter: 'bg-sky-500',
};

const getPlatformColor = (platform) => {
    return platformColors[platform?.toLowerCase()] || 'bg-gray-500';
};

const truncateText = (text, maxLength = 60) => {
    if (!text) return '';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
};

const relativeTime = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = date - now;
    const diffMins = Math.round(diffMs / 60000);
    if (diffMins < 0) return t('manager.dashboard.pastDue');
    if (diffMins < 60) return t('manager.dashboard.inMinutes', { n: diffMins });
    const diffHours = Math.round(diffMins / 60);
    if (diffHours < 24) return t('manager.dashboard.inHours', { n: diffHours });
    const diffDays = Math.round(diffHours / 24);
    return t('manager.dashboard.inDays', { n: diffDays });
};

const formatNumber = (num) => {
    if (num == null) return '0';
    if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
    if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
    return num.toString();
};

// --- Data Loading ---
onMounted(async () => {
    try {
        await Promise.all([
            managerStore.fetchAnalyticsDashboard(),
            managerStore.fetchScheduledPosts({ approval_status: 'approved', status: 'scheduled' }),
            managerStore.fetchCurrentPlan(),
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
            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-white">
                    {{ t('manager.dashboard.title') }}
                </h1>
                <p class="mt-1 text-sm text-gray-400">
                    {{ t('manager.dashboard.subtitle', { brand: brandName }) }}
                </p>
            </div>

            <!-- Crisis Alert Banner -->
            <div
                v-if="hasCrisisAlerts"
                class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 flex flex-col sm:flex-row items-start sm:items-center gap-3"
            >
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-red-500/20">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-red-300">
                            {{ t('manager.dashboard.crisisAlert') }}
                        </p>
                        <p class="text-xs text-red-400/80 mt-0.5">
                            {{ t('manager.dashboard.crisisAlertCount', { count: crisisAlertCount }) }}
                        </p>
                    </div>
                </div>
                <button
                    @click="router.push('/app/manager/inbox')"
                    class="shrink-0 rounded-lg bg-red-500/20 px-4 py-2 text-sm font-medium text-red-300 hover:bg-red-500/30 transition-colors"
                >
                    {{ t('manager.dashboard.viewAlerts') }}
                </button>
            </div>

            <!-- Workflow Guidance Banner -->
            <div
                v-if="needsWorkflowGuidance"
                class="mb-6 rounded-xl border border-indigo-500/30 bg-indigo-500/5 p-5 sm:p-6"
            >
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-500/20">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-indigo-300">
                                {{ t('manager.dashboard.workflowGuide.title') }}
                            </h3>
                            <p class="text-xs text-indigo-400/70 mt-0.5">
                                {{ t('manager.dashboard.workflowGuide.description', { count: plannedSlotsCount }) }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="workflowDismissed = true"
                        class="shrink-0 p-1 rounded text-indigo-400/50 hover:text-indigo-300 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div class="flex items-start gap-3 rounded-lg bg-gray-900/50 border border-gray-800/50 p-3">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-500/20 flex items-center justify-center text-xs font-bold text-indigo-400">1</span>
                        <div>
                            <p class="text-xs font-medium text-gray-200">{{ t('manager.dashboard.workflowGuide.step1Title') }}</p>
                            <p class="text-[10px] text-gray-500 mt-0.5">{{ t('manager.dashboard.workflowGuide.step1Desc') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-gray-900/50 border border-gray-800/50 p-3">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-500/20 flex items-center justify-center text-xs font-bold text-indigo-400">2</span>
                        <div>
                            <p class="text-xs font-medium text-gray-200">{{ t('manager.dashboard.workflowGuide.step2Title') }}</p>
                            <p class="text-[10px] text-gray-500 mt-0.5">{{ t('manager.dashboard.workflowGuide.step2Desc') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-gray-900/50 border border-gray-800/50 p-3">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-500/20 flex items-center justify-center text-xs font-bold text-indigo-400">3</span>
                        <div>
                            <p class="text-xs font-medium text-gray-200">{{ t('manager.dashboard.workflowGuide.step3Title') }}</p>
                            <p class="text-[10px] text-gray-500 mt-0.5">{{ t('manager.dashboard.workflowGuide.step3Desc') }}</p>
                        </div>
                    </div>
                </div>

                <button
                    @click="router.push('/app/manager/calendar')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ t('manager.dashboard.workflowGuide.cta') }}
                </button>
            </div>

            <!-- Quick Stats Row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
                <button
                    v-for="stat in quickStats"
                    :key="stat.label"
                    @click="router.push(stat.route)"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-5 text-left hover:border-gray-700 transition-all group"
                >
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider leading-tight">
                            {{ stat.label }}
                        </p>
                        <div
                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                            :class="stat.iconBg"
                        >
                            <!-- Clipboard icon -->
                            <svg v-if="stat.icon === 'clipboard'" class="w-4 h-4" :class="stat.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.251 2.251 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                            </svg>
                            <!-- Clock icon -->
                            <svg v-else-if="stat.icon === 'clock'" class="w-4 h-4" :class="stat.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <!-- Chart icon -->
                            <svg v-else-if="stat.icon === 'chart'" class="w-4 h-4" :class="stat.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                            </svg>
                            <!-- Sparkle icon -->
                            <svg v-else-if="stat.icon === 'sparkle'" class="w-4 h-4" :class="stat.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl sm:text-3xl font-bold" :class="stat.color">
                        {{ stat.value }}
                    </p>
                </button>
            </div>

            <!-- Two-Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Upcoming Posts -->
                    <div class="rounded-xl bg-gray-900 border border-gray-800 p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">
                            {{ t('manager.dashboard.upcomingPosts') }}
                        </h3>

                        <!-- Posts List -->
                        <div v-if="upcomingPosts.length > 0" class="space-y-3">
                            <div
                                v-for="post in upcomingPosts"
                                :key="post.id"
                                class="flex items-center gap-3 rounded-lg bg-gray-800/50 border border-gray-700/50 p-3 hover:border-gray-600/50 transition-colors"
                            >
                                <!-- Platform Badge -->
                                <div
                                    class="w-2.5 h-2.5 rounded-full shrink-0"
                                    :class="getPlatformColor(post.platform)"
                                ></div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-200 truncate">
                                        {{ truncateText(post.content || post.text || post.caption, 60) }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ t('manager.dashboard.scheduledFor') }} {{ relativeTime(post.scheduled_at) }}
                                    </p>
                                </div>

                                <!-- Status Badge -->
                                <span class="shrink-0 inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">
                                    {{ post.approval_status }}
                                </span>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="flex flex-col items-center justify-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mb-3 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <p class="text-sm">{{ t('manager.dashboard.noUpcomingPosts') }}</p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="rounded-xl bg-gray-900 border border-gray-800 p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">
                            {{ t('manager.dashboard.recentActivity') }}
                        </h3>
                        <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mb-3 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <p class="text-sm">{{ t('manager.dashboard.activityComingSoon') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Platform Overview -->
                    <div class="rounded-xl bg-gray-900 border border-gray-800 p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">
                            {{ t('manager.dashboard.platformOverview') }}
                        </h3>

                        <!-- Platform Cards -->
                        <div v-if="platformSnapshots.length > 0" class="space-y-3">
                            <div
                                v-for="snapshot in platformSnapshots"
                                :key="snapshot.platform"
                                class="flex items-center gap-3 rounded-lg bg-gray-800/50 border border-gray-700/50 p-3"
                            >
                                <div
                                    class="w-3 h-3 rounded-full shrink-0"
                                    :class="getPlatformColor(snapshot.platform)"
                                ></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-200 capitalize">
                                        {{ snapshot.platform }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-white">
                                        {{ formatNumber(snapshot.followers_count) }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ snapshot.engagement_rate != null ? `${(snapshot.engagement_rate * 100).toFixed(1)}%` : '--' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- No Platform Data -->
                        <div v-else class="flex flex-col items-center justify-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mb-3 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            <p class="text-sm">{{ t('manager.dashboard.noPlatformData') }}</p>
                            <p class="text-xs text-gray-600 mt-1">{{ t('manager.dashboard.connectAccountsFirst') }}</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="rounded-xl bg-gray-900 border border-gray-800 p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">
                            {{ t('manager.dashboard.quickActions') }}
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button
                                v-for="action in quickActions"
                                :key="action.label"
                                @click="router.push(action.route)"
                                class="flex items-start gap-3 rounded-lg bg-gray-800/50 border border-gray-700/50 p-3 text-left transition-all group"
                                :class="action.hoverBorder"
                            >
                                <div
                                    class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition"
                                    :class="action.bgColor"
                                >
                                    <svg v-if="action.icon === 'approval'" class="w-4.5 h-4.5" :class="action.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <svg v-else-if="action.icon === 'calendar'" class="w-4.5 h-4.5" :class="action.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <svg v-else-if="action.icon === 'analytics'" class="w-4.5 h-4.5" :class="action.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <svg v-else-if="action.icon === 'accounts'" class="w-4.5 h-4.5" :class="action.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-white">{{ action.label }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ action.description }}</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
