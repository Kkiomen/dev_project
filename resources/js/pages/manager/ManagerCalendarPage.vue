<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import SlotDetailsModal from '@/components/manager/SlotDetailsModal.vue';
import AddSlotModal from '@/components/manager/AddSlotModal.vue';
import PlanSlotsTable from '@/components/manager/PlanSlotsTable.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

// --- State ---
const currentDate = ref(new Date());
const currentView = ref('month');
const selectedSlot = ref(null);
const showSlotModal = ref(false);
const showAddSlotModal = ref(false);
const addSlotDate = ref('');
const bulkGenerating = ref(false);
const planGenerating = ref(false);
const generatingPlanId = ref(null);
const generationStep = ref('');
const generationSlotsCreated = ref(0);
const generationTotalSlots = ref(0);
const pollTimer = ref(null);
const generationFailed = ref(false);

const POLL_INTERVAL = 3000;
const MAX_POLL_TIME = 200000;

// --- Platform colors ---
const platformColors = {
    instagram: 'bg-pink-500',
    facebook: 'bg-blue-500',
    tiktok: 'bg-gray-100',
    linkedin: 'bg-sky-600',
    x: 'bg-gray-400',
    youtube: 'bg-red-500',
};

// --- Status colors ---
const getStatusColor = (status) => {
    switch (status) {
        case 'generating': return 'bg-amber-400 animate-pulse';
        case 'content_ready': return 'bg-blue-400';
        case 'media_ready': return 'bg-purple-400';
        case 'approved': return 'bg-emerald-400';
        case 'published': return 'bg-green-400';
        case 'skipped': return 'bg-yellow-400';
        default: return 'bg-gray-500';
    }
};

// --- Weekday keys for i18n ---
const weekdayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

// --- Calendar generation: Month view ---
const generateCalendarDays = (year, month) => {
    const firstDay = new Date(year, month, 1);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - ((startDate.getDay() + 6) % 7));

    const days = [];
    const current = new Date(startDate);
    for (let i = 0; i < 42; i++) {
        days.push({
            date: new Date(current),
            dateStr: current.toISOString().split('T')[0],
            day: current.getDate(),
            isCurrentMonth: current.getMonth() === month,
            isToday: current.toDateString() === new Date().toDateString(),
        });
        current.setDate(current.getDate() + 1);
    }
    return days;
};

// --- Calendar generation: Week view ---
const generateWeekDays = (date) => {
    const start = new Date(date);
    const dayOfWeek = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - dayOfWeek);

    const days = [];
    const current = new Date(start);
    for (let i = 0; i < 7; i++) {
        days.push({
            date: new Date(current),
            dateStr: current.toISOString().split('T')[0],
            day: current.getDate(),
            isCurrentMonth: current.getMonth() === date.getMonth(),
            isToday: current.toDateString() === new Date().toDateString(),
        });
        current.setDate(current.getDate() + 1);
    }
    return days;
};

// --- Computed ---
const calendarDays = computed(() => {
    return generateCalendarDays(currentDate.value.getFullYear(), currentDate.value.getMonth());
});

const weekDays = computed(() => {
    return generateWeekDays(currentDate.value);
});

const currentMonthLabel = computed(() => {
    return currentDate.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
});

const currentWeekLabel = computed(() => {
    const days = weekDays.value;
    const start = days[0].date;
    const end = days[6].date;
    const opts = { day: 'numeric', month: 'short' };
    const yearOpts = { day: 'numeric', month: 'short', year: 'numeric' };
    if (start.getMonth() === end.getMonth()) {
        return `${start.toLocaleDateString(undefined, opts)} - ${end.toLocaleDateString(undefined, yearOpts)}`;
    }
    return `${start.toLocaleDateString(undefined, opts)} - ${end.toLocaleDateString(undefined, yearOpts)}`;
});

const navigationLabel = computed(() => {
    if (currentView.value === 'month') return currentMonthLabel.value;
    if (currentView.value === 'week') return currentWeekLabel.value;
    return currentMonthLabel.value;
});

const slots = computed(() => {
    return managerStore.currentPlan?.slots || [];
});

const slotsByDate = computed(() => {
    const map = {};
    for (const slot of slots.value) {
        if (!slot.scheduled_date) continue;
        if (!map[slot.scheduled_date]) {
            map[slot.scheduled_date] = [];
        }
        map[slot.scheduled_date].push(slot);
    }
    for (const dateKey of Object.keys(map)) {
        map[dateKey].sort((a, b) => (a.scheduled_time || '').localeCompare(b.scheduled_time || ''));
    }
    return map;
});

const hasAnySlots = computed(() => {
    return slots.value.length > 0;
});

const plannedSlotsCount = computed(() => {
    return slots.value.filter(s => s.status === 'planned').length;
});

const pillars = computed(() => {
    const cp = managerStore.strategy?.content_pillars;
    if (!cp || !Array.isArray(cp)) return [];
    return cp.map(p => typeof p === 'string' ? p : (p.name || p.label || ''));
});

// --- Navigation ---
const goToToday = () => {
    currentDate.value = new Date();
};

const goToPrevious = () => {
    const d = new Date(currentDate.value);
    if (currentView.value === 'month') {
        d.setMonth(d.getMonth() - 1);
    } else {
        d.setDate(d.getDate() - 7);
    }
    currentDate.value = d;
};

const goToNext = () => {
    const d = new Date(currentDate.value);
    if (currentView.value === 'month') {
        d.setMonth(d.getMonth() + 1);
    } else {
        d.setDate(d.getDate() + 7);
    }
    currentDate.value = d;
};

// --- Slot interactions ---
const handleSlotClick = (slot) => {
    selectedSlot.value = slot;
    showSlotModal.value = true;
};

const handleSlotUpdated = () => {
    managerStore.fetchCurrentPlan();
};

const handleDayClick = (dateStr) => {
    addSlotDate.value = dateStr;
    showAddSlotModal.value = true;
};

const handleSlotCreated = () => {
    managerStore.fetchCurrentPlan();
};

const handleGenerateAll = async () => {
    const planId = managerStore.currentPlan?.id;
    if (!planId || bulkGenerating.value) return;

    bulkGenerating.value = true;
    try {
        const result = await managerStore.generateAllContent(planId);
        if (result?.count > 0) {
            toast.success(t('manager.calendar.generateAllSuccess', { count: result.count }));
            // Mark local slots as generating so button hides immediately
            slots.value.forEach(s => {
                if (s.status === 'planned') s.status = 'generating';
            });
        }
        await managerStore.fetchCurrentPlan();
    } catch {
        toast.error(t('manager.calendar.generateAllError'));
    } finally {
        bulkGenerating.value = false;
    }
};

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
                toast.success(t('manager.calendar.planGenerated'));
            }, 1000);
        } else if (data.step === 'failed') {
            stopPolling();
            planGenerating.value = false;
            generatingPlanId.value = null;
            generationFailed.value = true;
            await managerStore.fetchCurrentPlan();
            toast.error(data.error === 'no_api_key'
                ? t('manager.addSlot.noApiKey')
                : t('manager.calendar.planGenerateError'));
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
        const month = currentDate.value.getMonth() + 1;
        const year = currentDate.value.getFullYear();
        const params = { month, year };

        if (mode === 'from_today') {
            params.fromDate = new Date().toISOString().split('T')[0];
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
            toast.error(t('manager.calendar.planGenerateError'));
        }
    }
};

// --- Helpers ---
const getSlotsForDate = (dateStr) => {
    return slotsByDate.value[dateStr] || [];
};

const getPlatformColor = (platform) => {
    return platformColors[platform] || 'bg-gray-500';
};

const formatTime = (time) => {
    if (!time) return '';
    return time.substring(0, 5);
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

watch(() => managerStore.currentBrandId, () => {
    stopPolling();
    planGenerating.value = false;
    managerStore.fetchCurrentPlan();
    managerStore.fetchStrategy();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('manager.calendar.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('manager.calendar.subtitle') }}</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Generate All button -->
                <button
                    v-if="plannedSlotsCount > 0"
                    @click="handleGenerateAll"
                    :disabled="bulkGenerating"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg v-if="bulkGenerating" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                    </svg>
                    {{ bulkGenerating ? t('manager.calendar.generating') : t('manager.calendar.generateAll', { count: plannedSlotsCount }) }}
                </button>

                <!-- View toggle -->
                <div class="flex items-center rounded-lg bg-gray-900 border border-gray-800 p-0.5">
                    <button
                        v-for="view in ['month', 'week', 'list']"
                        :key="view"
                        @click="currentView = view"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="currentView === view
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-gray-400 hover:text-gray-200'"
                    >
                        {{ t(`manager.calendar.view.${view}`) }}
                    </button>
                </div>

                <!-- Navigation -->
                <div class="flex items-center gap-2">
                    <button
                        @click="goToToday"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-900 border border-gray-800 text-gray-400 hover:text-white hover:border-gray-700 transition-colors"
                    >
                        {{ t('manager.calendar.today') }}
                    </button>

                    <div class="flex items-center rounded-lg bg-gray-900 border border-gray-800">
                        <button
                            @click="goToPrevious"
                            class="p-1.5 text-gray-400 hover:text-white transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>

                        <span class="px-3 py-1.5 text-xs font-medium text-white min-w-[140px] text-center capitalize">
                            {{ navigationLabel }}
                        </span>

                        <button
                            @click="goToNext"
                            class="p-1.5 text-gray-400 hover:text-white transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
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
                        :class="generationStep === 'queued'
                            ? 'bg-purple-500/20 ring-2 ring-purple-500/50'
                            : 'bg-emerald-500/20'"
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
                        :class="generationStep === 'calling_ai'
                            ? 'bg-purple-500/20 ring-2 ring-purple-500/50'
                            : ['creating_slots', 'done'].includes(generationStep)
                                ? 'bg-emerald-500/20'
                                : 'bg-gray-800'"
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
                        :class="generationStep === 'creating_slots'
                            ? 'bg-purple-500/20 ring-2 ring-purple-500/50'
                            : generationStep === 'done'
                                ? 'bg-emerald-500/20'
                                : 'bg-gray-800'"
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

        <!-- Empty state -->
        <div
            v-else-if="!hasAnySlots"
            class="rounded-xl bg-gray-900 border border-gray-800 p-8 sm:p-12 flex flex-col items-center justify-center text-center"
        >
            <div class="w-16 h-16 rounded-full bg-purple-500/10 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.calendar.emptyPlanTitle') }}</h3>
            <p class="text-sm text-gray-400 max-w-md mb-6">{{ t('manager.calendar.emptyPlanDescription') }}</p>

            <!-- Generate plan options -->
            <div class="w-full max-w-sm space-y-3 mb-4">
                <button
                    @click="handleGeneratePlan('full')"
                    :disabled="planGenerating || !managerStore.strategyIsActive"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-lg bg-purple-600/10 border border-purple-500/30 text-left hover:bg-purple-600/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <div class="w-9 h-9 rounded-lg bg-purple-500/20 flex items-center justify-center shrink-0">
                        <svg v-if="planGenerating" class="w-4 h-4 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <svg v-else class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
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
                        <svg v-if="planGenerating" class="w-4 h-4 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <svg v-else class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
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
                @click="handleDayClick(new Date().toISOString().split('T')[0])"
                class="inline-flex items-center justify-center gap-2 px-5 py-2 text-sm font-medium text-gray-400 hover:text-white transition-colors"
            >
                {{ t('manager.calendar.addManually') }}
            </button>

            <p
                v-if="!managerStore.strategyIsActive"
                class="text-xs text-amber-400 mt-3"
            >
                {{ t('manager.calendar.noActiveStrategy') }}
            </p>
        </div>

        <!-- Calendar -->
        <div v-else>
            <!-- ==================== MONTH VIEW ==================== -->
            <div v-if="currentView === 'month'" class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden">
                <!-- Weekday header row -->
                <div class="grid grid-cols-7 border-b border-gray-800">
                    <div
                        v-for="key in weekdayKeys"
                        :key="key"
                        class="px-2 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider"
                    >
                        {{ t(`manager.calendar.weekdays.${key}`) }}
                    </div>
                </div>

                <!-- Day cells grid -->
                <div class="grid grid-cols-7">
                    <div
                        v-for="(day, index) in calendarDays"
                        :key="day.dateStr"
                        @click="handleDayClick(day.dateStr)"
                        class="min-h-[90px] sm:min-h-[110px] lg:min-h-[130px] border-b border-r border-gray-800 p-1.5 sm:p-2 transition-colors hover:bg-gray-800/30 cursor-pointer"
                        :class="{
                            'border-r-0': (index + 1) % 7 === 0,
                            'border-b-0': index >= 35,
                            'ring-1 ring-inset ring-indigo-500/50 bg-indigo-500/5': day.isToday,
                        }"
                    >
                        <!-- Day number -->
                        <div class="flex items-center justify-between mb-1">
                            <span
                                class="text-xs font-medium leading-none"
                                :class="{
                                    'text-white': day.isCurrentMonth && !day.isToday,
                                    'text-gray-600': !day.isCurrentMonth,
                                    'text-indigo-400 font-bold': day.isToday,
                                }"
                            >
                                {{ day.day }}
                            </span>
                        </div>

                        <!-- Slot items -->
                        <div class="space-y-0.5">
                            <div
                                v-for="slot in getSlotsForDate(day.dateStr).slice(0, 3)"
                                :key="slot.id"
                                @click.stop="handleSlotClick(slot)"
                                class="flex items-center gap-1 px-1 py-0.5 rounded text-[10px] sm:text-xs leading-tight cursor-pointer hover:bg-gray-700/50 transition-colors group/slot"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full shrink-0"
                                    :class="getStatusColor(slot.status)"
                                ></span>
                                <span class="text-gray-500 shrink-0 hidden sm:inline">
                                    {{ formatTime(slot.scheduled_time) }}
                                </span>
                                <span class="text-gray-300 truncate">
                                    {{ slot.topic }}
                                </span>
                            </div>
                            <!-- Overflow indicator -->
                            <div
                                v-if="getSlotsForDate(day.dateStr).length > 3"
                                class="text-[10px] text-gray-500 px-1"
                            >
                                +{{ getSlotsForDate(day.dateStr).length - 3 }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== WEEK VIEW ==================== -->
            <div v-else-if="currentView === 'week'" class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden">
                <!-- Desktop: 7-column layout -->
                <div class="hidden sm:grid grid-cols-7">
                    <!-- Day columns -->
                    <div
                        v-for="(day, index) in weekDays"
                        :key="day.dateStr"
                        @click="handleDayClick(day.dateStr)"
                        class="border-r border-gray-800 last:border-r-0 min-h-[400px] flex flex-col cursor-pointer"
                        :class="{
                            'bg-indigo-500/5': day.isToday,
                        }"
                    >
                        <!-- Day header -->
                        <div
                            class="px-2 py-3 border-b border-gray-800 text-center"
                            :class="{ 'border-b-indigo-500/30': day.isToday }"
                        >
                            <div
                                class="text-[10px] font-semibold uppercase tracking-wider mb-1"
                                :class="day.isToday ? 'text-indigo-400' : 'text-gray-500'"
                            >
                                {{ t(`manager.calendar.weekdays.${weekdayKeys[index]}`) }}
                            </div>
                            <div
                                class="text-lg font-bold"
                                :class="{
                                    'text-indigo-400': day.isToday,
                                    'text-white': !day.isToday && day.isCurrentMonth,
                                    'text-gray-600': !day.isToday && !day.isCurrentMonth,
                                }"
                            >
                                {{ day.day }}
                            </div>
                        </div>

                        <!-- Slots list -->
                        <div class="flex-1 p-1.5 space-y-1.5 overflow-y-auto">
                            <div
                                v-for="slot in getSlotsForDate(day.dateStr)"
                                :key="slot.id"
                                @click.stop="handleSlotClick(slot)"
                                class="rounded-lg bg-gray-800/60 border border-gray-700/50 p-2 cursor-pointer hover:bg-gray-800 hover:border-gray-600 transition-colors"
                            >
                                <!-- Platform badge + status dot -->
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span
                                        class="w-2 h-2 rounded-full shrink-0"
                                        :class="getStatusColor(slot.status)"
                                    ></span>
                                    <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">
                                        {{ slot.platform }}
                                    </span>
                                </div>
                                <!-- Topic -->
                                <p class="text-xs text-gray-200 leading-snug line-clamp-2 mb-1">
                                    {{ slot.topic }}
                                </p>
                                <!-- Time -->
                                <div v-if="slot.scheduled_time" class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px] text-gray-500">
                                        {{ formatTime(slot.scheduled_time) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Empty day state -->
                            <div
                                v-if="getSlotsForDate(day.dateStr).length === 0"
                                class="flex flex-col items-center justify-center py-8 text-center"
                            >
                                <p class="text-[10px] text-gray-600">{{ t('manager.calendar.emptyDay') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile: stacked layout -->
                <div class="sm:hidden divide-y divide-gray-800">
                    <div
                        v-for="(day, index) in weekDays"
                        :key="day.dateStr"
                        @click="handleDayClick(day.dateStr)"
                        class="p-3 cursor-pointer"
                        :class="{ 'bg-indigo-500/5': day.isToday }"
                    >
                        <!-- Day header -->
                        <div class="flex items-center gap-2 mb-2">
                            <span
                                class="text-xs font-semibold uppercase tracking-wider"
                                :class="day.isToday ? 'text-indigo-400' : 'text-gray-500'"
                            >
                                {{ t(`manager.calendar.weekdays.${weekdayKeys[index]}`) }}
                            </span>
                            <span
                                class="text-sm font-bold"
                                :class="day.isToday ? 'text-indigo-400' : 'text-white'"
                            >
                                {{ day.day }}
                            </span>
                            <span
                                v-if="getSlotsForDate(day.dateStr).length > 0"
                                class="ml-auto text-[10px] font-medium text-gray-500 bg-gray-800 rounded-full px-2 py-0.5"
                            >
                                {{ getSlotsForDate(day.dateStr).length }}
                            </span>
                        </div>

                        <!-- Slots -->
                        <div class="space-y-1.5 pl-2">
                            <div
                                v-for="slot in getSlotsForDate(day.dateStr)"
                                :key="slot.id"
                                @click.stop="handleSlotClick(slot)"
                                class="flex items-center gap-2 rounded-lg bg-gray-800/60 border border-gray-700/50 p-2 cursor-pointer hover:bg-gray-800 transition-colors"
                            >
                                <span
                                    class="w-2 h-2 rounded-full shrink-0"
                                    :class="getStatusColor(slot.status)"
                                ></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-200 truncate">{{ slot.topic }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] text-gray-500 uppercase">{{ slot.platform }}</span>
                                        <span v-if="slot.scheduled_time" class="text-[10px] text-gray-500">
                                            {{ formatTime(slot.scheduled_time) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-if="getSlotsForDate(day.dateStr).length === 0"
                                class="text-[10px] text-gray-600 py-1"
                            >
                                {{ t('manager.calendar.emptyDay') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== LIST VIEW ==================== -->
            <PlanSlotsTable
                v-else
                :slots="slots"
                :plan-id="managerStore.currentPlan?.id"
                :pillars="pillars"
                @updated="handleSlotUpdated"
            />
        </div>

        <!-- Slot Details Modal -->
        <SlotDetailsModal
            :show="showSlotModal"
            :slot="selectedSlot"
            :plan-id="managerStore.currentPlan?.id"
            @close="showSlotModal = false"
            @updated="handleSlotUpdated"
        />

        <!-- Add Slot Modal -->
        <AddSlotModal
            :show="showAddSlotModal"
            :date-str="addSlotDate"
            :plan-id="managerStore.currentPlan?.id"
            @close="showAddSlotModal = false"
            @created="handleSlotCreated"
        />
    </div>
</template>
