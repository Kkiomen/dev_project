<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useVideoManagerStore } from '@/stores/videoManager';
import { useToast } from '@/composables/useToast';
import VideoProjectCard from '@/components/videoManager/VideoProjectCard.vue';
import VideoProjectRow from '@/components/videoManager/VideoProjectRow.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const videoManagerStore = useVideoManagerStore();
const toast = useToast();

const search = ref('');
const statusFilter = ref('all');
const sortBy = ref('newest');
const viewMode = ref('grid');
const page = ref(1);

const statusTabs = computed(() => [
    { key: 'all', label: t('videoManager.library.all') },
    { key: 'processing', label: t('videoManager.library.processing') },
    { key: 'transcribed', label: t('videoManager.library.transcribed') },
    { key: 'completed', label: t('videoManager.library.completed') },
    { key: 'failed', label: t('videoManager.library.failed') },
]);

const sortOptions = [
    { value: 'newest', label: 'videoManager.library.newest' },
    { value: 'oldest', label: 'videoManager.library.oldest' },
    { value: 'title', label: 'videoManager.library.sortTitle' },
];

const loadProjects = async () => {
    const params = { page: page.value, per_page: 20 };

    if (brandsStore.currentBrand?.id) {
        params.brand_id = brandsStore.currentBrand.id;
    }

    if (statusFilter.value !== 'all') {
        if (statusFilter.value === 'processing') {
            params.status = 'transcribing';
        } else {
            params.status = statusFilter.value;
        }
    }

    if (search.value) {
        params.search = search.value;
    }

    if (sortBy.value === 'oldest') {
        params.sort = 'oldest';
    }

    await videoManagerStore.fetchProjects(params);
};

const handleDelete = async (projectId) => {
    try {
        await videoManagerStore.deleteProject(projectId);
        toast.success(t('videoManager.library.deleted'));
    } catch {
        toast.error(t('videoManager.library.deleteFailed'));
    }
};

const handleRender = async (projectId) => {
    try {
        await videoManagerStore.renderProject(projectId);
        toast.success(t('videoManager.library.renderStarted'));
    } catch {
        toast.error(t('videoManager.library.renderFailed'));
    }
};

const handleDownload = (projectId) => {
    window.open(videoManagerStore.getDownloadUrl(projectId), '_blank');
};

const handleBulkDelete = async () => {
    if (videoManagerStore.selectedIds.length === 0) return;
    try {
        const result = await videoManagerStore.bulkDelete(videoManagerStore.selectedIds);
        toast.success(t('videoManager.library.bulkDeleted', { count: result.deleted }));
        loadProjects();
    } catch {
        toast.error(t('videoManager.library.bulkDeleteFailed'));
    }
};

const handleBulkRender = async () => {
    if (videoManagerStore.selectedIds.length === 0) return;
    try {
        const result = await videoManagerStore.bulkRender(videoManagerStore.selectedIds);
        toast.success(t('videoManager.library.bulkRenderStarted', { count: result.dispatched }));
    } catch {
        toast.error(t('videoManager.library.bulkRenderFailed'));
    }
};

onMounted(loadProjects);

watch([statusFilter, sortBy, page], loadProjects);
watch(() => brandsStore.currentBrand?.id, loadProjects);

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        page.value = 1;
        loadProjects();
    }, 300);
});
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-white">{{ t('videoManager.library.title') }}</h1>
            <button
                @click="router.push({ name: 'videoManager.upload' })"
                class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ t('videoManager.library.uploadNew') }}
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3">
            <!-- Search -->
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('videoManager.library.search')"
                    class="w-full pl-10 pr-4 py-2 bg-gray-900 border border-gray-800 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-violet-500"
                />
            </div>

            <!-- Sort -->
            <select
                v-model="sortBy"
                class="px-3 py-2 bg-gray-900 border border-gray-800 rounded-lg text-sm text-white focus:outline-none focus:border-violet-500"
            >
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ t(opt.label) }}</option>
            </select>

            <!-- View toggle -->
            <div class="flex rounded-lg border border-gray-800 overflow-hidden">
                <button
                    @click="viewMode = 'grid'"
                    class="px-3 py-2 text-sm transition-colors"
                    :class="viewMode === 'grid' ? 'bg-violet-600 text-white' : 'bg-gray-900 text-gray-400 hover:text-white'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                    </svg>
                </button>
                <button
                    @click="viewMode = 'list'"
                    class="px-3 py-2 text-sm transition-colors"
                    :class="viewMode === 'list' ? 'bg-violet-600 text-white' : 'bg-gray-900 text-gray-400 hover:text-white'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-1 overflow-x-auto">
            <button
                v-for="tab in statusTabs"
                :key="tab.key"
                @click="statusFilter = tab.key; page = 1"
                class="px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors"
                :class="statusFilter === tab.key
                    ? 'bg-violet-600/20 text-violet-400'
                    : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Bulk actions bar -->
        <div
            v-if="videoManagerStore.selectedIds.length > 0"
            class="flex items-center gap-3 p-3 bg-violet-600/10 border border-violet-600/30 rounded-lg"
        >
            <span class="text-sm text-violet-300">
                {{ t('videoManager.library.selected', { count: videoManagerStore.selectedIds.length }) }}
            </span>
            <div class="flex items-center gap-2 ml-auto">
                <button
                    @click="handleBulkRender"
                    class="px-3 py-1.5 text-xs font-medium bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors"
                >
                    {{ t('videoManager.library.bulkRender') }}
                </button>
                <button
                    @click="handleBulkDelete"
                    class="px-3 py-1.5 text-xs font-medium bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
                >
                    {{ t('videoManager.library.bulkDelete') }}
                </button>
                <button
                    @click="videoManagerStore.clearSelection()"
                    class="px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-white transition-colors"
                >
                    {{ t('videoManager.library.clearSelection') }}
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="videoManagerStore.projectsLoading" class="flex items-center justify-center py-16">
            <svg class="w-8 h-8 text-violet-500 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
            </svg>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="videoManagerStore.projects.length === 0"
            class="text-center py-16"
        >
            <svg class="w-16 h-16 mx-auto text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-white">{{ t('videoManager.library.emptyTitle') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ t('videoManager.library.emptyDesc') }}</p>
            <button
                @click="router.push({ name: 'videoManager.upload' })"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
                {{ t('videoManager.library.uploadFirst') }}
            </button>
        </div>

        <!-- Grid view -->
        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <VideoProjectCard
                v-for="project in videoManagerStore.projects"
                :key="project.id"
                :project="project"
                :selected="videoManagerStore.selectedIds.includes(project.id)"
                @toggle-select="videoManagerStore.toggleSelection"
                @delete="handleDelete"
                @render="handleRender"
                @download="handleDownload"
            />
        </div>

        <!-- List view -->
        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="pl-4 pr-2 py-3 w-10">
                            <input
                                type="checkbox"
                                :checked="videoManagerStore.selectedIds.length === videoManagerStore.projects.length && videoManagerStore.projects.length > 0"
                                @change="videoManagerStore.selectedIds.length === videoManagerStore.projects.length ? videoManagerStore.clearSelection() : videoManagerStore.selectAll()"
                                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-gray-900 cursor-pointer"
                            />
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('videoManager.library.colTitle') }}</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('videoManager.library.colStatus') }}</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('videoManager.library.colDuration') }}</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('videoManager.library.colStyle') }}</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('videoManager.library.colCreated') }}</th>
                        <th class="px-3 py-3 w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <VideoProjectRow
                        v-for="project in videoManagerStore.projects"
                        :key="project.id"
                        :project="project"
                        :selected="videoManagerStore.selectedIds.includes(project.id)"
                        @toggle-select="videoManagerStore.toggleSelection"
                        @delete="handleDelete"
                        @render="handleRender"
                        @download="handleDownload"
                    />
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="videoManagerStore.pagination.lastPage > 1"
            class="flex items-center justify-between"
        >
            <p class="text-sm text-gray-500">
                {{ t('videoManager.library.showing', { from: (page - 1) * videoManagerStore.pagination.perPage + 1, to: Math.min(page * videoManagerStore.pagination.perPage, videoManagerStore.pagination.total), total: videoManagerStore.pagination.total }) }}
            </p>
            <div class="flex gap-1">
                <button
                    @click="page = Math.max(1, page - 1)"
                    :disabled="page === 1"
                    class="px-3 py-1.5 text-sm rounded-lg border border-gray-800 text-gray-400 hover:text-white hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ t('videoManager.library.prev') }}
                </button>
                <button
                    @click="page = Math.min(videoManagerStore.pagination.lastPage, page + 1)"
                    :disabled="page === videoManagerStore.pagination.lastPage"
                    class="px-3 py-1.5 text-sm rounded-lg border border-gray-800 text-gray-400 hover:text-white hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ t('videoManager.library.next') }}
                </button>
            </div>
        </div>
    </div>
</template>
