<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    benchmarks: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    hasCompetitors: { type: Boolean, default: false },
});

const emit = defineEmits(['scrape']);

const hasData = computed(() => props.benchmarks?.has_data);

const formatNumber = (num) => {
    if (!num) return '0';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return Math.round(num).toString();
};

const platforms = computed(() => {
    if (!props.benchmarks?.by_platform) return [];
    return Object.entries(props.benchmarks.by_platform).map(([name, data]) => ({
        name,
        ...data,
    }));
});

const platformBarWidth = (platform) => {
    const max = Math.max(...platforms.value.map(p => p.avg_engagement_rate || 0));
    return max > 0 ? Math.round(((platform.avg_engagement_rate || 0) / max) * 100) : 0;
};
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold text-white mb-4">{{ t('ci.benchmarks.title') }}</h2>

        <div v-if="loading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="!hasData" class="rounded-xl bg-gray-900 border border-gray-800 p-8 text-center">
            <p class="text-sm text-gray-400">{{ t('ci.benchmarks.noBenchmarks') }}</p>
            <button
                v-if="hasCompetitors"
                @click="emit('scrape')"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
                {{ t('ci.benchmarks.collectData') }}
            </button>
            <p v-else class="text-xs text-orange-400 mt-3">{{ t('ci.benchmarks.addCompetitorsFirst') }}</p>
        </div>

        <template v-else>
            <!-- Key metrics -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.benchmarks.engagementRate') }}</p>
                    <p class="text-xl font-bold text-white">{{ (benchmarks.avg_engagement_rate || 0).toFixed(2) }}%</p>
                </div>
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.benchmarks.avgLikes') }}</p>
                    <p class="text-xl font-bold text-white">{{ formatNumber(benchmarks.avg_likes) }}</p>
                </div>
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.benchmarks.avgComments') }}</p>
                    <p class="text-xl font-bold text-white">{{ formatNumber(benchmarks.avg_comments) }}</p>
                </div>
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-3">
                    <p class="text-[11px] text-gray-500 mb-1">{{ t('ci.benchmarks.avgViews') }}</p>
                    <p class="text-xl font-bold text-white">{{ formatNumber(benchmarks.avg_views) }}</p>
                </div>
            </div>

            <!-- Platform breakdown -->
            <div v-if="platforms.length" class="rounded-xl bg-gray-900 border border-gray-800 p-4">
                <h3 class="text-sm font-medium text-gray-300 mb-3">{{ t('ci.benchmarks.byPlatform') }}</h3>
                <div class="space-y-3">
                    <div v-for="platform in platforms" :key="platform.name">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-gray-400 capitalize">{{ platform.name }}</span>
                            <span class="text-xs font-medium text-white">{{ (platform.avg_engagement_rate || 0).toFixed(2) }}%</span>
                        </div>
                        <div class="w-full bg-gray-800 rounded-full h-1.5">
                            <div
                                class="bg-orange-500 h-1.5 rounded-full transition-all duration-500"
                                :style="{ width: platformBarWidth(platform) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
