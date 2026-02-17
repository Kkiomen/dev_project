<script setup>
import { onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';
import PipelineStatusBadge from './PipelineStatusBadge.vue';

const { t } = useI18n();
const store = usePipelinesStore();

const pipelineId = computed(() => store.currentPipeline?.id);

onMounted(() => {
    if (pipelineId.value) {
        store.fetchRuns(pipelineId.value);
    }
});

const selectRun = (run) => {
    store.currentRun = run;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="border-t border-gray-800 pt-4">
        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
            {{ t('pipeline.runHistory.title') }}
        </h4>

        <div v-if="store.runsLoading" class="text-center py-4">
            <div class="w-5 h-5 border-2 border-gray-600 border-t-indigo-500 rounded-full animate-spin mx-auto" />
        </div>

        <div v-else-if="store.runs.length === 0" class="text-center py-4">
            <p class="text-xs text-gray-500">{{ t('pipeline.runHistory.noRuns') }}</p>
        </div>

        <div v-else class="space-y-1 max-h-48 overflow-y-auto">
            <button
                v-for="run in store.runs"
                :key="run.id"
                @click="selectRun(run)"
                :class="[
                    'w-full flex items-center justify-between px-2 py-1.5 rounded-md text-left transition',
                    store.currentRun?.id === run.id
                        ? 'bg-indigo-600/20 border border-indigo-500/30'
                        : 'hover:bg-gray-800 border border-transparent',
                ]"
            >
                <div class="min-w-0">
                    <span class="text-[10px] text-gray-400 block truncate">
                        {{ formatDate(run.created_at) }}
                    </span>
                </div>
                <PipelineStatusBadge :status="run.status" size="sm" />
            </button>
        </div>
    </div>
</template>
