<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const props = defineProps({
    brandId: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const toast = useToast();

const loading = ref(true);
const selectedDays = ref(30);
const data = ref(null);

const periodOptions = [7, 30, 90];

const fetchStats = async () => {
    loading.value = true;
    try {
        const response = await window.axios.get(`/api/v1/brands/${props.brandId}/ai-usage-stats`, {
            params: { days: selectedDays.value },
        });
        data.value = response.data;
    } catch (error) {
        console.error('Failed to fetch AI usage stats:', error);
        toast.error(t('common.error'));
    } finally {
        loading.value = false;
    }
};

const selectPeriod = (days) => {
    selectedDays.value = days;
    fetchStats();
};

onMounted(fetchStats);

const openaiStats = computed(() => data.value?.providers?.openai || null);
const wavespeedStats = computed(() => data.value?.providers?.wavespeed || null);
const apifyStats = computed(() => data.value?.apify || null);

const totalCost = computed(() => {
    if (!data.value) return 0;
    let cost = 0;
    if (data.value.providers) {
        Object.values(data.value.providers).forEach((p) => {
            cost += parseFloat(p.total_cost) || 0;
        });
    }
    if (data.value.apify) {
        cost += parseFloat(data.value.apify.total_cost) || 0;
    }
    return cost;
});

const chartData = computed(() => {
    if (!data.value?.daily?.length) return [];
    return data.value.daily;
});

const maxDailyCost = computed(() => {
    if (!chartData.value.length) return 1;
    const max = Math.max(...chartData.value.map((d) => parseFloat(d.total_cost) || 0));
    return max || 1;
});

const operations = computed(() => {
    if (!data.value?.operations?.length) return [];
    return data.value.operations;
});

const apifyBreakdown = computed(() => {
    if (!apifyStats.value?.cost_breakdown) return [];
    return Object.entries(apifyStats.value.cost_breakdown).map(([actor, cost]) => ({
        actor,
        cost: parseFloat(cost) || 0,
    }));
});

const apifyBudgetPercent = computed(() => {
    if (!apifyStats.value || !apifyStats.value.budget_limit) return 0;
    return Math.min(100, ((parseFloat(apifyStats.value.total_cost) || 0) / (parseFloat(apifyStats.value.budget_limit) || 1)) * 100);
});

const formatCost = (cost) => {
    return `$${(parseFloat(cost) || 0).toFixed(4)}`;
};

const formatCostShort = (cost) => {
    return `$${(parseFloat(cost) || 0).toFixed(2)}`;
};

const formatNumber = (num) => {
    return (num || 0).toLocaleString();
};

const formatDuration = (ms) => {
    if (!ms) return '0ms';
    if (ms < 1000) return `${Math.round(ms)}ms`;
    return `${(ms / 1000).toFixed(1)}s`;
};

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return `${date.getDate()}.${(date.getMonth() + 1).toString().padStart(2, '0')}`;
};
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 sm:mb-6">
            <div>
                <h2 class="text-base sm:text-lg font-medium text-gray-900">
                    {{ t('aiUsage.title') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ t('aiUsage.description') }}
                </p>
            </div>

            <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                <button
                    v-for="days in periodOptions"
                    :key="days"
                    @click="selectPeriod(days)"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                    :class="[
                        selectedDays === days
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-600 hover:text-gray-900'
                    ]"
                >
                    {{ t(`aiUsage.period.${days}days`) }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16">
            <LoadingSpinner size="lg" />
        </div>

        <div v-else-if="!data" class="text-center py-16">
            <p class="text-sm text-gray-500">{{ t('aiUsage.noData') }}</p>
        </div>

        <div v-else class="space-y-4 sm:space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <!-- Total Cost -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-blue-700 uppercase tracking-wide">
                            {{ t('aiUsage.totalCost') }}
                        </span>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-blue-900">{{ formatCostShort(totalCost) }}</p>
                </div>

                <!-- OpenAI -->
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">OpenAI</span>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">
                        {{ formatCostShort(openaiStats?.total_cost) }}
                    </p>
                    <div class="mt-1 flex items-center gap-3 text-xs text-gray-500">
                        <span>{{ formatNumber(openaiStats?.total_tokens) }} {{ t('aiUsage.tokens') }}</span>
                        <span>{{ formatNumber(openaiStats?.total_requests) }} {{ t('aiUsage.requests') }}</span>
                    </div>
                </div>

                <!-- WaveSpeed -->
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">WaveSpeed</span>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">
                        {{ formatNumber(wavespeedStats?.total_requests) }}
                    </p>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ t('aiUsage.avgDuration') }}: {{ formatDuration(wavespeedStats?.avg_duration_ms) }}
                    </div>
                </div>

                <!-- Apify -->
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-orange-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Apify</span>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">
                        {{ formatCostShort(apifyStats?.total_cost) }}
                    </p>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ t('aiUsage.apify.budget') }}: {{ formatCostShort(apifyStats?.remaining) }} {{ t('aiUsage.apify.remaining') }}
                    </div>
                </div>
            </div>

            <!-- Daily Cost Chart -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('aiUsage.dailyChart.title') }}</h3>

                <div v-if="chartData.length === 0" class="text-center py-8 text-sm text-gray-500">
                    {{ t('aiUsage.noData') }}
                </div>

                <div v-else class="space-y-2">
                    <!-- Chart bars -->
                    <div class="flex items-end gap-px sm:gap-0.5 h-32 sm:h-40">
                        <div
                            v-for="day in chartData"
                            :key="day.date"
                            class="flex-1 flex flex-col items-center justify-end group relative"
                        >
                            <div
                                class="w-full bg-blue-500 hover:bg-blue-600 rounded-t transition-colors cursor-default min-h-[2px]"
                                :style="{ height: `${Math.max(2, ((parseFloat(day.total_cost) || 0) / maxDailyCost) * 100)}%` }"
                            />
                            <!-- Tooltip -->
                            <div class="absolute bottom-full mb-2 hidden group-hover:block z-10">
                                <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
                                    <div class="font-medium">{{ formatDate(day.date) }}</div>
                                    <div>{{ t('aiUsage.totalCost') }}: {{ formatCost(day.total_cost) }}</div>
                                    <div>{{ t('aiUsage.requests') }}: {{ formatNumber(day.request_count) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- X-axis labels (show every few days on mobile) -->
                    <div class="flex gap-px sm:gap-0.5 text-xs text-gray-400">
                        <div
                            v-for="(day, index) in chartData"
                            :key="'label-' + day.date"
                            class="flex-1 text-center truncate"
                        >
                            <span v-if="chartData.length <= 10 || index % Math.ceil(chartData.length / 10) === 0">
                                {{ formatDate(day.date) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operations Breakdown -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('aiUsage.operations.title') }}</h3>

                <div v-if="operations.length === 0" class="text-center py-8 text-sm text-gray-500">
                    {{ t('aiUsage.noData') }}
                </div>

                <div v-else class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-2 px-4 sm:px-3 font-medium text-gray-500 text-xs uppercase tracking-wide">
                                    {{ t('aiUsage.operations.name') }}
                                </th>
                                <th class="text-right py-2 px-4 sm:px-3 font-medium text-gray-500 text-xs uppercase tracking-wide">
                                    {{ t('aiUsage.operations.requests') }}
                                </th>
                                <th class="text-right py-2 px-4 sm:px-3 font-medium text-gray-500 text-xs uppercase tracking-wide hidden sm:table-cell">
                                    {{ t('aiUsage.operations.tokens') }}
                                </th>
                                <th class="text-right py-2 px-4 sm:px-3 font-medium text-gray-500 text-xs uppercase tracking-wide">
                                    {{ t('aiUsage.operations.cost') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="op in operations"
                                :key="op.operation"
                                class="border-b border-gray-50 hover:bg-gray-50"
                            >
                                <td class="py-2.5 px-4 sm:px-3 text-gray-900 font-medium">{{ op.operation }}</td>
                                <td class="py-2.5 px-4 sm:px-3 text-right text-gray-600">{{ formatNumber(op.request_count) }}</td>
                                <td class="py-2.5 px-4 sm:px-3 text-right text-gray-600 hidden sm:table-cell">{{ formatNumber(op.total_tokens) }}</td>
                                <td class="py-2.5 px-4 sm:px-3 text-right text-gray-900 font-medium">{{ formatCost(op.total_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Apify Details -->
            <div v-if="apifyBreakdown.length > 0" class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('aiUsage.apify.title') }}</h3>

                <!-- Budget bar -->
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                        <span>{{ formatCostShort(apifyStats?.total_cost) }} / {{ formatCostShort(apifyStats?.budget_limit) }}</span>
                        <span>{{ apifyBudgetPercent.toFixed(0) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div
                            class="h-2 rounded-full transition-all"
                            :class="[
                                apifyBudgetPercent > 80 ? 'bg-red-500' :
                                apifyBudgetPercent > 50 ? 'bg-yellow-500' : 'bg-green-500'
                            ]"
                            :style="{ width: `${apifyBudgetPercent}%` }"
                        />
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-400 mt-1">
                        <span>{{ t('aiUsage.apify.runs') }}: {{ formatNumber(apifyStats?.total_runs) }}</span>
                        <span>{{ t('aiUsage.apify.results') }}: {{ formatNumber(apifyStats?.total_results) }}</span>
                    </div>
                </div>

                <!-- Actor breakdown -->
                <div class="space-y-2">
                    <div
                        v-for="item in apifyBreakdown"
                        :key="item.actor"
                        class="flex items-center justify-between py-1.5 text-sm"
                    >
                        <span class="text-gray-600 truncate mr-3">{{ item.actor }}</span>
                        <span class="text-gray-900 font-medium whitespace-nowrap">{{ formatCost(item.cost) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
