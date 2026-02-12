<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRssFeedsStore } from '@/stores/rssFeeds';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import AddRssFeedModal from '@/components/rssFeeds/AddRssFeedModal.vue';

const { t } = useI18n();
const rssStore = useRssFeedsStore();
const { confirm } = useConfirm();
const toast = useToast();

const activeTab = ref('feeds');
const showAddModal = ref(false);
const loading = ref(true);
const refreshingFeedId = ref(null);

// Articles filters
const searchQuery = ref('');
const searchDebounced = ref('');
const selectedFeedId = ref('');
const selectedPeriod = ref('all');
const currentPage = ref(1);

let searchTimeout = null;
watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchDebounced.value = val;
    }, 300);
});

const statusColors = {
    green: 'bg-green-500/20 text-green-400',
    yellow: 'bg-yellow-500/20 text-yellow-400',
    red: 'bg-red-500/20 text-red-400',
};

const periodOptions = computed(() => [
    { value: 'today', label: t('rssFeeds.periodToday') },
    { value: '3days', label: t('rssFeeds.period3Days') },
    { value: '7days', label: t('rssFeeds.period7Days') },
    { value: '30days', label: t('rssFeeds.period30Days') },
    { value: 'all', label: t('rssFeeds.periodAll') },
]);

const getSinceDate = (period) => {
    const now = new Date();
    switch (period) {
        case 'today': {
            const d = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            return d.toISOString();
        }
        case '3days': {
            const d = new Date(now);
            d.setDate(d.getDate() - 3);
            return d.toISOString();
        }
        case '7days': {
            const d = new Date(now);
            d.setDate(d.getDate() - 7);
            return d.toISOString();
        }
        case '30days': {
            const d = new Date(now);
            d.setDate(d.getDate() - 30);
            return d.toISOString();
        }
        default:
            return null;
    }
};

const fetchData = async () => {
    loading.value = true;
    try {
        await rssStore.fetchFeeds();
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

watch(activeTab, (tab) => {
    if (tab === 'articles') {
        loadArticles();
    }
});

watch([searchDebounced, selectedFeedId, selectedPeriod], () => {
    currentPage.value = 1;
    if (activeTab.value === 'articles') {
        loadArticles();
    }
});

const loadArticles = async () => {
    const params = {
        page: currentPage.value,
        per_page: 20,
    };
    if (searchDebounced.value) params.search = searchDebounced.value;
    if (selectedFeedId.value) params.feed_id = selectedFeedId.value;
    const since = getSinceDate(selectedPeriod.value);
    if (since) params.since = since;

    await rssStore.fetchArticles(params);
};

const goToPage = (page) => {
    currentPage.value = page;
    loadArticles();
};

const handleFeedCreated = () => {
    showAddModal.value = false;
    toast.success(t('rssFeeds.feedCreated'));
};

const handleRefresh = async (feed) => {
    refreshingFeedId.value = feed.id;
    try {
        await rssStore.refreshFeed(feed.id);
        toast.success(t('rssFeeds.feedRefreshed'));
    } catch (error) {
        toast.error(error.response?.data?.message || t('rssFeeds.refreshError'));
    } finally {
        refreshingFeedId.value = null;
    }
};

const handleToggleStatus = async (feed) => {
    const newStatus = feed.status === 'active' ? 'paused' : 'active';
    try {
        await rssStore.updateFeed(feed.id, { status: newStatus });
        toast.success(newStatus === 'active' ? t('rssFeeds.feedResumed') : t('rssFeeds.feedPaused'));
    } catch (error) {
        toast.error(error.response?.data?.message || t('common.error'));
    }
};

const handleDelete = async (feed) => {
    const confirmed = await confirm({
        title: t('rssFeeds.deleteFeed'),
        message: t('rssFeeds.deleteFeedConfirm', { name: feed.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await rssStore.deleteFeed(feed.id);
            toast.success(t('rssFeeds.feedDeleted'));
        } catch (error) {
            toast.error(error.response?.data?.message || t('common.error'));
        }
    }
};

const showFeedArticles = (feed) => {
    selectedFeedId.value = feed.id;
    activeTab.value = 'articles';
};

const truncateUrl = (url) => {
    try {
        const parsed = new URL(url);
        return parsed.hostname;
    } catch {
        return url;
    }
};

const relativeTime = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHr = Math.floor(diffMs / 3600000);
    const diffDay = Math.floor(diffMs / 86400000);

    if (diffMin < 1) return t('rssFeeds.timeJustNow');
    if (diffMin < 60) return t('rssFeeds.timeMinutesAgo', { n: diffMin });
    if (diffHr < 24) return t('rssFeeds.timeHoursAgo', { n: diffHr });
    if (diffDay === 1) return t('rssFeeds.timeYesterday');
    if (diffDay < 7) return t('rssFeeds.timeDaysAgo', { n: diffDay });

    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
};

const totalPages = computed(() => {
    if (!rssStore.articlesMeta) return 1;
    return rssStore.articlesMeta.last_page || 1;
});

const articlesTotal = computed(() => {
    return rssStore.articlesMeta?.total || 0;
});
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ t('rssFeeds.title') }}</h1>
                    <p class="text-gray-400 mt-1">{{ t('rssFeeds.subtitle') }}</p>
                </div>
                <Button @click="showAddModal = true">
                    <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('rssFeeds.addFeed') }}
                </Button>
            </div>

            <!-- Stats bar -->
            <div v-if="!loading && rssStore.feeds.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                <div class="bg-gray-900 rounded-xl border border-gray-800 px-4 py-3">
                    <div class="text-2xl font-bold text-white">{{ rssStore.feeds.length }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ t('rssFeeds.statsFeeds') }}</div>
                </div>
                <div class="bg-gray-900 rounded-xl border border-gray-800 px-4 py-3">
                    <div class="text-2xl font-bold text-white">{{ rssStore.activeFeedsCount }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ t('rssFeeds.statsActive') }}</div>
                </div>
                <div class="bg-gray-900 rounded-xl border border-gray-800 px-4 py-3 col-span-2 sm:col-span-1">
                    <div class="text-2xl font-bold text-white">{{ rssStore.totalArticlesCount }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ t('rssFeeds.statsArticles') }}</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-800 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button
                        @click="activeTab = 'feeds'"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'feeds'
                            ? 'border-indigo-500 text-indigo-400'
                            : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-600'
                        "
                    >
                        {{ t('rssFeeds.tabFeeds') }}
                        <span
                            v-if="rssStore.feeds.length"
                            class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="activeTab === 'feeds' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-gray-800 text-gray-400'"
                        >
                            {{ rssStore.feeds.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'articles'"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'articles'
                            ? 'border-indigo-500 text-indigo-400'
                            : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-600'
                        "
                    >
                        {{ t('rssFeeds.tabArticles') }}
                        <span
                            v-if="rssStore.totalArticlesCount"
                            class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="activeTab === 'articles' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-gray-800 text-gray-400'"
                        >
                            {{ rssStore.totalArticlesCount }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex justify-center py-20">
                <LoadingSpinner />
            </div>

            <!-- TAB: Feeds -->
            <template v-else-if="activeTab === 'feeds'">
                <!-- Empty state -->
                <div v-if="rssStore.feeds.length === 0" class="text-center py-20">
                    <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-orange-500/10 to-amber-500/10 flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">{{ t('rssFeeds.noFeeds') }}</h3>
                    <p class="mt-2 text-sm text-gray-400 max-w-sm mx-auto">{{ t('rssFeeds.noFeedsDescription') }}</p>
                    <div class="mt-6">
                        <Button @click="showAddModal = true">
                            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('rssFeeds.addFeed') }}
                        </Button>
                    </div>
                </div>

                <!-- Feeds grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="feed in rssStore.feeds"
                        :key="feed.id"
                        class="relative bg-gray-900 rounded-xl border border-gray-800 overflow-hidden hover:border-gray-700 transition-all duration-200 group"
                    >
                        <!-- Status color bar -->
                        <div
                            class="h-1"
                            :class="{
                                'bg-green-400': feed.status_color === 'green',
                                'bg-yellow-400': feed.status_color === 'yellow',
                                'bg-red-400': feed.status_color === 'red',
                            }"
                        />
                        <div class="p-5">
                            <!-- Top: name + status -->
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 bg-orange-500/10">
                                        <svg class="w-4.5 h-4.5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-white truncate text-sm">
                                            {{ feed.name }}
                                        </h3>
                                        <p class="text-xs text-gray-500 truncate" :title="feed.url">
                                            {{ truncateUrl(feed.url) }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0"
                                    :class="statusColors[feed.status_color] || 'bg-gray-800 text-gray-400'"
                                >
                                    {{ feed.status_label }}
                                </span>
                            </div>

                            <!-- Error message -->
                            <div v-if="feed.last_error" class="mt-2 rounded-md bg-red-500/10 border border-red-500/20 p-2">
                                <p class="text-xs text-red-400 line-clamp-2">{{ feed.last_error }}</p>
                            </div>

                            <!-- Stats row -->
                            <div class="mt-3 pt-3 border-t border-gray-800 flex items-center justify-between">
                                <button
                                    @click="showFeedArticles(feed)"
                                    class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-400 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <span class="font-medium">{{ feed.articles_count || 0 }}</span>
                                    {{ t('rssFeeds.articles') }}
                                </button>
                                <span v-if="feed.last_fetched_at" class="text-xs text-gray-500" :title="new Date(feed.last_fetched_at).toLocaleString()">
                                    {{ relativeTime(feed.last_fetched_at) }}
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="mt-3 pt-3 border-t border-gray-800 flex items-center gap-1">
                                <button
                                    @click="handleRefresh(feed)"
                                    :disabled="refreshingFeedId === feed.id"
                                    class="flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-gray-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-lg transition-colors disabled:opacity-50"
                                    :title="t('rssFeeds.refresh')"
                                >
                                    <svg
                                        class="w-3.5 h-3.5"
                                        :class="{ 'animate-spin': refreshingFeedId === feed.id }"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M21.015 4.356v4.992" />
                                    </svg>
                                    {{ t('rssFeeds.refresh') }}
                                </button>
                                <button
                                    @click="handleToggleStatus(feed)"
                                    class="flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-gray-400 hover:text-yellow-400 hover:bg-yellow-500/10 rounded-lg transition-colors"
                                >
                                    <svg v-if="feed.status === 'active'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    {{ feed.status === 'active' ? t('rssFeeds.pause') : t('rssFeeds.resume') }}
                                </button>
                                <button
                                    @click="handleDelete(feed)"
                                    class="ml-auto p-1.5 rounded-lg text-gray-600 hover:text-red-400 hover:bg-red-500/10 opacity-0 group-hover:opacity-100 transition-all"
                                    :title="t('rssFeeds.deleteFeed')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Add Feed Card -->
                    <button
                        @click="showAddModal = true"
                        class="rounded-xl border-2 border-dashed border-gray-800 hover:border-orange-500/50 hover:bg-orange-500/5 transition-all duration-200 min-h-[200px] flex flex-col items-center justify-center gap-2 text-gray-500 hover:text-orange-400 group"
                    >
                        <div class="w-10 h-10 rounded-xl bg-gray-800 group-hover:bg-orange-500/10 flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium">{{ t('rssFeeds.addFeed') }}</span>
                    </button>
                </div>
            </template>

            <!-- TAB: Articles -->
            <template v-else-if="activeTab === 'articles'">
                <!-- Period filter pills -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <button
                        v-for="period in periodOptions"
                        :key="period.value"
                        @click="selectedPeriod = period.value"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                        :class="selectedPeriod === period.value
                            ? 'bg-indigo-500/20 border-indigo-500/30 text-indigo-400'
                            : 'bg-gray-900 border-gray-700 text-gray-400 hover:bg-gray-800 hover:border-gray-600'
                        "
                    >
                        {{ period.label }}
                    </button>
                </div>

                <!-- Search + feed filter -->
                <div class="flex flex-col sm:flex-row gap-3 mb-6">
                    <div class="relative flex-1 max-w-md">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('rssFeeds.searchArticles')"
                            class="block w-full pl-10 rounded-lg bg-gray-900 border-gray-700 text-white placeholder-gray-500 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                    </div>
                    <select
                        v-model="selectedFeedId"
                        class="rounded-lg bg-gray-900 border-gray-700 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">{{ t('rssFeeds.allFeeds') }}</option>
                        <option v-for="feed in rssStore.feeds" :key="feed.id" :value="feed.id">
                            {{ feed.name }}
                        </option>
                    </select>
                </div>

                <!-- Results count -->
                <div v-if="!rssStore.articlesLoading && rssStore.articles.length > 0" class="mb-4">
                    <p class="text-xs text-gray-500">
                        {{ t('rssFeeds.showingArticles', { count: articlesTotal }) }}
                    </p>
                </div>

                <!-- Articles loading -->
                <div v-if="rssStore.articlesLoading" class="flex justify-center py-20">
                    <LoadingSpinner />
                </div>

                <!-- Empty articles -->
                <div v-else-if="rssStore.articles.length === 0" class="text-center py-20">
                    <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">{{ t('rssFeeds.noArticles') }}</h3>
                    <p class="mt-2 text-sm text-gray-400 max-w-sm mx-auto">
                        {{ selectedPeriod !== 'all' || searchDebounced || selectedFeedId
                            ? t('rssFeeds.noArticlesFiltered')
                            : t('rssFeeds.noArticlesDescription')
                        }}
                    </p>
                    <button
                        v-if="selectedPeriod !== 'all' || searchDebounced || selectedFeedId"
                        @click="selectedPeriod = 'all'; searchQuery = ''; selectedFeedId = ''"
                        class="mt-4 text-sm text-indigo-400 hover:text-indigo-300 font-medium"
                    >
                        {{ t('rssFeeds.clearFilters') }}
                    </button>
                </div>

                <!-- Articles list -->
                <div v-else class="space-y-3">
                    <a
                        v-for="article in rssStore.articles"
                        :key="article.id"
                        :href="article.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block bg-gray-900 rounded-xl border border-gray-800 overflow-hidden hover:border-gray-700 transition-all duration-200 group"
                    >
                        <div class="flex">
                            <!-- Image -->
                            <div v-if="article.image_url" class="hidden sm:block w-48 lg:w-56 flex-shrink-0">
                                <img
                                    :src="article.image_url"
                                    :alt="article.title"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                />
                            </div>

                            <div class="flex-1 min-w-0 p-4">
                                <!-- Source + time -->
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span v-if="article.feed_name" class="text-xs font-medium text-orange-400 bg-orange-500/10 px-1.5 py-0.5 rounded">
                                        {{ article.feed_name }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ relativeTime(article.published_at) }}
                                    </span>
                                    <span v-if="article.author" class="text-xs text-gray-500">
                                        · {{ article.author }}
                                    </span>
                                </div>

                                <!-- Title -->
                                <h3 class="text-sm font-semibold text-white group-hover:text-indigo-400 transition-colors line-clamp-2 mb-1">
                                    {{ article.title }}
                                    <svg class="inline w-3 h-3 ml-0.5 text-gray-600 group-hover:text-indigo-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </h3>

                                <!-- Description -->
                                <p v-if="article.description" class="text-xs text-gray-400 line-clamp-2 leading-relaxed">
                                    {{ article.description }}
                                </p>

                                <!-- Mobile image -->
                                <img
                                    v-if="article.image_url"
                                    :src="article.image_url"
                                    :alt="article.title"
                                    class="sm:hidden mt-2 w-full h-36 object-cover rounded-lg"
                                    loading="lazy"
                                />

                                <!-- Categories -->
                                <div v-if="article.categories && article.categories.length" class="flex flex-wrap gap-1.5 mt-2">
                                    <span
                                        v-for="cat in article.categories"
                                        :key="cat"
                                        class="inline-flex items-center rounded-full bg-gray-800 px-2 py-0.5 text-xs text-gray-400"
                                    >
                                        {{ cat }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 pt-6">
                        <button
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage <= 1"
                            class="px-3.5 py-2 text-sm font-medium text-gray-300 bg-gray-900 border border-gray-700 rounded-lg hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ t('rssFeeds.prevPage') }}
                        </button>

                        <!-- Page numbers -->
                        <div class="hidden sm:flex items-center gap-1">
                            <template v-for="page in totalPages" :key="page">
                                <button
                                    v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                                    @click="goToPage(page)"
                                    class="w-9 h-9 text-sm font-medium rounded-lg transition-colors"
                                    :class="page === currentPage
                                        ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30'
                                        : 'text-gray-400 hover:bg-gray-800'
                                    "
                                >
                                    {{ page }}
                                </button>
                                <span
                                    v-else-if="page === 2 && currentPage > 3 || page === totalPages - 1 && currentPage < totalPages - 2"
                                    class="w-9 h-9 flex items-center justify-center text-gray-600"
                                >
                                    ...
                                </span>
                            </template>
                        </div>

                        <!-- Mobile page indicator -->
                        <span class="sm:hidden text-sm text-gray-500">
                            {{ t('rssFeeds.pageOf', { current: currentPage, total: totalPages }) }}
                        </span>

                        <button
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage >= totalPages"
                            class="px-3.5 py-2 text-sm font-medium text-gray-300 bg-gray-900 border border-gray-700 rounded-lg hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ t('rssFeeds.nextPage') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Add Feed Modal -->
        <AddRssFeedModal
            :show="showAddModal"
            @close="showAddModal = false"
            @created="handleFeedCreated"
        />
    </div>
</template>
