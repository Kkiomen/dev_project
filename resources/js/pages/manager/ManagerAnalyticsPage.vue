<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();

const period = ref('7d');
const periods = ['7d', '30d', '90d'];

const dashboard = computed(() => managerStore.analyticsDashboard);
const scores = computed(() => managerStore.performanceScores || []);
const loading = computed(() => managerStore.analyticsLoading);
const crisisAlerts = computed(() => managerStore.unresolvedCrisisAlerts || []);

const metricKeys = ['followers', 'reach', 'engagementRate', 'posts'];

const getMetricValue = (key) => {
    if (!dashboard.value) return '--';
    const map = {
        followers: dashboard.value.total_followers,
        reach: dashboard.value.total_reach,
        engagementRate: dashboard.value.engagement_rate,
        posts: dashboard.value.total_posts,
    };
    const val = map[key];
    if (val === undefined || val === null) return '--';
    if (key === 'engagementRate') return formatPercent(val);
    return formatNumber(val);
};

const metricColors = {
    followers: 'text-blue-400',
    reach: 'text-purple-400',
    engagementRate: 'text-emerald-400',
    posts: 'text-amber-400',
};

const metricIcons = {
    followers: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    reach: 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    engagementRate: 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605',
    posts: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
};

const formatNumber = (num) => {
    if (num === null || num === undefined) return '--';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
};

const formatPercent = (num) => {
    if (num === null || num === undefined) return '--';
    return parseFloat(num).toFixed(1) + '%';
};

const scoreColorClasses = {
    excellent: 'bg-emerald-500/10 text-emerald-400',
    good: 'bg-blue-500/10 text-blue-400',
    average: 'bg-amber-500/10 text-amber-400',
    below_average: 'bg-orange-500/10 text-orange-400',
    poor: 'bg-red-500/10 text-red-400',
};

const platformColors = {
    instagram: 'bg-pink-500',
    facebook: 'bg-blue-600',
    tiktok: 'bg-gray-100 text-black',
    linkedin: 'bg-blue-700',
    x: 'bg-gray-700',
    youtube: 'bg-red-600',
};

const platformBreakdown = computed(() => {
    if (!dashboard.value?.platform_breakdown) return [];
    return dashboard.value.platform_breakdown;
});

const loadData = async () => {
    await Promise.all([
        managerStore.fetchAnalyticsDashboard({ period: period.value }),
        managerStore.fetchPerformanceScores({ period: period.value }),
    ]);
};

onMounted(() => {
    loadData();
});

watch(period, () => {
    loadData();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Crisis Alert Banner -->
        <div
            v-if="crisisAlerts.length > 0"
            class="mb-6 rounded-xl bg-red-900/20 border border-red-800/50 p-4 flex items-start gap-3"
        >
            <svg class="w-6 h-6 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <div>
                <h3 class="text-sm font-semibold text-red-300">
                    {{ t('manager.analytics.crisisAlert') }}
                </h3>
                <p class="mt-1 text-xs text-red-400/80">
                    {{ crisisAlerts.length }} {{ t('manager.analytics.unresolvedAlerts') }}
                </p>
            </div>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('manager.analytics.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('manager.analytics.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-for="p in periods"
                    :key="p"
                    @click="period = p"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                    :class="period === p
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-800 text-gray-400 hover:text-gray-200 hover:bg-gray-700'"
                >
                    {{ t(`manager.analytics.period.${p}`) }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <LoadingSpinner size="lg" />
        </div>

        <template v-else>
            <!-- Metric Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div
                    v-for="metric in metricKeys"
                    :key="metric"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="metricIcons[metric]" />
                        </svg>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ t(`manager.analytics.metrics.${metric}`) }}
                        </p>
                    </div>
                    <p class="text-2xl font-bold" :class="metricColors[metric]">
                        {{ getMetricValue(metric) }}
                    </p>
                </div>
            </div>

            <!-- Platform Breakdown -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-4">
                    {{ t('manager.analytics.platformBreakdown') }}
                </h2>
                <div v-if="platformBreakdown.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="platform in platformBreakdown"
                        :key="platform.platform"
                        class="rounded-xl bg-gray-900 border border-gray-800 p-4 flex items-center gap-4"
                    >
                        <span
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0"
                            :class="platformColors[platform.platform] || 'bg-gray-600'"
                        >
                            {{ (platform.platform || '?')[0].toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white capitalize">{{ platform.platform }}</p>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-xs text-gray-400">
                                    {{ formatNumber(platform.followers) }} {{ t('manager.analytics.metrics.followers').toLowerCase() }}
                                </span>
                                <span class="text-xs text-gray-600">|</span>
                                <span class="text-xs text-gray-400">
                                    {{ formatPercent(platform.engagement_rate) }} {{ t('manager.analytics.metrics.engagementRate').toLowerCase() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-xl bg-gray-900 border border-gray-800 p-8 text-center"
                >
                    <p class="text-sm text-gray-500">{{ t('manager.analytics.noDataYet') }}</p>
                </div>
            </div>

            <!-- Performance Scores -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-4">
                    {{ t('manager.analytics.performanceScores') }}
                </h2>
                <div v-if="scores.length > 0" class="space-y-3">
                    <div
                        v-for="score in scores"
                        :key="score.id"
                        class="rounded-xl bg-gray-900 border border-gray-800 p-4 flex flex-col sm:flex-row sm:items-center gap-3"
                    >
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <span
                                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                :class="platformColors[score.platform] || 'bg-gray-600'"
                            >
                                {{ (score.platform || '?')[0].toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-300 truncate">
                                    {{ score.post_title || score.content_preview || '--' }}
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ score.scored_at ? new Date(score.scored_at).toLocaleDateString() : '' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-sm font-bold text-white">{{ score.overall_score ?? '--' }}</span>
                            <span
                                class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="scoreColorClasses[score.score_label] || 'bg-gray-700 text-gray-300'"
                            >
                                {{ score.score_label ? t(`manager.analytics.scoreLabel.${score.score_label}`) : '--' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-xl bg-gray-900 border border-gray-800 p-8 text-center"
                >
                    <p class="text-sm text-gray-500">{{ t('manager.analytics.noDataYet') }}</p>
                </div>
            </div>

            <!-- Chart Placeholder -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <div class="flex flex-col items-center justify-center py-16 text-gray-500">
                    <svg class="w-16 h-16 mb-3 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.analytics.chartsComingSoon') }}</h3>
                    <p class="text-sm text-gray-400 max-w-md text-center">{{ t('manager.analytics.chartsDescription') }}</p>
                </div>
            </div>
        </template>
    </div>
</template>
