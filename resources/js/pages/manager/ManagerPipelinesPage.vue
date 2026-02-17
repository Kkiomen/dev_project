<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';
import { useBrandsStore } from '@/stores/brands';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import Modal from '@/components/common/Modal.vue';
import SkeletonLoader from '@/components/common/SkeletonLoader.vue';

const { t } = useI18n();
const router = useRouter();
const store = usePipelinesStore();
const brandsStore = useBrandsStore();
const toast = useToast();
const { confirm } = useConfirm();

const showCreateModal = ref(false);
const createForm = ref({ name: '', description: '' });
const creating = ref(false);

onMounted(() => {
    if (store.currentBrandId) {
        store.fetchPipelines();
    }
});

// Retry when brand becomes available (on F5 refresh)
watch(() => brandsStore.currentBrand, (brand) => {
    if (brand && store.pipelines.length === 0 && !store.pipelinesLoading) {
        store.fetchPipelines();
    }
});

const openCreateModal = () => {
    createForm.value = { name: '', description: '' };
    showCreateModal.value = true;
};

const createPipeline = async () => {
    if (!createForm.value.name.trim()) return;
    creating.value = true;
    try {
        const pipeline = await store.createPipeline(createForm.value);
        showCreateModal.value = false;
        toast.success(t('pipeline.toast.created'));
        router.push({ name: 'manager.pipeline.editor', params: { pipelineId: pipeline.id } });
    } catch (error) {
        toast.error(t('pipeline.errors.createFailed'));
    } finally {
        creating.value = false;
    }
};

const confirmDelete = async (pipeline) => {
    const confirmed = await confirm({
        title: t('pipeline.deletePipeline'),
        message: t('pipeline.deleteConfirm'),
        confirmText: t('common.delete'),
        variant: 'danger',
    });
    if (!confirmed) return;

    try {
        await store.deletePipeline(pipeline.id);
        toast.success(t('pipeline.toast.deleted'));
    } catch (error) {
        toast.error(t('pipeline.errors.deleteFailed'));
    }
};

const openEditor = (pipeline) => {
    router.push({ name: 'manager.pipeline.editor', params: { pipelineId: pipeline.id } });
};

const getStatusColor = (status) => {
    const colors = {
        draft: 'bg-gray-500/20 text-gray-400',
        active: 'bg-green-500/20 text-green-400',
        archived: 'bg-yellow-500/20 text-yellow-400',
    };
    return colors[status] || colors.draft;
};
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('pipeline.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('pipeline.subtitle') }}</p>
            </div>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 transition shrink-0"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ t('pipeline.createPipeline') }}
            </button>
        </div>

        <!-- Loading -->
        <SkeletonLoader v-if="store.pipelinesLoading" variant="card-grid" :count="6" />

        <!-- Empty State -->
        <div v-else-if="store.pipelines.length === 0" class="text-center py-20">
            <svg class="mx-auto h-16 w-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-300">{{ t('pipeline.noPipelines') }}</h3>
            <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">{{ t('pipeline.noPipelinesDesc') }}</p>
            <button
                @click="openCreateModal"
                class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 transition"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ t('pipeline.createPipeline') }}
            </button>
        </div>

        <!-- Pipeline Cards Grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="pipeline in store.pipelines"
                :key="pipeline.id"
                @click="openEditor(pipeline)"
                class="group bg-gray-800/50 border border-gray-700/50 rounded-xl p-5 cursor-pointer hover:bg-gray-800 hover:border-gray-600 transition-all duration-200"
            >
                <!-- Thumbnail or placeholder -->
                <div class="aspect-video bg-gray-900 rounded-lg mb-4 flex items-center justify-center overflow-hidden">
                    <img
                        v-if="pipeline.thumbnail_url"
                        :src="pipeline.thumbnail_url"
                        class="w-full h-full object-cover"
                        :alt="pipeline.name"
                    />
                    <svg v-else class="w-12 h-12 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                </div>

                <!-- Info -->
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-white truncate">{{ pipeline.name }}</h3>
                        <p v-if="pipeline.description" class="mt-1 text-xs text-gray-400 line-clamp-2">{{ pipeline.description }}</p>
                    </div>
                    <span :class="['shrink-0 px-2 py-0.5 text-xs font-medium rounded-full', getStatusColor(pipeline.status)]">
                        {{ t(`pipeline.${pipeline.status}`) }}
                    </span>
                </div>

                <!-- Meta -->
                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Z" />
                        </svg>
                        {{ pipeline.nodes_count ?? 0 }} {{ t('pipeline.nodes') }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                        {{ pipeline.runs_count ?? 0 }} {{ t('pipeline.runs') }}
                    </span>
                </div>

                <!-- Actions -->
                <div class="mt-3 pt-3 border-t border-gray-700/50 flex items-center justify-between">
                    <span class="text-xs text-gray-500">
                        {{ pipeline.last_run ? t('pipeline.lastRun') + ': ' + new Date(pipeline.last_run.created_at).toLocaleDateString() : t('pipeline.noRuns') }}
                    </span>
                    <button
                        @click.stop="confirmDelete(pipeline)"
                        class="p-1 rounded text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-white mb-4">{{ t('pipeline.newPipeline') }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('pipeline.name') }}</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            :placeholder="t('pipeline.namePlaceholder')"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            @keydown.enter="createPipeline"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('pipeline.description') }}</label>
                        <textarea
                            v-model="createForm.description"
                            :placeholder="t('pipeline.descriptionPlaceholder')"
                            rows="3"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showCreateModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-200 transition"
                    >
                        {{ t('pipeline.cancel') }}
                    </button>
                    <button
                        @click="createPipeline"
                        :disabled="creating || !createForm.name.trim()"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ creating ? t('pipeline.creating') : t('pipeline.create') }}
                    </button>
                </div>
            </div>
        </Modal>

    </div>
</template>
