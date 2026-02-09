<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { useRssFeedsStore } from '@/stores/rssFeeds';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const rssStore = useRssFeedsStore();
const loading = ref(true);

const todayDateFormatted = computed(() => {
    return new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const todayStart = computed(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString();
});

const heroArticle = computed(() => {
    return rssStore.articles.find(a => a.image_url) || rssStore.articles[0] || null;
});

const gridArticles = computed(() => {
    if (!heroArticle.value) return [];
    return rssStore.articles.filter(a => a.id !== heroArticle.value.id);
});

const articleCount = computed(() => rssStore.articles.length);

const relativeTime = (dateStr) => {
    if (!dateStr) return '';
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

const estimateReadTime = (description) => {
    if (!description) return null;
    const wordCount = description.split(/\s+/).length;
    const minutes = Math.max(1, Math.ceil(wordCount / 200));
    return minutes;
};

const fetchTodayArticles = async () => {
    loading.value = true;
    try {
        await rssStore.fetchArticles({
            since: todayStart.value,
            per_page: 50,
        });
    } finally {
        loading.value = false;
    }
};

onMounted(fetchTodayArticles);
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <RouterLink
                        to="/rss-feeds"
                        class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        {{ t('rssFeeds.backToFeeds') }}
                    </RouterLink>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">
                            {{ t('rssFeeds.todayTitle') }}
                        </h1>
                        <p class="text-gray-400 mt-1 text-sm">{{ todayDateFormatted }}</p>
                    </div>
                    <div v-if="!loading && articleCount > 0" class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                            {{ t('rssFeeds.todayArticleCount', { count: articleCount }) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex justify-center py-24">
                <LoadingSpinner />
            </div>

            <!-- Empty State -->
            <div v-else-if="articleCount === 0" class="text-center py-24">
                <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-orange-50 to-amber-100 flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">{{ t('rssFeeds.todayEmpty') }}</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                    {{ t('rssFeeds.todayEmptyDescription') }}
                </p>
                <RouterLink
                    to="/rss-feeds"
                    class="inline-flex items-center gap-1.5 mt-6 text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    {{ t('rssFeeds.backToFeeds') }}
                </RouterLink>
            </div>

            <!-- Content -->
            <template v-else>
                <!-- Hero Article -->
                <a
                    v-if="heroArticle"
                    :href="heroArticle.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block mb-8 group"
                >
                    <div class="relative rounded-2xl overflow-hidden bg-gray-900">
                        <!-- Hero image -->
                        <div v-if="heroArticle.image_url" class="aspect-[16/9] sm:aspect-[21/9]">
                            <img
                                :src="heroArticle.image_url"
                                :alt="heroArticle.title"
                                class="w-full h-full object-cover opacity-80 group-hover:opacity-70 group-hover:scale-105 transition-all duration-500"
                            />
                        </div>
                        <div v-else class="aspect-[16/9] sm:aspect-[21/9] bg-gradient-to-br from-blue-600 to-indigo-800" />

                        <!-- Gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent" />

                        <!-- Content overlay -->
                        <div class="absolute bottom-0 left-0 right-0 p-5 sm:p-8">
                            <div class="flex items-center gap-2 mb-3">
                                <span v-if="heroArticle.feed_name" class="inline-flex items-center rounded-full bg-orange-500 px-2.5 py-0.5 text-xs font-semibold text-white">
                                    {{ heroArticle.feed_name }}
                                </span>
                                <span class="text-xs text-gray-300">
                                    {{ relativeTime(heroArticle.published_at) }}
                                </span>
                                <span v-if="estimateReadTime(heroArticle.description)" class="text-xs text-gray-400">
                                    &middot; {{ t('rssFeeds.minuteRead', { n: estimateReadTime(heroArticle.description) }) }}
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white leading-tight line-clamp-3 group-hover:text-blue-200 transition-colors">
                                {{ heroArticle.title }}
                            </h2>
                            <p v-if="heroArticle.description" class="mt-2 text-sm sm:text-base text-gray-300 line-clamp-2 max-w-3xl">
                                {{ heroArticle.description }}
                            </p>
                            <div v-if="heroArticle.categories && heroArticle.categories.length" class="flex flex-wrap gap-1.5 mt-3">
                                <span
                                    v-for="cat in heroArticle.categories.slice(0, 3)"
                                    :key="cat"
                                    class="inline-flex items-center rounded-full bg-white/15 backdrop-blur-sm px-2 py-0.5 text-xs text-gray-200"
                                >
                                    {{ cat }}
                                </span>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Grid Articles -->
                <div v-if="gridArticles.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <a
                        v-for="article in gridArticles"
                        :key="article.id"
                        :href="article.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-gray-300 transition-all duration-200 flex flex-col"
                    >
                        <!-- Card image -->
                        <div v-if="article.image_url" class="aspect-[16/10] overflow-hidden">
                            <img
                                :src="article.image_url"
                                :alt="article.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                        </div>

                        <!-- Card body -->
                        <div class="flex-1 p-4 flex flex-col">
                            <!-- Source + time -->
                            <div class="flex items-center gap-2 mb-2">
                                <span v-if="article.feed_name" class="text-xs font-medium text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded">
                                    {{ article.feed_name }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    {{ relativeTime(article.published_at) }}
                                </span>
                            </div>

                            <!-- Title -->
                            <h3 class="text-sm font-semibold text-gray-900 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1.5">
                                {{ article.title }}
                            </h3>

                            <!-- Description -->
                            <p v-if="article.description" class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-3">
                                {{ article.description }}
                            </p>

                            <!-- Footer -->
                            <div class="mt-auto flex items-center justify-between pt-2 border-t border-gray-100">
                                <div v-if="article.categories && article.categories.length" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="cat in article.categories.slice(0, 2)"
                                        :key="cat"
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500"
                                    >
                                        {{ cat }}
                                    </span>
                                </div>
                                <span v-if="estimateReadTime(article.description)" class="text-xs text-gray-400 ml-auto">
                                    {{ t('rssFeeds.minuteRead', { n: estimateReadTime(article.description) }) }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </template>
        </div>
    </div>
</template>
