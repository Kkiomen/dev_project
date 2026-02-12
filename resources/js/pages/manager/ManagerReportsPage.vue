<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();

const activeTab = ref('weekly');
const expandedReport = ref(null);

const weeklyReports = computed(() => managerStore.weeklyReports);
const listeningReports = computed(() => managerStore.listeningReports);
const loading = computed(() => managerStore.weeklyReportsLoading || managerStore.listeningReportsLoading);

const toggleExpand = (reportId) => {
    expandedReport.value = expandedReport.value === reportId ? null : reportId;
};

const statusClass = (status) => {
    if (status === 'ready' || status === 'completed') {
        return 'bg-green-500/10 text-green-400 border-green-500/20';
    }
    return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
};

const statusLabel = (status) => {
    if (status === 'ready' || status === 'completed') {
        return t('manager.reports.statusReady');
    }
    return t('manager.reports.statusGenerating');
};

const sentimentBarWidth = (value, total) => {
    if (!total) return '0%';
    return `${Math.round((value / total) * 100)}%`;
};

const formatPeriod = (report) => {
    if (report.period_label) return report.period_label;
    if (report.week_start && report.week_end) {
        const start = new Date(report.week_start).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        const end = new Date(report.week_end).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        return `${start} - ${end}`;
    }
    if (report.period_start && report.period_end) {
        const start = new Date(report.period_start).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        const end = new Date(report.period_end).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        return `${start} - ${end}`;
    }
    return report.created_at ? new Date(report.created_at).toLocaleDateString() : '';
};

const renderSummary = (summary) => {
    if (!summary) return [];
    if (typeof summary === 'string') {
        try {
            summary = JSON.parse(summary);
        } catch {
            return [{ key: 'summary', value: summary }];
        }
    }
    if (typeof summary === 'object' && !Array.isArray(summary)) {
        return Object.entries(summary).map(([key, value]) => ({
            key,
            value: typeof value === 'object' ? JSON.stringify(value) : String(value),
        }));
    }
    return [{ key: 'summary', value: String(summary) }];
};

const loadTabData = () => {
    if (activeTab.value === 'weekly') {
        managerStore.fetchWeeklyReports();
    } else {
        managerStore.fetchListeningReports();
    }
};

watch(activeTab, () => {
    expandedReport.value = null;
    loadTabData();
});

onMounted(() => {
    loadTabData();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">{{ t('manager.reports.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.reports.subtitle') }}</p>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-800">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <button
                    @click="activeTab = 'weekly'"
                    class="whitespace-nowrap border-b-2 pb-3 px-1 text-sm font-medium transition-colors"
                    :class="activeTab === 'weekly'
                        ? 'border-violet-500 text-violet-400'
                        : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-600'"
                >
                    {{ t('manager.reports.tabs.weekly') }}
                </button>
                <button
                    @click="activeTab = 'listening'"
                    class="whitespace-nowrap border-b-2 pb-3 px-1 text-sm font-medium transition-colors"
                    :class="activeTab === 'listening'
                        ? 'border-violet-500 text-violet-400'
                        : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-600'"
                >
                    {{ t('manager.reports.tabs.listening') }}
                </button>
            </nav>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Weekly Reports Tab -->
        <div v-else-if="activeTab === 'weekly'">
            <div v-if="weeklyReports.length" class="space-y-4">
                <div
                    v-for="report in weeklyReports"
                    :key="report.id"
                    class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden"
                >
                    <!-- Report header (clickable) -->
                    <button
                        @click="toggleExpand(report.id)"
                        class="w-full p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 text-left hover:bg-gray-800/30 transition-colors"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-sm font-semibold text-white">{{ formatPeriod(report) }}</h3>
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(report.status)"
                                >
                                    {{ statusLabel(report.status) }}
                                </span>
                            </div>
                            <p v-if="report.summary_excerpt || report.summary" class="text-sm text-gray-400 line-clamp-2">
                                {{ report.summary_excerpt || (typeof report.summary === 'string' ? report.summary.substring(0, 150) : '') }}
                            </p>
                        </div>

                        <!-- Growth metrics highlights -->
                        <div v-if="report.growth_metrics" class="flex flex-wrap gap-4 shrink-0">
                            <div
                                v-for="(value, key) in (typeof report.growth_metrics === 'object' ? report.growth_metrics : {})"
                                :key="key"
                                class="text-center"
                            >
                                <p class="text-xs text-gray-500 capitalize">{{ key }}</p>
                                <p class="text-sm font-bold" :class="Number(value) >= 0 ? 'text-green-400' : 'text-red-400'">
                                    {{ Number(value) >= 0 ? '+' : '' }}{{ value }}
                                </p>
                            </div>
                        </div>

                        <!-- Expand icon -->
                        <svg
                            class="w-5 h-5 text-gray-500 shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': expandedReport === report.id }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Expanded details -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-[2000px]"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 max-h-[2000px]"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div v-if="expandedReport === report.id" class="border-t border-gray-800 p-4 sm:p-5 space-y-6 overflow-hidden">
                            <!-- Summary key-value pairs -->
                            <div v-if="report.summary">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.summary') }}</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div
                                        v-for="item in renderSummary(report.summary)"
                                        :key="item.key"
                                        class="rounded-lg bg-gray-800/50 px-3 py-2"
                                    >
                                        <p class="text-xs text-gray-500 capitalize">{{ item.key.replace(/_/g, ' ') }}</p>
                                        <p class="text-sm text-white mt-0.5">{{ item.value }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Top posts -->
                            <div v-if="report.top_posts && report.top_posts.length">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.topPosts') }}</h4>
                                <div class="space-y-2">
                                    <div
                                        v-for="(post, idx) in report.top_posts"
                                        :key="idx"
                                        class="rounded-lg bg-gray-800/50 px-3 py-2 flex items-center gap-3"
                                    >
                                        <span class="text-xs font-bold text-gray-500 w-5 text-center shrink-0">{{ idx + 1 }}</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-white truncate">{{ post.title || post.content || post.text || '-' }}</p>
                                            <p v-if="post.platform" class="text-xs text-gray-500">{{ post.platform }}</p>
                                        </div>
                                        <div v-if="post.engagement || post.engagement_rate" class="text-right shrink-0">
                                            <p class="text-sm font-semibold text-violet-400">{{ post.engagement || post.engagement_rate }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recommendations -->
                            <div v-if="report.recommendations">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.recommendations') }}</h4>
                                <div class="rounded-lg bg-gray-800/50 px-4 py-3">
                                    <p class="text-sm text-gray-300 whitespace-pre-line">{{ typeof report.recommendations === 'string' ? report.recommendations : JSON.stringify(report.recommendations, null, 2) }}</p>
                                </div>
                            </div>

                            <!-- Platform breakdown -->
                            <div v-if="report.platform_breakdown && typeof report.platform_breakdown === 'object'">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.platformBreakdown') }}</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <div
                                        v-for="(data, platform) in report.platform_breakdown"
                                        :key="platform"
                                        class="rounded-lg bg-gray-800/50 px-3 py-2 text-center"
                                    >
                                        <p class="text-xs text-gray-500 capitalize mb-1">{{ platform }}</p>
                                        <template v-if="typeof data === 'object'">
                                            <div v-for="(val, metric) in data" :key="metric">
                                                <p class="text-xs text-gray-600">{{ metric }}</p>
                                                <p class="text-sm font-semibold text-white">{{ val }}</p>
                                            </div>
                                        </template>
                                        <p v-else class="text-sm font-semibold text-white">{{ data }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Collapse button -->
                            <div class="pt-2">
                                <button
                                    @click="toggleExpand(report.id)"
                                    class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                                >
                                    {{ t('manager.reports.collapse') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-full bg-violet-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.reports.noWeeklyReports') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.reports.noWeeklyReportsDescription') }}</p>
            </div>
        </div>

        <!-- Listening Reports Tab -->
        <div v-else-if="activeTab === 'listening'">
            <div v-if="listeningReports.length" class="space-y-4">
                <div
                    v-for="report in listeningReports"
                    :key="report.id"
                    class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden"
                >
                    <!-- Report header (clickable) -->
                    <button
                        @click="toggleExpand(report.id)"
                        class="w-full p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 text-left hover:bg-gray-800/30 transition-colors"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-sm font-semibold text-white">{{ formatPeriod(report) }}</h3>
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(report.status)"
                                >
                                    {{ statusLabel(report.status) }}
                                </span>
                            </div>

                            <!-- Share of voice overview -->
                            <div v-if="report.share_of_voice != null" class="mt-1 text-sm text-gray-400">
                                {{ t('manager.reports.shareOfVoice') }}: <span class="text-white font-medium">{{ report.share_of_voice }}%</span>
                            </div>

                            <!-- Sentiment breakdown bar -->
                            <div v-if="report.sentiment_breakdown" class="mt-2">
                                <div class="flex rounded-full h-2 overflow-hidden bg-gray-800">
                                    <div
                                        class="bg-green-500 transition-all"
                                        :style="{ width: sentimentBarWidth(report.sentiment_breakdown.positive || 0, (report.sentiment_breakdown.positive || 0) + (report.sentiment_breakdown.neutral || 0) + (report.sentiment_breakdown.negative || 0)) }"
                                    ></div>
                                    <div
                                        class="bg-gray-500 transition-all"
                                        :style="{ width: sentimentBarWidth(report.sentiment_breakdown.neutral || 0, (report.sentiment_breakdown.positive || 0) + (report.sentiment_breakdown.neutral || 0) + (report.sentiment_breakdown.negative || 0)) }"
                                    ></div>
                                    <div
                                        class="bg-red-500 transition-all"
                                        :style="{ width: sentimentBarWidth(report.sentiment_breakdown.negative || 0, (report.sentiment_breakdown.positive || 0) + (report.sentiment_breakdown.neutral || 0) + (report.sentiment_breakdown.negative || 0)) }"
                                    ></div>
                                </div>
                                <div class="flex justify-between mt-1 text-xs text-gray-500">
                                    <span class="text-green-400">{{ report.sentiment_breakdown.positive || 0 }} {{ t('manager.listening.sentiment.positive') }}</span>
                                    <span>{{ report.sentiment_breakdown.neutral || 0 }} {{ t('manager.listening.sentiment.neutral') }}</span>
                                    <span class="text-red-400">{{ report.sentiment_breakdown.negative || 0 }} {{ t('manager.listening.sentiment.negative') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Top mentions count -->
                        <div v-if="report.top_mentions_count != null" class="text-center shrink-0 sm:w-24">
                            <p class="text-2xl font-bold text-white">{{ report.top_mentions_count }}</p>
                            <p class="text-xs text-gray-500">{{ t('manager.reports.topMentions') }}</p>
                        </div>

                        <!-- Expand icon -->
                        <svg
                            class="w-5 h-5 text-gray-500 shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': expandedReport === report.id }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Expanded details -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-[2000px]"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 max-h-[2000px]"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div v-if="expandedReport === report.id" class="border-t border-gray-800 p-4 sm:p-5 space-y-6 overflow-hidden">
                            <!-- Sentiment breakdown details -->
                            <div v-if="report.sentiment_breakdown">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.sentimentBreakdown') }}</h4>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="rounded-lg bg-green-500/5 border border-green-500/10 px-3 py-3 text-center">
                                        <p class="text-2xl font-bold text-green-400">{{ report.sentiment_breakdown.positive || 0 }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('manager.listening.sentiment.positive') }}</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-500/5 border border-gray-500/10 px-3 py-3 text-center">
                                        <p class="text-2xl font-bold text-gray-300">{{ report.sentiment_breakdown.neutral || 0 }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('manager.listening.sentiment.neutral') }}</p>
                                    </div>
                                    <div class="rounded-lg bg-red-500/5 border border-red-500/10 px-3 py-3 text-center">
                                        <p class="text-2xl font-bold text-red-400">{{ report.sentiment_breakdown.negative || 0 }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('manager.listening.sentiment.negative') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Trending keywords -->
                            <div v-if="report.trending_keywords && report.trending_keywords.length">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.trendingKeywords') }}</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="(kw, idx) in report.trending_keywords"
                                        :key="idx"
                                        class="inline-flex items-center rounded-full bg-violet-500/10 text-violet-400 px-3 py-1 text-xs font-medium"
                                    >
                                        {{ typeof kw === 'string' ? kw : kw.keyword || kw.term }}
                                    </span>
                                </div>
                            </div>

                            <!-- AI Summary -->
                            <div v-if="report.ai_summary">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ t('manager.reports.aiSummary') }}</h4>
                                <div class="rounded-lg bg-gray-800/50 px-4 py-3">
                                    <p class="text-sm text-gray-300 whitespace-pre-line">{{ report.ai_summary }}</p>
                                </div>
                            </div>

                            <!-- Collapse button -->
                            <div class="pt-2">
                                <button
                                    @click="toggleExpand(report.id)"
                                    class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                                >
                                    {{ t('manager.reports.collapse') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-full bg-violet-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.reports.noListeningReports') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.reports.noListeningReportsDescription') }}</p>
            </div>
        </div>
    </div>
</template>
