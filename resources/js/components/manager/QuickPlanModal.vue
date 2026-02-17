<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    planId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'created']);

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const platforms = [
    { key: 'instagram', label: 'Instagram', color: 'from-purple-500 to-pink-500' },
    { key: 'facebook', label: 'Facebook', color: 'from-blue-600 to-blue-500' },
    { key: 'tiktok', label: 'TikTok', color: 'from-gray-200 to-gray-100' },
    { key: 'linkedin', label: 'LinkedIn', color: 'from-sky-700 to-sky-600' },
    { key: 'x', label: 'X', color: 'from-gray-500 to-gray-400' },
    { key: 'youtube', label: 'YouTube', color: 'from-red-600 to-red-500' },
];

const contentTypes = ['post', 'carousel', 'video', 'reel', 'story', 'article', 'thread', 'poll'];

const rangePresets = [
    { key: 'next_week', days: 7 },
    { key: 'next_2_weeks', days: 14 },
    { key: 'next_month', days: 30 },
    { key: 'custom', days: 0 },
];

// --- Form state ---
const selectedPlatforms = ref([]);
const postsPerDay = ref(1);
const contentType = ref('post');
const rangePreset = ref('next_week');
const customFrom = ref('');
const customTo = ref('');
const submitting = ref(false);
const progress = ref(0);
const totalToCreate = ref(0);

// --- Computed ---
const dateRange = computed(() => {
    if (rangePreset.value === 'custom') {
        if (!customFrom.value || !customTo.value) return { from: null, to: null };
        return { from: customFrom.value, to: customTo.value };
    }

    const preset = rangePresets.find(p => p.key === rangePreset.value);
    const from = new Date();
    from.setDate(from.getDate() + 1); // start from tomorrow
    const to = new Date(from);
    to.setDate(to.getDate() + (preset?.days || 7) - 1);

    return {
        from: from.toISOString().split('T')[0],
        to: to.toISOString().split('T')[0],
    };
});

const daysCount = computed(() => {
    const { from, to } = dateRange.value;
    if (!from || !to) return 0;
    const d1 = new Date(from + 'T00:00:00');
    const d2 = new Date(to + 'T00:00:00');
    return Math.max(0, Math.floor((d2 - d1) / (1000 * 60 * 60 * 24)) + 1);
});

const totalItems = computed(() => {
    return daysCount.value * selectedPlatforms.value.length * postsPerDay.value;
});

const canSubmit = computed(() => {
    return selectedPlatforms.value.length > 0
        && postsPerDay.value >= 1
        && daysCount.value > 0
        && props.planId
        && !submitting.value;
});

const pillars = computed(() => {
    const cp = managerStore.strategy?.content_pillars;
    if (!cp || !Array.isArray(cp)) return [];
    return cp.map(p => typeof p === 'string' ? p : (p.name || p.label || ''));
});

// --- Methods ---
const togglePlatform = (key) => {
    const idx = selectedPlatforms.value.indexOf(key);
    if (idx >= 0) {
        selectedPlatforms.value.splice(idx, 1);
    } else {
        selectedPlatforms.value.push(key);
    }
};

const defaultTimeSlots = (count) => {
    // Distribute posts evenly through the day (9:00 - 21:00)
    const startHour = 9;
    const endHour = 21;
    const gap = (endHour - startHour) / count;
    const times = [];
    for (let i = 0; i < count; i++) {
        const hour = Math.floor(startHour + gap * i);
        const minute = Math.round((startHour + gap * i - hour) * 60);
        times.push(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`);
    }
    return times;
};

const generateDates = () => {
    const { from, to } = dateRange.value;
    if (!from || !to) return [];
    const dates = [];
    const current = new Date(from + 'T00:00:00');
    const end = new Date(to + 'T00:00:00');
    while (current <= end) {
        dates.push(current.toISOString().split('T')[0]);
        current.setDate(current.getDate() + 1);
    }
    return dates;
};

const handleSubmit = async () => {
    if (!canSubmit.value) return;

    submitting.value = true;
    progress.value = 0;
    totalToCreate.value = totalItems.value;

    const dates = generateDates();
    const timeSlots = defaultTimeSlots(postsPerDay.value * selectedPlatforms.value.length);
    let created = 0;
    let failed = 0;

    try {
        for (const date of dates) {
            let timeIdx = 0;
            for (const platform of selectedPlatforms.value) {
                for (let i = 0; i < postsPerDay.value; i++) {
                    const pillar = pillars.value.length > 0
                        ? pillars.value[created % pillars.value.length]
                        : null;

                    try {
                        await managerStore.addPlanSlot(props.planId, {
                            scheduled_date: date,
                            scheduled_time: timeSlots[timeIdx % timeSlots.length] + ':00',
                            platform,
                            content_type: contentType.value,
                            topic: null,
                            description: null,
                            pillar,
                        });
                        created++;
                    } catch {
                        failed++;
                    }
                    timeIdx++;
                    progress.value = created + failed;
                }
            }
        }

        if (created > 0) {
            toast.success(t('manager.quickPlan.successMessage', { count: created }));
            emit('created');
            emit('close');
        }
        if (failed > 0) {
            toast.error(t('manager.quickPlan.partialError', { failed }));
        }
    } catch {
        toast.error(t('common.error'));
    } finally {
        submitting.value = false;
        progress.value = 0;
    }
};

const resetForm = () => {
    selectedPlatforms.value = [];
    postsPerDay.value = 1;
    contentType.value = 'post';
    rangePreset.value = 'next_week';
    customFrom.value = '';
    customTo.value = '';
    submitting.value = false;
    progress.value = 0;
};

const close = () => {
    if (submitting.value) return;
    emit('close');
};

watch(() => props.show, (show) => {
    if (show) resetForm();
});
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="bg-gray-900 -m-6 rounded-xl">
            <!-- Header -->
            <div class="p-5 border-b border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ t('manager.quickPlan.title') }}</h3>
                        <p class="text-sm text-gray-400 mt-0.5">{{ t('manager.quickPlan.subtitle') }}</p>
                    </div>
                    <button
                        @click="close"
                        :disabled="submitting"
                        class="shrink-0 p-1.5 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition-colors disabled:opacity-30"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-5 max-h-[70vh] overflow-y-auto">
                <!-- 1. Platforms -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.quickPlan.selectPlatforms') }}
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <button
                            v-for="p in platforms"
                            :key="p.key"
                            @click="togglePlatform(p.key)"
                            :disabled="submitting"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border transition-all text-left"
                            :class="selectedPlatforms.includes(p.key)
                                ? 'bg-indigo-600/15 border-indigo-500/50 ring-1 ring-indigo-500/30'
                                : 'bg-gray-800/50 border-gray-700 hover:border-gray-600'"
                        >
                            <!-- Platform icons -->
                            <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0"
                                :class="selectedPlatforms.includes(p.key) ? `bg-gradient-to-br ${p.color}` : 'bg-gray-700'"
                            >
                                <svg v-if="p.key === 'instagram'" class="w-3.5 h-3.5" :class="selectedPlatforms.includes(p.key) ? 'text-white' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                                </svg>
                                <svg v-else-if="p.key === 'facebook'" class="w-3.5 h-3.5" :class="selectedPlatforms.includes(p.key) ? 'text-white' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                <svg v-else-if="p.key === 'tiktok'" class="w-3.5 h-3.5" :class="selectedPlatforms.includes(p.key) ? 'text-gray-900' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                                </svg>
                                <svg v-else-if="p.key === 'linkedin'" class="w-3.5 h-3.5" :class="selectedPlatforms.includes(p.key) ? 'text-white' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                </svg>
                                <svg v-else-if="p.key === 'x'" class="w-3.5 h-3.5" :class="selectedPlatforms.includes(p.key) ? 'text-gray-900' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                                <svg v-else-if="p.key === 'youtube'" class="w-3.5 h-3.5" :class="selectedPlatforms.includes(p.key) ? 'text-white' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="text-sm font-medium" :class="selectedPlatforms.includes(p.key) ? 'text-white' : 'text-gray-400'">
                                    {{ p.label }}
                                </span>
                            </div>
                            <!-- Checkmark -->
                            <svg
                                v-if="selectedPlatforms.includes(p.key)"
                                class="w-4 h-4 text-indigo-400 ml-auto shrink-0"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 2. Date range -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.quickPlan.dateRange') }}
                    </label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button
                            v-for="preset in rangePresets"
                            :key="preset.key"
                            @click="rangePreset = preset.key"
                            :disabled="submitting"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                            :class="rangePreset === preset.key
                                ? 'bg-indigo-600 border-indigo-500 text-white'
                                : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                        >
                            {{ t(`manager.quickPlan.range.${preset.key}`) }}
                        </button>
                    </div>
                    <!-- Custom range inputs -->
                    <div v-if="rangePreset === 'custom'" class="flex items-center gap-3">
                        <input
                            v-model="customFrom"
                            type="date"
                            class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                        />
                        <span class="text-gray-500 text-sm">—</span>
                        <input
                            v-model="customTo"
                            type="date"
                            class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                        />
                    </div>
                    <p v-if="daysCount > 0" class="text-xs text-gray-500 mt-2">
                        {{ t('manager.quickPlan.daysSelected', { count: daysCount }) }}
                    </p>
                </div>

                <!-- 3. Posts per day per platform -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.quickPlan.postsPerDay') }}
                    </label>
                    <div class="flex items-center gap-3">
                        <button
                            v-for="n in [1, 2, 3, 4]"
                            :key="n"
                            @click="postsPerDay = n"
                            :disabled="submitting"
                            class="w-10 h-10 rounded-lg border text-sm font-semibold transition-colors"
                            :class="postsPerDay === n
                                ? 'bg-indigo-600 border-indigo-500 text-white'
                                : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                        >
                            {{ n }}
                        </button>
                        <span class="text-xs text-gray-500">{{ t('manager.quickPlan.perPlatform') }}</span>
                    </div>
                </div>

                <!-- 4. Content type -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.quickPlan.contentType') }}
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="ct in contentTypes"
                            :key="ct"
                            @click="contentType = ct"
                            :disabled="submitting"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors capitalize"
                            :class="contentType === ct
                                ? 'bg-indigo-600 border-indigo-500 text-white'
                                : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                        >
                            {{ ct }}
                        </button>
                    </div>
                </div>

                <!-- Summary -->
                <div
                    v-if="selectedPlatforms.length > 0 && daysCount > 0"
                    class="rounded-lg bg-indigo-600/10 border border-indigo-500/20 px-4 py-3"
                >
                    <p class="text-sm text-indigo-300">
                        {{ t('manager.quickPlan.summary', {
                            items: totalItems,
                            days: daysCount,
                            platforms: selectedPlatforms.length,
                            perDay: postsPerDay,
                        }) }}
                    </p>
                </div>

                <!-- Progress bar during creation -->
                <div v-if="submitting" class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-400">{{ t('manager.quickPlan.creating') }}</span>
                        <span class="text-white font-medium">{{ progress }}/{{ totalToCreate }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-800 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-indigo-500 transition-all duration-300"
                            :style="{ width: totalToCreate > 0 ? (progress / totalToCreate * 100) + '%' : '0%' }"
                        />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-5 border-t border-gray-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3">
                <button
                    @click="close"
                    :disabled="submitting"
                    class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:text-gray-200 hover:bg-gray-800 transition-colors disabled:opacity-50 order-2 sm:order-1"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    @click="handleSubmit"
                    :disabled="!canSubmit"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2"
                >
                    <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ submitting
                        ? t('manager.quickPlan.creating')
                        : t('manager.quickPlan.createItems', { count: totalItems })
                    }}
                </button>
            </div>
        </div>
    </Modal>
</template>
