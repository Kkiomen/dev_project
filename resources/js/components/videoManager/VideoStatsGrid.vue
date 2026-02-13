<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    stats: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const formatDuration = (seconds) => {
    if (!seconds) return '0s';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
};
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Projects -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">{{ t('videoManager.dashboard.totalProjects') }}</p>
                    <p class="mt-1 text-2xl font-bold text-white">
                        <span v-if="loading" class="inline-block w-12 h-7 bg-gray-800 rounded animate-pulse" />
                        <span v-else>{{ stats?.total ?? 0 }}</span>
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-violet-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Processing Now -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">{{ t('videoManager.dashboard.processing') }}</p>
                    <p class="mt-1 text-2xl font-bold text-white">
                        <span v-if="loading" class="inline-block w-12 h-7 bg-gray-800 rounded animate-pulse" />
                        <span v-else>{{ stats?.processing_count ?? 0 }}</span>
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400 animate-spin" fill="none" viewBox="0 0 24 24" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" />
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Completed Today -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">{{ t('videoManager.dashboard.completedToday') }}</p>
                    <p class="mt-1 text-2xl font-bold text-white">
                        <span v-if="loading" class="inline-block w-12 h-7 bg-gray-800 rounded animate-pulse" />
                        <span v-else>{{ stats?.completed_today ?? 0 }}</span>
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Duration -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">{{ t('videoManager.dashboard.totalDuration') }}</p>
                    <p class="mt-1 text-2xl font-bold text-white">
                        <span v-if="loading" class="inline-block w-12 h-7 bg-gray-800 rounded animate-pulse" />
                        <span v-else>{{ formatDuration(stats?.total_duration) }}</span>
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-600/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</template>
