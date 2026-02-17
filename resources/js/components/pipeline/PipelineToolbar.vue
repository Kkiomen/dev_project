<script setup>
import { computed, ref, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    pipelineId: { type: String, required: true },
    lastSavedAt: { type: Date, default: null },
});

const emit = defineEmits(['execute']);

const { t } = useI18n();
const router = useRouter();
const store = usePipelinesStore();
const toast = useToast();

const isEditing = ref(false);
const editNameRef = ref(null);
const editName = ref('');

const pipelineName = computed(() => store.currentPipeline?.name || t('pipeline.toolbar.untitled'));

const startEditing = () => {
    editName.value = store.currentPipeline?.name || '';
    isEditing.value = true;
    nextTick(() => editNameRef.value?.focus());
};

const finishEditing = async () => {
    isEditing.value = false;
    const trimmed = editName.value.trim();
    if (trimmed && trimmed !== store.currentPipeline?.name) {
        try {
            await store.updatePipeline(props.pipelineId, { name: trimmed });
        } catch {
            toast.error(t('pipeline.errors.saveFailed'));
        }
    }
};

const goBack = () => {
    router.push({ name: 'manager.pipelines' });
};
</script>

<template>
    <div class="h-12 flex items-center gap-3 px-4 bg-white border-b border-gray-200 shrink-0">
        <!-- Breadcrumb: "Pipelines" text link -->
        <button
            @click="goBack"
            class="text-sm text-gray-400 hover:text-gray-600 transition"
        >
            {{ t('pipeline.toolbar.pipelines') }}
        </button>

        <!-- Breadcrumb separator -->
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>

        <!-- Pipeline Name (editable) -->
        <div class="flex items-center gap-2 min-w-0">
            <input
                v-if="isEditing"
                ref="editNameRef"
                v-model="editName"
                @blur="finishEditing"
                @keydown.enter="finishEditing"
                @keydown.escape="isEditing = false"
                class="text-sm font-medium text-gray-900 bg-transparent border-b border-indigo-500 outline-none px-0 py-0.5 min-w-[120px]"
            />
            <button
                v-else
                @click="startEditing"
                class="text-sm font-medium text-gray-900 hover:text-indigo-600 transition truncate"
            >
                {{ pipelineName }}
            </button>
        </div>

        <!-- Save status -->
        <div class="flex items-center gap-1.5">
            <span v-if="store.saving" class="text-[10px] text-gray-400 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 animate-pulse" />
                {{ t('pipeline.saving') }}
            </span>
            <span v-else-if="store.isDirty" class="text-[10px] text-amber-500 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400" />
                {{ t('pipeline.toolbar.unsavedChanges') }}
            </span>
            <span v-else-if="lastSavedAt" class="text-[10px] text-gray-400 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400" />
                {{ t('pipeline.toolbar.autoSaved') }}
            </span>
        </div>

        <div class="flex-1" />

        <!-- Execute button -->
        <button
            @click="$emit('execute')"
            :disabled="store.executing"
            class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-500 transition disabled:opacity-50 shadow-sm"
        >
            <svg v-if="!store.executing" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
            </svg>
            <span v-else class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            {{ store.executing ? t('pipeline.executing') : t('pipeline.execute') }}
        </button>
    </div>
</template>
