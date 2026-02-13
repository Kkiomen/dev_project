<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useVideoManagerStore } from '@/stores/videoManager';
import VideoStatsGrid from '@/components/videoManager/VideoStatsGrid.vue';
import ProcessingQueue from '@/components/videoManager/ProcessingQueue.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const videoManagerStore = useVideoManagerStore();

const recentProjects = ref([]);
const recentLoading = ref(false);
let refreshInterval = null;

const loadData = async () => {
    const params = {};
    if (brandsStore.currentBrand?.id) {
        params.brand_id = brandsStore.currentBrand.id;
    }

    videoManagerStore.fetchStats(params);
    videoManagerStore.fetchHealth();

    recentLoading.value = true;
    try {
        await videoManagerStore.fetchProjects({ ...params, per_page: 5 });
        recentProjects.value = videoManagerStore.projects.slice(0, 5);
    } finally {
        recentLoading.value = false;
    }
};

const formatDuration = (seconds) => {
    if (!seconds) return '--';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const formatDate = (date) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    loadData();
    refreshInterval = setInterval(loadData, 15000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

watch(() => brandsStore.currentBrand?.id, () => {
    loadData();
});
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">{{ t('videoManager.dashboard.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('videoManager.dashboard.subtitle') }}</p>
            </div>
            <button
                @click="router.push({ name: 'videoManager.upload' })"
                class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                {{ t('videoManager.dashboard.uploadVideo') }}
            </button>
        </div>

        <!-- Stats Grid -->
        <VideoStatsGrid :stats="videoManagerStore.stats" :loading="videoManagerStore.statsLoading" />

        <!-- Service Health -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <h3 class="text-sm font-semibold text-white mb-3">{{ t('videoManager.dashboard.serviceHealth') }}</h3>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center gap-2">
                    <div
                        class="w-2.5 h-2.5 rounded-full"
                        :class="videoManagerStore.health.transcriber ? 'bg-green-500' : 'bg-red-500'"
                    />
                    <span class="text-sm text-gray-300">{{ t('videoManager.dashboard.transcriber') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="w-2.5 h-2.5 rounded-full"
                        :class="videoManagerStore.health.video_editor ? 'bg-green-500' : 'bg-red-500'"
                    />
                    <span class="text-sm text-gray-300">{{ t('videoManager.dashboard.videoEditor') }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Processing Queue -->
            <ProcessingQueue
                :projects="videoManagerStore.processingProjects"
                :loading="videoManagerStore.projectsLoading"
            />

            <!-- Recent Projects -->
            <div class="bg-gray-900 rounded-xl border border-gray-800">
                <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-white">{{ t('videoManager.dashboard.recentProjects') }}</h3>
                    <button
                        @click="router.push({ name: 'videoManager.library' })"
                        class="text-xs text-violet-400 hover:text-violet-300 transition-colors"
                    >
                        {{ t('videoManager.dashboard.viewAll') }}
                    </button>
                </div>
                <div class="divide-y divide-gray-800">
                    <template v-if="recentLoading">
                        <div v-for="i in 3" :key="i" class="px-5 py-3">
                            <div class="h-4 w-40 bg-gray-800 rounded animate-pulse" />
                            <div class="mt-1 h-3 w-24 bg-gray-800 rounded animate-pulse" />
                        </div>
                    </template>

                    <div v-else-if="recentProjects.length === 0" class="px-5 py-8 text-center">
                        <p class="text-sm text-gray-500">{{ t('videoManager.dashboard.noProjects') }}</p>
                    </div>

                    <div
                        v-else
                        v-for="project in recentProjects"
                        :key="project.id"
                        @click="router.push({ name: 'videoManager.editor', params: { projectId: project.id } })"
                        class="px-5 py-3 flex items-center justify-between hover:bg-gray-800/50 cursor-pointer transition-colors"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-white truncate">{{ project.title }}</p>
                            <p class="text-xs text-gray-500">{{ formatDate(project.created_at) }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-3">
                            <span class="text-xs text-gray-500">{{ formatDuration(project.duration) }}</span>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="{
                                    'bg-green-500/20 text-green-400': project.status === 'completed',
                                    'bg-purple-500/20 text-purple-400': project.status === 'transcribed',
                                    'bg-amber-500/20 text-amber-400': project.is_processing,
                                    'bg-red-500/20 text-red-400': project.status === 'failed',
                                    'bg-gray-500/20 text-gray-400': project.status === 'pending',
                                }"
                            >
                                {{ project.status_label }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
                @click="router.push({ name: 'videoManager.upload' })"
                class="flex items-center gap-4 p-5 bg-gray-900 rounded-xl border border-gray-800 hover:border-violet-600/50 transition-colors text-left group"
            >
                <div class="w-12 h-12 rounded-xl bg-violet-600/20 flex items-center justify-center shrink-0 group-hover:bg-violet-600/30 transition-colors">
                    <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">{{ t('videoManager.dashboard.uploadVideo') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ t('videoManager.dashboard.uploadDesc') }}</p>
                </div>
            </button>

            <button
                @click="router.push({ name: 'videoManager.library' })"
                class="flex items-center gap-4 p-5 bg-gray-900 rounded-xl border border-gray-800 hover:border-violet-600/50 transition-colors text-left group"
            >
                <div class="w-12 h-12 rounded-xl bg-violet-600/20 flex items-center justify-center shrink-0 group-hover:bg-violet-600/30 transition-colors">
                    <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">{{ t('videoManager.dashboard.browseLibrary') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ t('videoManager.dashboard.browseDesc') }}</p>
                </div>
            </button>
        </div>
    </div>
</template>
