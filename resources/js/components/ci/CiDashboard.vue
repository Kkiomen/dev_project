<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCompetitiveIntelligenceStore } from '@/stores/competitiveIntelligence';
import { useToast } from '@/composables/useToast';
import CompetitorList from './CompetitorList.vue';
import AddCompetitorModal from './AddCompetitorModal.vue';
import InsightsFeed from './InsightsFeed.vue';
import TrendingTopics from './TrendingTopics.vue';
import BenchmarkChart from './BenchmarkChart.vue';
import CostTracker from './CostTracker.vue';
import DiscoverCompetitorsModal from './DiscoverCompetitorsModal.vue';

const { t } = useI18n();
const store = useCompetitiveIntelligenceStore();
const toast = useToast();

const activeTab = ref('overview');
const showAddModal = ref(false);
const showDiscoverModal = ref(false);
const addingDiscovered = ref(false);
const scraping = ref(false);

const hasCompetitors = computed(() => store.competitors.length > 0);

const showOnboarding = computed(() => {
    return hasCompetitors.value
        && store.insights.length === 0
        && store.trends.length === 0
        && !store.benchmarks?.has_data;
});

const hasActiveScrape = computed(() => {
    if (!store.scrapeStatus) return false;
    const runs = store.scrapeStatus.active_runs || store.scrapeStatus;
    return Array.isArray(runs) ? runs.length > 0 : false;
});

const tabs = computed(() => [
    { id: 'overview', label: t('ci.dashboard.overview') },
    { id: 'competitors', label: t('ci.dashboard.competitorsTab') },
    { id: 'insights', label: t('ci.dashboard.insightsTab'), badge: store.unactionedInsights.length },
    { id: 'trends', label: t('ci.dashboard.trendsTab') },
    { id: 'benchmarks', label: t('ci.dashboard.benchmarksTab') },
    { id: 'settings', label: t('ci.dashboard.settingsTab') },
]);

const handleAddCompetitor = async (data) => {
    try {
        await store.addCompetitor(data);
        showAddModal.value = false;
        toast.success(t('ci.competitors.created'));
        activeTab.value = 'overview';
    } catch {
        toast.error(t('common.error'));
    }
};

const handleRemoveCompetitor = async (competitor) => {
    if (!confirm(t('ci.competitors.deleteConfirm'))) return;
    try {
        await store.removeCompetitor(competitor.public_id);
        toast.success(t('ci.competitors.deleted'));
    } catch {
        toast.error(t('common.error'));
    }
};

const handleDiscover = async () => {
    showDiscoverModal.value = true;
    try {
        await store.discoverCompetitors();
    } catch (e) {
        if (e.response?.data?.error_code === 'no_api_key') {
            toast.error(t('ci.discover.noApiKey'));
        } else {
            toast.error(t('common.error'));
        }
    }
};

const handleDiscoverAdd = async (selectedCompetitors) => {
    addingDiscovered.value = true;
    let added = 0;
    let failed = 0;
    try {
        for (const competitor of selectedCompetitors) {
            try {
                await store.addCompetitor({
                    name: competitor.name,
                    notes: competitor.description || null,
                    accounts: competitor.accounts || [],
                });
                added++;
            } catch {
                failed++;
            }
        }
        showDiscoverModal.value = false;
        if (added > 0) {
            toast.success(t('ci.discover.added', { count: added }));
            activeTab.value = 'competitors';
        }
        if (failed > 0) {
            toast.error(t('ci.discover.addFailed', { count: failed }));
        }
    } finally {
        addingDiscovered.value = false;
    }
};

const handleInsightAction = async (insight, action) => {
    try {
        await store.actionInsight(insight.public_id || insight.id, action);
        toast.success(action === 'applied' ? t('ci.insights.applied') : t('ci.insights.dismissed'));
    } catch {
        toast.error(t('common.error'));
    }
};

const triggerScrape = async (type) => {
    scraping.value = true;
    try {
        await store.triggerScrape(type);
        toast.success(t('ci.scrape.dispatched'));
        store.fetchScrapeStatus();
    } catch (e) {
        toast.error(e.response?.data?.message || t('common.error'));
    } finally {
        scraping.value = false;
    }
};

const collectAll = async () => {
    scraping.value = true;
    try {
        await Promise.allSettled([
            store.triggerScrape('profiles'),
            store.triggerScrape('posts'),
            store.triggerScrape('trends'),
        ]);
        toast.success(t('ci.scrape.dispatched'));
        store.fetchScrapeStatus();
    } catch (e) {
        toast.error(e.response?.data?.message || t('common.error'));
    } finally {
        scraping.value = false;
    }
};

onMounted(() => {
    store.fetchDashboardData();
    store.fetchScrapeStatus();
});
</script>

<template>
    <div>
        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-800 overflow-x-auto scrollbar-hide">
            <nav class="flex gap-1 min-w-max">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    class="relative px-4 py-2.5 text-sm font-medium transition-colors whitespace-nowrap"
                    :class="activeTab === tab.id
                        ? 'text-orange-400 border-b-2 border-orange-400'
                        : 'text-gray-400 hover:text-gray-300'"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.badge"
                        class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-orange-500/20 text-orange-400 text-[10px] font-semibold"
                    >
                        {{ tab.badge > 99 ? '99+' : tab.badge }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Overview tab -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
            <!-- Quick stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.dashboard.competitorsTab') }}</p>
                    <p class="text-xl font-bold text-white">{{ store.activeCompetitors.length }}</p>
                </div>
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.dashboard.insightsTab') }}</p>
                    <p class="text-xl font-bold text-white">{{ store.unactionedInsights.length }}</p>
                </div>
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.dashboard.trendsTab') }}</p>
                    <p class="text-xl font-bold text-white">{{ store.risingTrends.length }}</p>
                </div>
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.cost.budget') }}</p>
                    <p class="text-xl font-bold text-white">{{ store.budgetUsagePercent }}%</p>
                </div>
            </div>

            <!-- Scrape status bar -->
            <div
                v-if="hasActiveScrape || scraping"
                class="rounded-xl bg-orange-500/10 border border-orange-500/20 p-3 flex items-center gap-3"
            >
                <div class="w-5 h-5 border-2 border-orange-500 border-t-transparent rounded-full animate-spin shrink-0"></div>
                <p class="text-sm text-orange-400">{{ t('ci.onboarding.scrapeRunning') }}</p>
            </div>

            <!-- Getting Started banner -->
            <div v-if="showOnboarding" class="rounded-xl bg-gray-900 border border-gray-800 p-5">
                <h3 class="text-base font-semibold text-white mb-1">{{ t('ci.onboarding.title') }}</h3>
                <p class="text-sm text-gray-400 mb-5">{{ t('ci.onboarding.description') }}</p>

                <!-- Steps -->
                <div class="flex flex-col sm:flex-row gap-3 mb-5">
                    <div class="flex items-center gap-2.5 flex-1 rounded-lg bg-gray-800/50 border border-gray-700 p-3">
                        <div class="w-7 h-7 rounded-full bg-green-500/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <span class="text-sm text-gray-300">{{ t('ci.onboarding.step1') }}</span>
                    </div>
                    <div class="flex items-center gap-2.5 flex-1 rounded-lg bg-orange-500/10 border border-orange-500/30 p-3">
                        <div class="w-7 h-7 rounded-full bg-orange-500/20 flex items-center justify-center shrink-0 text-sm font-bold text-orange-400">2</div>
                        <span class="text-sm text-orange-400 font-medium">{{ t('ci.onboarding.step2') }}</span>
                    </div>
                    <div class="flex items-center gap-2.5 flex-1 rounded-lg bg-gray-800/50 border border-gray-700 p-3 opacity-50">
                        <div class="w-7 h-7 rounded-full bg-gray-700 flex items-center justify-center shrink-0 text-sm font-bold text-gray-400">3</div>
                        <span class="text-sm text-gray-400">{{ t('ci.onboarding.step3') }}</span>
                    </div>
                </div>

                <!-- Scrape buttons -->
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="collectAll"
                        :disabled="scraping"
                        class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.onboarding.collectAll') }}
                    </button>
                    <button
                        @click="triggerScrape('profiles')"
                        :disabled="scraping"
                        class="rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.scrape.scrapeProfiles') }}
                    </button>
                    <button
                        @click="triggerScrape('posts')"
                        :disabled="scraping"
                        class="rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.scrape.scrapePosts') }}
                    </button>
                    <button
                        @click="triggerScrape('trends')"
                        :disabled="scraping"
                        class="rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.scrape.scrapeTrends') }}
                    </button>
                </div>
            </div>

            <!-- High priority insights -->
            <InsightsFeed
                v-if="store.highPriorityInsights.length"
                :insights="store.highPriorityInsights.slice(0, 5)"
                :loading="store.insightsLoading"
                :has-competitors="hasCompetitors"
                @action="handleInsightAction"
                @scrape="collectAll"
            />

            <!-- Rising trends -->
            <TrendingTopics
                v-if="store.risingTrends.length"
                :trends="store.risingTrends.slice(0, 5)"
                :loading="store.trendsLoading"
                :has-competitors="hasCompetitors"
                @scrape="() => triggerScrape('trends')"
            />
        </div>

        <!-- Competitors tab -->
        <div v-if="activeTab === 'competitors'">
            <CompetitorList
                :competitors="store.competitors"
                :loading="store.competitorsLoading"
                @add="showAddModal = true"
                @remove="handleRemoveCompetitor"
                @discover="handleDiscover"
            />
        </div>

        <!-- Insights tab -->
        <div v-if="activeTab === 'insights'">
            <InsightsFeed
                :insights="store.insights"
                :loading="store.insightsLoading"
                :has-competitors="hasCompetitors"
                @action="handleInsightAction"
                @scrape="collectAll"
            />
        </div>

        <!-- Trends tab -->
        <div v-if="activeTab === 'trends'">
            <TrendingTopics
                :trends="store.trends"
                :loading="store.trendsLoading"
                :has-competitors="hasCompetitors"
                @scrape="() => triggerScrape('trends')"
            />
        </div>

        <!-- Benchmarks tab -->
        <div v-if="activeTab === 'benchmarks'">
            <BenchmarkChart
                :benchmarks="store.benchmarks"
                :loading="store.benchmarksLoading"
                :has-competitors="hasCompetitors"
                @scrape="() => triggerScrape('posts')"
            />
        </div>

        <!-- Settings tab -->
        <div v-if="activeTab === 'settings'" class="space-y-6">
            <!-- Data collection controls -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-4">
                <h3 class="text-sm font-semibold text-white mb-4">{{ t('ci.scrape.title') }}</h3>
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="triggerScrape('profiles')"
                        :disabled="scraping"
                        class="rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.scrape.scrapeProfiles') }}
                    </button>
                    <button
                        @click="triggerScrape('posts')"
                        :disabled="scraping"
                        class="rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.scrape.scrapePosts') }}
                    </button>
                    <button
                        @click="triggerScrape('trends')"
                        :disabled="scraping"
                        class="rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        {{ t('ci.scrape.scrapeTrends') }}
                    </button>
                </div>
            </div>

            <!-- Cost tracker -->
            <CostTracker
                :cost-data="store.costData"
                :loading="store.costLoading"
            />
        </div>

        <!-- Add competitor modal -->
        <AddCompetitorModal
            :show="showAddModal"
            :saving="store.saving"
            @close="showAddModal = false"
            @save="handleAddCompetitor"
        />

        <!-- Discover competitors modal -->
        <DiscoverCompetitorsModal
            :show="showDiscoverModal"
            :competitors="store.discoveredCompetitors"
            :loading="store.discovering"
            :adding="addingDiscovered"
            @close="showDiscoverModal = false"
            @add="handleDiscoverAdd"
            @discover="store.discoverCompetitors()"
        />
    </div>
</template>
