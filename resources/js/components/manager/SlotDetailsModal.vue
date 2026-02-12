<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    slot: { type: Object, default: null },
    planId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'updated']);

const { t } = useI18n();
const router = useRouter();
const managerStore = useManagerStore();
const toast = useToast();

const generating = ref(false);
const generationStep = ref(0);
const generationFailed = ref(false);
const pollTimer = ref(null);
const elapsedSeconds = ref(0);
const elapsedTimer = ref(null);

const POLL_INTERVAL = 3000;
const MAX_POLL_TIME = 180000;

const statusColorMap = {
    planned: 'bg-gray-500/20 text-gray-300 border-gray-500/30',
    generating: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
    content_ready: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    media_ready: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
    approved: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
    published: 'bg-green-500/20 text-green-400 border-green-500/30',
    skipped: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
};

const platformColorMap = {
    instagram: 'bg-pink-500/20 text-pink-400',
    facebook: 'bg-blue-500/20 text-blue-400',
    tiktok: 'bg-gray-500/20 text-gray-300',
    linkedin: 'bg-sky-500/20 text-sky-400',
    x: 'bg-gray-500/20 text-gray-300',
    youtube: 'bg-red-500/20 text-red-400',
};

const statusColor = computed(() => {
    return statusColorMap[props.slot?.status] || statusColorMap.planned;
});

const platformColor = computed(() => {
    return platformColorMap[props.slot?.platform] || 'bg-gray-500/20 text-gray-300';
});

const isPlanned = computed(() => props.slot?.status === 'planned');
const isGenerating = computed(() => generating.value || props.slot?.status === 'generating');

const formattedDate = computed(() => {
    if (!props.slot?.scheduled_date) return '';
    const date = new Date(props.slot.scheduled_date);
    return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
});

const formattedTime = computed(() => {
    if (!props.slot?.scheduled_time) return '';
    return props.slot.scheduled_time.substring(0, 5);
});

const formattedElapsed = computed(() => {
    const mins = Math.floor(elapsedSeconds.value / 60);
    const secs = elapsedSeconds.value % 60;
    if (mins > 0) return `${mins}:${secs.toString().padStart(2, '0')}`;
    return `${secs}s`;
});

const progressSteps = computed(() => [
    {
        label: t('manager.slotModal.progress.step1'),
        active: generationStep.value >= 1,
        done: generationStep.value > 1,
    },
    {
        label: t('manager.slotModal.progress.step2'),
        active: generationStep.value >= 2,
        done: generationStep.value > 2,
    },
    {
        label: t('manager.slotModal.progress.step3'),
        active: generationStep.value >= 3,
        done: generationStep.value > 3,
    },
]);

const stopPolling = () => {
    if (pollTimer.value) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
    if (elapsedTimer.value) {
        clearInterval(elapsedTimer.value);
        elapsedTimer.value = null;
    }
};

const simulateSteps = (startStep = 1) => {
    generationStep.value = startStep;
    if (startStep < 2) {
        setTimeout(() => { if (generating.value) generationStep.value = 2; }, 8000);
    }
    if (startStep < 3) {
        setTimeout(() => { if (generating.value) generationStep.value = 3; }, startStep >= 2 ? 8000 : 20000);
    }
};

const startPolling = () => {
    stopPolling();

    elapsedTimer.value = setInterval(() => {
        elapsedSeconds.value++;
    }, 1000);

    const startTime = Date.now();

    pollTimer.value = setInterval(async () => {
        if (Date.now() - startTime > MAX_POLL_TIME) {
            stopPolling();
            generating.value = false;
            generationFailed.value = true;
            toast.error(t('manager.slotModal.progress.timeout'));
            return;
        }

        try {
            const data = await managerStore.fetchSlotStatus(props.planId, props.slot.id);
            if (!data) return;

            if (data.status === 'content_ready' || data.status === 'media_ready') {
                stopPolling();
                generationStep.value = 4;

                setTimeout(() => {
                    generating.value = false;
                    toast.success(t('manager.slotModal.progress.done'));
                    emit('updated');
                    emit('close');
                }, 1200);
            } else if (data.status === 'planned') {
                stopPolling();
                generating.value = false;
                generationFailed.value = true;
                toast.error(t('manager.slotModal.progress.failed'));
            }
        } catch {
            // Silently retry on network errors
        }
    }, POLL_INTERVAL);
};

const handleGenerateContent = async () => {
    if (!props.planId || !props.slot?.id) return;

    generating.value = true;
    generationFailed.value = false;
    generationStep.value = 0;
    elapsedSeconds.value = 0;

    try {
        await managerStore.generateSlotContent(props.planId, props.slot.id);
    } catch {
        toast.error(t('manager.slotModal.generateError'));
        generating.value = false;
        return;
    }

    simulateSteps();
    startPolling();
};

const close = () => {
    if (generating.value) return;
    stopPolling();
    emit('close');
};

// Resume polling if modal opens with a slot already in 'generating' status
watch(() => [props.show, props.slot?.status], ([show, status]) => {
    if (show && status === 'generating' && !pollTimer.value) {
        generating.value = true;
        generationFailed.value = false;
        elapsedSeconds.value = 0;
        simulateSteps(2);
        startPolling();
    }
    if (!show) {
        stopPolling();
        generating.value = false;
        generationStep.value = 0;
        generationFailed.value = false;
        elapsedSeconds.value = 0;
    }
}, { immediate: true });

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div v-if="slot" class="bg-gray-900 -m-6 rounded-xl">
            <!-- Header -->
            <div class="p-5 border-b border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-white truncate">
                            {{ slot.topic || t('manager.slotModal.title') }}
                        </h3>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                :class="statusColor"
                            >
                                {{ t(`manager.slotModal.statusLabels.${slot.status}`) }}
                            </span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="platformColor"
                            >
                                {{ slot.platform }}
                            </span>
                        </div>
                    </div>
                    <button
                        @click="close"
                        :disabled="generating"
                        class="shrink-0 p-1.5 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-4">
                <!-- Generation progress -->
                <div v-if="isGenerating" class="rounded-xl bg-gray-800/70 border border-gray-700/50 p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">{{ t('manager.slotModal.progress.title') }}</p>
                            <p class="text-xs text-gray-500">{{ formattedElapsed }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(step, idx) in progressSteps"
                            :key="idx"
                            class="flex items-center gap-3"
                        >
                            <div class="shrink-0">
                                <div v-if="step.done" class="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div v-else-if="step.active" class="w-5 h-5 rounded-full bg-indigo-500/30 flex items-center justify-center">
                                    <div class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></div>
                                </div>
                                <div v-else class="w-5 h-5 rounded-full bg-gray-700/50 flex items-center justify-center">
                                    <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                                </div>
                            </div>
                            <span
                                class="text-sm transition-colors"
                                :class="{
                                    'text-emerald-400': step.done,
                                    'text-white': step.active && !step.done,
                                    'text-gray-600': !step.active,
                                }"
                            >
                                {{ step.label }}
                            </span>
                        </div>
                    </div>

                    <p v-if="elapsedSeconds > 60" class="mt-3 text-xs text-gray-500">
                        {{ t('manager.slotModal.progress.patience') }}
                    </p>
                </div>

                <!-- Generation failed -->
                <div v-else-if="generationFailed" class="rounded-xl bg-red-900/20 border border-red-500/30 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <p class="text-sm text-red-300">{{ t('manager.slotModal.progress.failed') }}</p>
                    </div>
                </div>

                <!-- Normal content when not generating -->
                <template v-else>
                    <!-- Schedule info -->
                    <div v-if="formattedDate" class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span class="text-gray-300">{{ formattedDate }}</span>
                        <span v-if="formattedTime" class="text-gray-500">{{ formattedTime }}</span>
                    </div>

                    <!-- Content type & Pillar -->
                    <div class="grid grid-cols-2 gap-3">
                        <div v-if="slot.content_type" class="rounded-lg bg-gray-800/50 border border-gray-700/50 p-3">
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                {{ t('manager.slotModal.contentType') }}
                            </p>
                            <p class="text-sm text-gray-200 capitalize">{{ slot.content_type }}</p>
                        </div>
                        <div v-if="slot.pillar" class="rounded-lg bg-gray-800/50 border border-gray-700/50 p-3">
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                {{ t('manager.slotModal.pillar') }}
                            </p>
                            <p class="text-sm text-gray-200">{{ slot.pillar }}</p>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="slot.description" class="rounded-lg bg-gray-800/50 border border-gray-700/50 p-3">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            {{ t('manager.slotModal.description') }}
                        </p>
                        <p class="text-sm text-gray-300 leading-relaxed">{{ slot.description }}</p>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="p-5 border-t border-gray-800 flex items-center justify-end gap-3">
                <button
                    v-if="!generating"
                    @click="close"
                    class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:text-gray-200 hover:bg-gray-800 transition-colors"
                >
                    {{ t('manager.slotModal.title') }}
                </button>

                <button
                    v-if="isPlanned && !generating"
                    @click="handleGenerateContent"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                    </svg>
                    {{ t('manager.slotModal.generateContent') }}
                </button>

                <button
                    v-else-if="!isPlanned && !generating && slot.social_post_id"
                    @click="router.push({ name: 'manager.content.edit', params: { id: slot.social_post_id } }); close();"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    {{ t('manager.slotModal.viewContent') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
