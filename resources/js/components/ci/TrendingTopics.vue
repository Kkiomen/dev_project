<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    trends: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    hasCompetitors: { type: Boolean, default: false },
});

const emit = defineEmits(['scrape']);

const directionClass = (direction) => {
    const map = {
        breakout: 'bg-red-500/10 text-red-400',
        rising: 'bg-green-500/10 text-green-400',
        stable: 'bg-blue-500/10 text-blue-400',
        declining: 'bg-yellow-500/10 text-yellow-400',
    };
    return map[direction] || 'bg-gray-500/10 text-gray-400';
};

const directionIcon = (direction) => {
    if (direction === 'breakout' || direction === 'rising') return '↑';
    if (direction === 'declining') return '↓';
    return '→';
};

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
};
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold text-white mb-4">{{ t('ci.trends.title') }}</h2>

        <div v-if="loading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="trends.length === 0" class="rounded-xl bg-gray-900 border border-gray-800 p-8 text-center">
            <p class="text-sm text-gray-400">{{ t('ci.trends.noTrends') }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ t('ci.trends.noTrendsDescription') }}</p>
            <button
                v-if="hasCompetitors"
                @click="emit('scrape')"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                </svg>
                {{ t('ci.trends.collectTrends') }}
            </button>
            <p v-else class="text-xs text-orange-400 mt-3">{{ t('ci.trends.addCompetitorsFirst') }}</p>
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="trend in trends"
                :key="trend.id"
                class="rounded-xl bg-gray-900 border border-gray-800 p-3 flex items-center gap-3"
            >
                <div
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold shrink-0"
                    :class="directionClass(trend.trend_direction)"
                >
                    {{ directionIcon(trend.trend_direction) }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-white">#{{ trend.topic }}</span>
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                            :class="directionClass(trend.trend_direction)"
                        >
                            {{ t(`ci.trends.direction.${trend.trend_direction}`) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 mt-0.5 text-[11px] text-gray-500">
                        <span v-if="trend.platform">{{ t(`ci.platforms.${trend.platform}`) }}</span>
                        <span v-if="trend.volume">{{ t('ci.trends.volume') }}: {{ formatNumber(trend.volume) }}</span>
                        <span v-if="trend.growth_rate">{{ trend.growth_rate > 0 ? '+' : '' }}{{ trend.growth_rate }}%</span>
                    </div>
                </div>

                <div v-if="trend.related_hashtags?.length" class="hidden sm:flex flex-wrap gap-1 max-w-[200px]">
                    <span
                        v-for="tag in trend.related_hashtags.slice(0, 3)"
                        :key="tag"
                        class="text-[10px] text-gray-500 bg-gray-800 rounded px-1.5 py-0.5"
                    >
                        #{{ tag }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
