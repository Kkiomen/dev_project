<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import PlanSlotsTable from '@/components/manager/PlanSlotsTable.vue';
import AddSlotModal from '@/components/manager/AddSlotModal.vue';
import QuickPlanModal from '@/components/manager/QuickPlanModal.vue';

const { t } = useI18n();
const router = useRouter();
const managerStore = useManagerStore();
const toast = useToast();

// --- State ---
const showAddModal = ref(false);
const addSlotDate = ref('');
const showQuickPlan = ref(false);
const showGenerateMenu = ref(false);
const planGenerating = ref(false);
const generatingPlanId = ref(null);
const generationStep = ref('');
const generationSlotsCreated = ref(0);
const generationTotalSlots = ref(0);
const pollTimer = ref(null);
const generationFailed = ref(false);
const contentPollTimer = ref(null);
const generatingAllContent = ref(false);

const POLL_INTERVAL = 3000;
const MAX_POLL_TIME = 200000;
const CONTENT_POLL_INTERVAL = 4000;

// --- Computed ---
const allSlots = computed(() => managerStore.currentPlan?.slots || []);

const slots = computed(() => allSlots.value.filter(s => s.status === 'planned' || s.status === 'generating' || s.status === 'content_ready'));

const pillars = computed(() => {
    const cp = managerStore.strategy?.content_pillars;
    if (!cp || !Array.isArray(cp)) return [];
    return cp.map(p => typeof p === 'string' ? p : (p.name || p.label || ''));
});

const totalCount = computed(() => slots.value.length);

const plannedCount = computed(() => slots.value.filter(s => s.status === 'planned').length);

const generatingCount = computed(() => slots.value.filter(s => s.status === 'generating').length);

const hasPlannedSlots = computed(() => plannedCount.value > 0);

const hasGeneratingSlots = computed(() => generatingCount.value > 0);

const allContentGenerated = computed(() => {
    if (planGenerating.value || allSlots.value.length === 0) return false;
    return allSlots.value.every(s => s.status !== 'planned' && s.status !== 'generating');
});

// --- Polling for plan generation ---
const stopPolling = () => {
    if (pollTimer.value) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
};

const startPolling = (planId) => {
    stopPolling();
    const startTime = Date.now();

    pollTimer.value = setInterval(async () => {
        if (Date.now() - startTime > MAX_POLL_TIME) {
            stopPolling();
            planGenerating.value = false;
            generationFailed.value = true;
            toast.error(t('manager.calendar.progress.timeout'));
            return;
        }

        const data = await managerStore.fetchPlanGenerationStatus(planId);
        if (!data) return;

        generationStep.value = data.step;
        generationSlotsCreated.value = data.slots_created || 0;
        generationTotalSlots.value = data.total_slots || 0;

        if (data.status !== 'generating' && data.step === 'done') {
            stopPolling();
            generationStep.value = 'done';

            setTimeout(async () => {
                await managerStore.fetchCurrentPlan();
                planGenerating.value = false;
                generatingPlanId.value = null;
                toast.success(t('manager.contentList.planGenerated'));
            }, 1000);
        } else if (data.step === 'failed') {
            stopPolling();
            planGenerating.value = false;
            generatingPlanId.value = null;
            generationFailed.value = true;
            await managerStore.fetchCurrentPlan();
            toast.error(data.error === 'no_api_key'
                ? t('manager.addSlot.noApiKey')
                : t('manager.contentList.planGenerateError'));
        }
    }, POLL_INTERVAL);
};

const handleGeneratePlan = async (mode = 'full') => {
    if (planGenerating.value) return;

    planGenerating.value = true;
    generationFailed.value = false;
    generationStep.value = 'queued';
    generationSlotsCreated.value = 0;
    generationTotalSlots.value = 0;

    try {
        const now = new Date();
        const month = now.getMonth() + 1;
        const year = now.getFullYear();
        const params = { month, year };

        if (mode === 'from_today') {
            params.fromDate = now.toISOString().split('T')[0];
        }

        const response = await managerStore.generateContentPlan(params);
        const planId = response?.data?.id || managerStore.currentPlan?.id;

        if (planId) {
            generatingPlanId.value = planId;
            startPolling(planId);
        }
    } catch (error) {
        planGenerating.value = false;
        const errorCode = error.response?.data?.error;
        if (errorCode === 'no_api_key') {
            toast.error(t('manager.addSlot.noApiKey'));
        } else if (errorCode === 'no_active_strategy') {
            toast.error(t('manager.calendar.noActiveStrategy'));
        } else {
            toast.error(t('manager.contentList.planGenerateError'));
        }
    }
};

// --- Content generation polling ---
const stopContentPolling = () => {
    if (contentPollTimer.value) {
        clearInterval(contentPollTimer.value);
        contentPollTimer.value = null;
    }
};

const startContentPolling = () => {
    if (contentPollTimer.value) return;

    contentPollTimer.value = setInterval(async () => {
        await managerStore.fetchCurrentPlan();

        if (!hasGeneratingSlots.value) {
            stopContentPolling();
            generatingAllContent.value = false;
        }
    }, CONTENT_POLL_INTERVAL);
};

// --- Content generation handlers ---
const handleGenerateAllContent = async () => {
    const planId = managerStore.currentPlan?.id;
    if (!planId || generatingAllContent.value) return;

    const count = plannedCount.value;
    generatingAllContent.value = true;

    // Optimistic update: mark all planned slots as generating
    allSlots.value.forEach(slot => {
        if (slot.status === 'planned') {
            slot.status = 'generating';
        }
    });

    try {
        await managerStore.generateAllContent(planId);
        toast.success(t('manager.contentList.generateAllContentSuccess', { count }));
        startContentPolling();
    } catch {
        // Revert optimistic update
        await managerStore.fetchCurrentPlan();
        generatingAllContent.value = false;
        toast.error(t('manager.contentList.generateAllContentError'));
    }
};

const handleGenerateSlot = async (slotId) => {
    const planId = managerStore.currentPlan?.id;
    if (!planId) return;

    // Optimistic update
    const slot = allSlots.value.find(s => s.id === slotId);
    if (slot) slot.status = 'generating';

    try {
        await managerStore.generateSlotContent(planId, slotId);
        toast.success(t('manager.contentList.generateSingleSuccess'));
        startContentPolling();
    } catch {
        // Revert
        if (slot) slot.status = 'planned';
        toast.error(t('manager.contentList.generateSingleError'));
    }
};

const handlePreviewSlot = (socialPostId) => {
    router.push({ name: 'manager.content.edit', params: { id: socialPostId } });
};

const handleGoToApproval = () => {
    router.push({ name: 'manager.approval' });
};

// --- Event handlers ---
const handleSlotUpdated = () => {
    managerStore.fetchCurrentPlan();
};

const handleSlotCreated = () => {
    managerStore.fetchCurrentPlan();
};

const handleAddNew = () => {
    addSlotDate.value = new Date().toISOString().split('T')[0];
    showAddModal.value = true;
};

const handleAddBetween = ({ dateStr }) => {
    addSlotDate.value = dateStr;
    showAddModal.value = true;
};

// --- Lifecycle ---
onMounted(() => {
    managerStore.fetchCurrentPlan();
    if (!managerStore.strategy) {
        managerStore.fetchStrategy();
    }
});

onUnmounted(() => {
    stopPolling();
    stopContentPolling();
});

watch(() => managerStore.currentBrandId, () => {
    stopPolling();
    stopContentPolling();
    planGenerating.value = false;
    generatingAllContent.value = false;
    managerStore.fetchCurrentPlan();
    managerStore.fetchStrategy();
});

// Resume polling if plan is already generating
watch(() => managerStore.currentPlan?.status, (status) => {
    if (status === 'generating' && !pollTimer.value) {
        const planId = managerStore.currentPlan?.id;
        if (planId) {
            planGenerating.value = true;
            generatingPlanId.value = planId;
            generationStep.value = 'calling_ai';
            startPolling(planId);
        }
    }
});

// Auto-start content polling if there are generating slots
watch(slots, (currentSlots) => {
    if (currentSlots.some(s => s.status === 'generating') && !contentPollTimer.value) {
        startContentPolling();
    }
}, { immediate: true });
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('manager.contentList.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('manager.contentList.subtitle') }}</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Stats pill -->
                <div v-if="totalCount > 0 || generatingCount > 0" class="flex items-center gap-3 text-xs text-gray-400">
                    <span>{{ t('manager.contentList.totalItems', { count: totalCount }) }}</span>
                    <span v-if="plannedCount > 0" class="text-amber-400">{{ t('manager.contentList.plannedItems', { count: plannedCount }) }}</span>
                    <span v-if="generatingCount > 0" class="inline-flex items-center gap-1.5 text-purple-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                        {{ generatingCount }} generating
                    </span>
                </div>

                <!-- Generate All Content button -->
                <button
                    v-if="hasPlannedSlots"
                    @click="handleGenerateAllContent"
                    :disabled="generatingAllContent"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-300 hover:bg-emerald-600/30 hover:text-emerald-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg v-if="generatingAllContent" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                    </svg>
                    {{ generatingAllContent ? t('manager.contentList.generateAllContentLoading') : t('manager.contentList.generateAllContent', { count: plannedCount }) }}
                </button>

                <!-- Add item button -->
                <button
                    @click="handleAddNew"
                    :disabled="!managerStore.currentPlan?.id"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:text-white hover:border-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('manager.contentList.addItem') }}
                </button>

                <!-- Quick Plan button -->
                <button
                    @click="showQuickPlan = true"
                    :disabled="!managerStore.currentPlan?.id"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-600/30 hover:text-indigo-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                    {{ t('manager.quickPlan.title') }}
                </button>

                <!-- Generate Plan dropdown -->
                <div class="relative">
                    <button
                        @click="showGenerateMenu = !showGenerateMenu"
                        :disabled="planGenerating"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-300 hover:bg-purple-600/30 hover:text-purple-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg v-if="planGenerating" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                        </svg>
                        {{ t('manager.contentList.generateWithAI') }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div
                        v-if="showGenerateMenu"
                        class="absolute right-0 top-full mt-1 w-72 rounded-xl bg-gray-900 border border-gray-700 shadow-xl z-20 overflow-hidden"
                    >
                        <button
                            @click="handleGeneratePlan('full'); showGenerateMenu = false"
                            :disabled="planGenerating || !managerStore.strategyIsActive"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-white">{{ t('manager.calendar.generateFullMonth') }}</p>
                                <p class="text-[11px] text-gray-400">{{ t('manager.calendar.generateFullMonthDesc') }}</p>
                            </div>
                        </button>
                        <button
                            @click="handleGeneratePlan('from_today'); showGenerateMenu = false"
                            :disabled="planGenerating || !managerStore.strategyIsActive"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-800 transition-colors border-t border-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-white">{{ t('manager.calendar.generateFromToday') }}</p>
                                <p class="text-[11px] text-gray-400">{{ t('manager.calendar.generateFromTodayDesc') }}</p>
                            </div>
                        </button>
                    </div>
                    <!-- Backdrop to close menu -->
                    <div
                        v-if="showGenerateMenu"
                        class="fixed inset-0 z-10"
                        @click="showGenerateMenu = false"
                    />
                </div>
            </div>
        </div>

        <!-- No strategy warning -->
        <div
            v-if="!managerStore.strategyIsActive && !managerStore.contentPlansLoading"
            class="mb-4 rounded-lg bg-amber-500/10 border border-amber-500/20 px-4 py-3 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <p class="text-sm text-amber-300">{{ t('manager.contentList.noStrategyWarning') }}</p>
        </div>

        <!-- Filter info banner -->
        <div
            v-if="allSlots.length > slots.length && slots.length > 0"
            class="mb-4 rounded-lg bg-blue-500/10 border border-blue-500/20 px-4 py-3 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <p class="text-sm text-blue-300">{{ t('manager.contentList.filterInfo') }}</p>
        </div>

        <!-- Loading state -->
        <div v-if="managerStore.contentPlansLoading && !planGenerating" class="flex items-center justify-center py-24">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Plan generation progress -->
        <div
            v-else-if="planGenerating"
            class="rounded-xl bg-gray-900 border border-gray-800 p-8 sm:p-12 flex flex-col items-center justify-center text-center"
        >
            <div class="w-16 h-16 rounded-full bg-purple-500/10 flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.calendar.progress.title') }}</h3>
            <p class="text-sm text-gray-400 mb-8">{{ t('manager.calendar.progress.subtitle') }}</p>

            <!-- Steps -->
            <div class="w-full max-w-xs space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                        :class="generationStep === 'queued' ? 'bg-purple-500/20 ring-2 ring-purple-500/50' : 'bg-emerald-500/20'"
                    >
                        <svg v-if="generationStep === 'queued'" class="w-3.5 h-3.5 text-purple-400 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <svg v-else class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </div>
                    <span class="text-sm" :class="generationStep === 'queued' ? 'text-white' : 'text-gray-500'">
                        {{ t('manager.calendar.progress.stepQueued') }}
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                        :class="generationStep === 'calling_ai' ? 'bg-purple-500/20 ring-2 ring-purple-500/50' : ['creating_slots', 'done'].includes(generationStep) ? 'bg-emerald-500/20' : 'bg-gray-800'"
                    >
                        <svg v-if="generationStep === 'calling_ai'" class="w-3.5 h-3.5 text-purple-400 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <svg v-else-if="['creating_slots', 'done'].includes(generationStep)" class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        <span v-else class="w-1.5 h-1.5 rounded-full bg-gray-600" />
                    </div>
                    <span class="text-sm" :class="generationStep === 'calling_ai' ? 'text-white' : ['creating_slots', 'done'].includes(generationStep) ? 'text-gray-500' : 'text-gray-600'">
                        {{ t('manager.calendar.progress.stepAi') }}
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                        :class="generationStep === 'creating_slots' ? 'bg-purple-500/20 ring-2 ring-purple-500/50' : generationStep === 'done' ? 'bg-emerald-500/20' : 'bg-gray-800'"
                    >
                        <svg v-if="generationStep === 'creating_slots'" class="w-3.5 h-3.5 text-purple-400 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <svg v-else-if="generationStep === 'done'" class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        <span v-else class="w-1.5 h-1.5 rounded-full bg-gray-600" />
                    </div>
                    <span class="text-sm" :class="generationStep === 'creating_slots' ? 'text-white' : generationStep === 'done' ? 'text-gray-500' : 'text-gray-600'">
                        {{ t('manager.calendar.progress.stepSlots') }}
                        <span v-if="generationStep === 'creating_slots' && generationTotalSlots > 0" class="text-gray-500">
                            ({{ generationSlotsCreated }}/{{ generationTotalSlots }})
                        </span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                        :class="generationStep === 'done' ? 'bg-emerald-500/20' : 'bg-gray-800'"
                    >
                        <svg v-if="generationStep === 'done'" class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        <span v-else class="w-1.5 h-1.5 rounded-full bg-gray-600" />
                    </div>
                    <span class="text-sm" :class="generationStep === 'done' ? 'text-emerald-400 font-medium' : 'text-gray-600'">
                        {{ t('manager.calendar.progress.stepDone') }}
                    </span>
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-8">{{ t('manager.calendar.progress.patience') }}</p>
        </div>

        <!-- All content generated state -->
        <div
            v-else-if="allContentGenerated"
            class="rounded-xl bg-gray-900 border border-gray-800 p-8 sm:p-12 flex flex-col items-center justify-center text-center"
        >
            <div class="w-16 h-16 rounded-full bg-emerald-500/10 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.contentList.allGeneratedTitle') }}</h3>
            <p class="text-sm text-gray-400 max-w-md mb-6">{{ t('manager.contentList.allGeneratedDescription') }}</p>
            <button
                @click="handleGoToApproval"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
                {{ t('manager.contentList.goToApproval') }}
            </button>
        </div>

        <!-- Empty state (no plan at all) -->
        <div
            v-else-if="allSlots.length === 0 && !managerStore.contentPlansLoading"
            class="rounded-xl bg-gray-900 border border-gray-800 p-8 sm:p-12 flex flex-col items-center justify-center text-center"
        >
            <div class="w-16 h-16 rounded-full bg-purple-500/10 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.contentList.emptyTitle') }}</h3>
            <p class="text-sm text-gray-400 max-w-md mb-6">{{ t('manager.contentList.emptyDescription') }}</p>

            <!-- Generate plan options -->
            <div class="w-full max-w-sm space-y-3 mb-4">
                <button
                    @click="handleGeneratePlan('full')"
                    :disabled="planGenerating || !managerStore.strategyIsActive"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-lg bg-purple-600/10 border border-purple-500/30 text-left hover:bg-purple-600/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <div class="w-9 h-9 rounded-lg bg-purple-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white">{{ t('manager.calendar.generateFullMonth') }}</p>
                        <p class="text-xs text-gray-400">{{ t('manager.calendar.generateFullMonthDesc') }}</p>
                    </div>
                </button>

                <button
                    @click="handleGeneratePlan('from_today')"
                    :disabled="planGenerating || !managerStore.strategyIsActive"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-lg bg-indigo-600/10 border border-indigo-500/30 text-left hover:bg-indigo-600/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white">{{ t('manager.calendar.generateFromToday') }}</p>
                        <p class="text-xs text-gray-400">{{ t('manager.calendar.generateFromTodayDesc') }}</p>
                    </div>
                </button>
            </div>

            <button
                @click="handleAddNew"
                class="inline-flex items-center justify-center gap-2 px-5 py-2 text-sm font-medium text-gray-400 hover:text-white transition-colors"
            >
                {{ t('manager.contentList.addManually') }}
            </button>
        </div>

        <!-- Content list table -->
        <PlanSlotsTable
            v-else
            :slots="slots"
            :plan-id="managerStore.currentPlan?.id"
            :pillars="pillars"
            @updated="handleSlotUpdated"
            @add="handleAddNew"
            @add-between="handleAddBetween"
            @generate-slot="handleGenerateSlot"
            @preview-slot="handlePreviewSlot"
        />

        <!-- Add Slot Modal -->
        <AddSlotModal
            :show="showAddModal"
            :date-str="addSlotDate"
            :plan-id="managerStore.currentPlan?.id"
            @close="showAddModal = false"
            @created="handleSlotCreated"
        />

        <!-- Quick Plan Modal -->
        <QuickPlanModal
            :show="showQuickPlan"
            :plan-id="managerStore.currentPlan?.id"
            @close="showQuickPlan = false"
            @created="handleSlotCreated"
        />
    </div>
</template>
