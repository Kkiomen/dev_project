<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    costData: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const current = computed(() => props.costData?.current);
const history = computed(() => props.costData?.history || []);

const usagePercent = computed(() => {
    if (!current.value) return 0;
    const { total_cost, budget_limit } = current.value;
    return budget_limit > 0 ? Math.min(100, Math.round((total_cost / budget_limit) * 100)) : 0;
});

const usageColorClass = computed(() => {
    if (usagePercent.value >= 90) return 'bg-red-500';
    if (usagePercent.value >= 70) return 'bg-yellow-500';
    return 'bg-green-500';
});

const formatCost = (cost) => `$${(cost || 0).toFixed(2)}`;
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold text-white mb-4">{{ t('ci.cost.title') }}</h2>

        <div v-if="loading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else-if="current">
            <!-- Current month -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 mb-4">
                <h3 class="text-sm font-medium text-gray-300 mb-3">{{ t('ci.cost.currentMonth') }}</h3>

                <div class="flex items-end gap-2 mb-2">
                    <span class="text-2xl font-bold text-white">{{ formatCost(current.total_cost) }}</span>
                    <span class="text-sm text-gray-500 mb-0.5">/ {{ formatCost(current.budget_limit) }}</span>
                </div>

                <div class="w-full bg-gray-800 rounded-full h-2 mb-3">
                    <div
                        class="h-2 rounded-full transition-all duration-500"
                        :class="usageColorClass"
                        :style="{ width: usagePercent + '%' }"
                    ></div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-lg font-semibold text-white">{{ current.total_runs || 0 }}</p>
                        <p class="text-[11px] text-gray-500">{{ t('ci.cost.totalRuns') }}</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">{{ current.total_results || 0 }}</p>
                        <p class="text-[11px] text-gray-500">{{ t('ci.cost.totalResults') }}</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-white">{{ formatCost(current.budget_limit - current.total_cost) }}</p>
                        <p class="text-[11px] text-gray-500">{{ t('ci.cost.remaining') }}</p>
                    </div>
                </div>
            </div>

            <!-- Cost breakdown -->
            <div v-if="current.cost_breakdown && Object.keys(current.cost_breakdown).length" class="rounded-xl bg-gray-900 border border-gray-800 p-4 mb-4">
                <h3 class="text-sm font-medium text-gray-300 mb-3">{{ t('ci.cost.breakdown') }}</h3>
                <div class="space-y-2">
                    <div
                        v-for="(cost, actorType) in current.cost_breakdown"
                        :key="actorType"
                        class="flex items-center justify-between"
                    >
                        <span class="text-xs text-gray-400">{{ actorType.replace(/_/g, ' ') }}</span>
                        <span class="text-xs font-medium text-white">{{ formatCost(cost) }}</span>
                    </div>
                </div>
            </div>

            <!-- History -->
            <div v-if="history.length > 1" class="rounded-xl bg-gray-900 border border-gray-800 p-4">
                <h3 class="text-sm font-medium text-gray-300 mb-3">{{ t('ci.cost.history') }}</h3>
                <div class="space-y-2">
                    <div
                        v-for="entry in history"
                        :key="entry.period"
                        class="flex items-center justify-between"
                    >
                        <span class="text-xs text-gray-400">{{ entry.period }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500">{{ entry.total_runs }} {{ t('ci.cost.totalRuns').toLowerCase() }}</span>
                            <span class="text-xs font-medium text-white">{{ formatCost(entry.total_cost) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
