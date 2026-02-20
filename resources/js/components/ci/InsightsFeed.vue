<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    insights: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    hasCompetitors: { type: Boolean, default: false },
});

const emit = defineEmits(['action', 'scrape']);

const priorityClass = (priority) => {
    if (priority >= 7) return 'bg-red-500/10 text-red-400 border-red-500/20';
    if (priority >= 4) return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
    return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
};

const priorityLabel = (priority) => {
    if (priority >= 7) return t('ci.insights.highPriority');
    if (priority >= 4) return t('ci.insights.mediumPriority');
    return t('ci.insights.lowPriority');
};

const typeIcon = (type) => {
    const icons = {
        content_gap: 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Z',
        timing_optimization: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        hashtag_strategy: 'M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5',
        format_recommendation: 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Z',
        trend_alert: 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
    };
    return icons[type] || icons.content_gap;
};
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold text-white mb-4">{{ t('ci.insights.title') }}</h2>

        <div v-if="loading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="insights.length === 0" class="rounded-xl bg-gray-900 border border-gray-800 p-8 text-center">
            <p class="text-sm text-gray-400">{{ t('ci.insights.noInsights') }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ t('ci.insights.noInsightsDescription') }}</p>
            <button
                v-if="hasCompetitors"
                @click="emit('scrape')"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                {{ t('ci.insights.collectData') }}
            </button>
            <p v-else class="text-xs text-orange-400 mt-3">{{ t('ci.insights.addCompetitorsFirst') }}</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="insight in insights"
                :key="insight.public_id || insight.id"
                class="rounded-xl bg-gray-900 border border-gray-800 p-4"
            >
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="typeIcon(insight.insight_type)" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-sm font-semibold text-white truncate">{{ insight.title }}</h3>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium border shrink-0"
                                :class="priorityClass(insight.priority)"
                            >
                                {{ priorityLabel(insight.priority) }}
                            </span>
                        </div>

                        <p class="text-xs text-gray-400 mb-2">{{ insight.description }}</p>

                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-500 border border-gray-700 rounded-full px-2 py-0.5">
                                {{ t(`ci.insights.types.${insight.insight_type}`) }}
                            </span>
                            <span v-if="insight.platform" class="text-[10px] text-gray-500 border border-gray-700 rounded-full px-2 py-0.5">
                                {{ t(`ci.platforms.${insight.platform}`) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!insight.is_actioned" class="flex gap-1.5 shrink-0">
                        <button
                            @click="emit('action', insight, 'applied')"
                            class="rounded-lg bg-green-500/10 border border-green-500/20 px-2.5 py-1 text-xs text-green-400 hover:bg-green-500/20 transition-colors"
                        >
                            {{ t('ci.insights.apply') }}
                        </button>
                        <button
                            @click="emit('action', insight, 'dismissed')"
                            class="rounded-lg bg-gray-800 border border-gray-700 px-2.5 py-1 text-xs text-gray-400 hover:bg-gray-700 transition-colors"
                        >
                            {{ t('ci.insights.dismiss') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
