<script setup>
import { useI18n } from 'vue-i18n';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['filter']);

const { t } = useI18n();

const statItems = [
    { key: 'total', status: '', color: 'text-gray-900', bg: 'bg-gray-50' },
    { key: 'draft', status: 'draft', color: 'text-gray-600', bg: 'bg-gray-50' },
    { key: 'pending', status: 'pending_approval', color: 'text-yellow-600', bg: 'bg-yellow-50' },
    { key: 'approved', status: 'approved', color: 'text-green-600', bg: 'bg-green-50' },
    { key: 'scheduled', status: 'scheduled', color: 'text-blue-600', bg: 'bg-blue-50' },
    { key: 'published', status: 'published', color: 'text-purple-600', bg: 'bg-purple-50' },
    { key: 'failed', status: 'failed', color: 'text-red-600', bg: 'bg-red-50' },
];

function getCount(key) {
    if (key === 'total') return props.stats.total ?? 0;
    if (key === 'pending') return props.stats.pending_approval ?? props.stats.pending ?? 0;
    return props.stats[key] ?? 0;
}

function handleClick(status) {
    emit('filter', status);
}
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2">
            <button
                v-for="item in statItems"
                :key="item.key"
                @click="handleClick(item.status)"
                class="flex flex-col items-center gap-1 rounded-lg px-3 py-2.5 transition-colors hover:bg-gray-100 cursor-pointer"
                :class="item.bg"
            >
                <span class="text-xl font-bold leading-none" :class="item.color">
                    <template v-if="loading">-</template>
                    <template v-else>{{ getCount(item.key) }}</template>
                </span>
                <span class="text-xs text-gray-500 leading-none">
                    {{ t(`postAutomation.stats.${item.key}`) }}
                </span>
            </button>
        </div>
        <!-- Queue coverage bar -->
        <div v-if="stats.coverage_days != null" class="mt-3 pt-3 border-t border-gray-100">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                <span>{{ t('postAutomation.stats.queueCoverage') }}</span>
                <span class="font-medium text-gray-700">{{ stats.coverage_days }} {{ t('postAutomation.stats.days') }}</span>
            </div>
            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-full bg-blue-500 rounded-full transition-all duration-500"
                    :style="{ width: Math.min((stats.coverage_days / (stats.target_days || 14)) * 100, 100) + '%' }"
                />
            </div>
        </div>
    </div>
</template>
