<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProposalsStore } from '@/stores/proposals';
import { usePostsStore } from '@/stores/posts';
import { useBrandsStore } from '@/stores/brands';

const emit = defineEmits(['add-proposal', 'edit-proposal']);
const { t } = useI18n();

const proposalsStore = useProposalsStore();
const postsStore = usePostsStore();
const brandsStore = useBrandsStore();

const currentDate = ref(new Date());
const loading = ref(false);

const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth());

const monthLabel = computed(() => {
    return currentDate.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
});

const dayNames = computed(() => [
    t('postAutomation.proposals.calendar.mon'),
    t('postAutomation.proposals.calendar.tue'),
    t('postAutomation.proposals.calendar.wed'),
    t('postAutomation.proposals.calendar.thu'),
    t('postAutomation.proposals.calendar.fri'),
    t('postAutomation.proposals.calendar.sat'),
    t('postAutomation.proposals.calendar.sun'),
]);

const calendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    // Monday-based: 0=Mon, 6=Sun
    let startDow = firstDay.getDay() - 1;
    if (startDow < 0) startDow = 6;

    const days = [];

    // Previous month padding
    for (let i = startDow - 1; i >= 0; i--) {
        const d = new Date(year, month, -i);
        days.push({ date: d, isCurrentMonth: false, dateStr: formatDateStr(d) });
    }

    // Current month
    for (let i = 1; i <= lastDay.getDate(); i++) {
        const d = new Date(year, month, i);
        days.push({ date: d, isCurrentMonth: true, dateStr: formatDateStr(d) });
    }

    // Next month padding (fill to complete the grid)
    const remainder = days.length % 7;
    if (remainder > 0) {
        const daysToAdd = 7 - remainder;
        for (let i = 1; i <= daysToAdd; i++) {
            const d = new Date(year, month + 1, i);
            days.push({ date: d, isCurrentMonth: false, dateStr: formatDateStr(d) });
        }
    }

    return days;
});

function formatDateStr(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${dd}`;
}

function isToday(dateStr) {
    return dateStr === formatDateStr(new Date());
}

function getProposalsForDate(dateStr) {
    return proposalsStore.calendarProposals[dateStr] || [];
}

function getPostsForDate(dateStr) {
    return postsStore.calendarPosts[dateStr] || [];
}

function prevMonth() {
    const d = new Date(currentDate.value);
    d.setMonth(d.getMonth() - 1);
    currentDate.value = d;
}

function nextMonth() {
    const d = new Date(currentDate.value);
    d.setMonth(d.getMonth() + 1);
    currentDate.value = d;
}

function goToToday() {
    currentDate.value = new Date();
}

function onDayClick(day) {
    if (!day.isCurrentMonth) return;
    emit('add-proposal', day.dateStr);
}

function onProposalClick(e, proposal) {
    e.stopPropagation();
    emit('edit-proposal', proposal);
}

async function fetchCalendarData() {
    loading.value = true;
    try {
        const year = currentYear.value;
        const month = currentMonth.value;

        // Get first and last visible dates
        const firstVisible = calendarDays.value[0]?.dateStr;
        const lastVisible = calendarDays.value[calendarDays.value.length - 1]?.dateStr;

        if (!firstVisible || !lastVisible) return;

        const brandId = brandsStore.currentBrand?.id || null;

        await Promise.all([
            proposalsStore.fetchCalendarProposals(firstVisible, lastVisible, brandId),
            postsStore.fetchCalendarPosts(firstVisible, lastVisible),
        ]);
    } catch {
        // Silent fail for calendar data
    } finally {
        loading.value = false;
    }
}

watch(currentDate, () => fetchCalendarData());
watch(() => brandsStore.currentBrand?.id, () => fetchCalendarData());

onMounted(() => {
    fetchCalendarData();
});

function postStatusColor(status) {
    const colors = {
        draft: 'bg-gray-300',
        pending_approval: 'bg-yellow-400',
        approved: 'bg-blue-400',
        scheduled: 'bg-indigo-400',
        published: 'bg-green-400',
        failed: 'bg-red-400',
    };
    return colors[status] || 'bg-gray-300';
}
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <button
                    @click="prevMonth"
                    class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-semibold text-gray-900 min-w-[160px] text-center capitalize">
                    {{ monthLabel }}
                </h3>
                <button
                    @click="nextMonth"
                    class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <button
                    @click="goToToday"
                    class="ml-2 px-2.5 py-1 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('postAutomation.proposals.calendar.today') }}
                </button>
            </div>

            <!-- Legend -->
            <div class="hidden sm:flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    {{ t('postAutomation.proposals.calendar.proposals') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                    {{ t('postAutomation.proposals.calendar.posts') }}
                </span>
            </div>
        </div>

        <!-- Day names -->
        <div class="grid grid-cols-7 border-b border-gray-200">
            <div
                v-for="name in dayNames"
                :key="name"
                class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase"
            >
                {{ name }}
            </div>
        </div>

        <!-- Calendar grid -->
        <div class="grid grid-cols-7">
            <div
                v-for="(day, idx) in calendarDays"
                :key="idx"
                :class="[
                    'min-h-[80px] sm:min-h-[100px] p-1.5 border-b border-r border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors',
                    !day.isCurrentMonth && 'bg-gray-50/50',
                    isToday(day.dateStr) && 'bg-blue-50/50',
                ]"
                @click="onDayClick(day)"
            >
                <!-- Day number -->
                <div class="flex items-center justify-between mb-1">
                    <span
                        :class="[
                            'text-xs font-medium',
                            isToday(day.dateStr)
                                ? 'text-blue-600 bg-blue-100 rounded-full w-6 h-6 flex items-center justify-center'
                                : day.isCurrentMonth
                                    ? 'text-gray-900'
                                    : 'text-gray-400'
                        ]"
                    >
                        {{ day.date.getDate() }}
                    </span>
                </div>

                <!-- Proposals (amber pills) -->
                <div
                    v-for="proposal in getProposalsForDate(day.dateStr).slice(0, 2)"
                    :key="'p-' + proposal.id"
                    class="mb-0.5"
                >
                    <div
                        class="px-1.5 py-0.5 text-[10px] sm:text-xs rounded bg-amber-100 text-amber-800 truncate cursor-pointer hover:bg-amber-200"
                        @click="onProposalClick($event, proposal)"
                        :title="(proposal.scheduled_time ? proposal.scheduled_time + ' ' : '') + proposal.title"
                    >
                        <span v-if="proposal.scheduled_time" class="font-medium">{{ proposal.scheduled_time }}</span>{{ proposal.scheduled_time ? ' ' : '' }}{{ proposal.title }}
                    </div>
                </div>
                <div
                    v-if="getProposalsForDate(day.dateStr).length > 2"
                    class="text-[10px] text-amber-600 px-1"
                >
                    +{{ getProposalsForDate(day.dateStr).length - 2 }}
                </div>

                <!-- Posts (colored pills by status) -->
                <div
                    v-for="post in getPostsForDate(day.dateStr).slice(0, 2)"
                    :key="'s-' + post.id"
                    class="mb-0.5"
                >
                    <div class="flex items-center gap-1 px-1.5 py-0.5 text-[10px] sm:text-xs rounded bg-blue-50 text-blue-800 truncate">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="postStatusColor(post.status)"></span>
                        <span class="truncate">{{ post.title }}</span>
                    </div>
                </div>
                <div
                    v-if="getPostsForDate(day.dateStr).length > 2"
                    class="text-[10px] text-blue-600 px-1"
                >
                    +{{ getPostsForDate(day.dateStr).length - 2 }}
                </div>
            </div>
        </div>

        <!-- Mobile legend -->
        <div class="sm:hidden flex items-center justify-center gap-6 py-3 border-t border-gray-200 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                {{ t('postAutomation.proposals.calendar.proposals') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                {{ t('postAutomation.proposals.calendar.posts') }}
            </span>
        </div>
    </div>
</template>
