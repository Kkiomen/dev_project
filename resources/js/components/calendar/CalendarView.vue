<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCalendarStore } from '@/stores/calendar';
import CalendarDayCell from './CalendarDayCell.vue';

const props = defineProps({
    posts: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['edit', 'reschedule', 'create']);

const { t } = useI18n();
const calendarStore = useCalendarStore();

// Get week days in order based on user's week start setting
const weekDays = computed(() => {
    return calendarStore.orderedDayKeys.map(key => t(`calendar.days.${key}`));
});

// Split days into weeks for proper rendering
const weeks = computed(() => {
    const days = calendarStore.calendarDays;
    
    // For week view, return only one week
    if (calendarStore.view === 'week') {
        return [days];
    }
    
    // For month view, split into weeks
    const result = [];
    for (let i = 0; i < days.length; i += 7) {
        result.push(days.slice(i, i + 7));
    }
    return result;
});

const handleDrop = (date, event) => {
    const postId = event.dataTransfer.getData('text/plain');
    const post = Object.values(props.posts).flat().find(p => p.id === postId);
    if (post && post.scheduled_date !== date) {
        emit('reschedule', post, date);
    }
    calendarStore.stopDragging();
};

const handleDragOver = (event) => {
    event.preventDefault();
};
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <!-- Week day headers -->
        <div 
            class="grid bg-gray-50 border-b border-gray-200"
            style="grid-template-columns: repeat(7, minmax(0, 1fr));"
        >
            <div
                v-for="(day, index) in weekDays"
                :key="index"
                class="px-2 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider"
            >
                {{ day }}
            </div>
        </div>

        <!-- Calendar grid - render week by week -->
        <div class="bg-white">
            <div
                v-for="(week, weekIndex) in weeks"
                :key="weekIndex"
                class="grid border-b border-gray-200 last:border-b-0"
                style="grid-template-columns: repeat(7, minmax(0, 1fr));"
            >
                <CalendarDayCell
                    v-for="(day, dayIndex) in week"
                    :key="day.date"
                    :day="day"
                    :posts="posts[day.date] || []"
                    :class="{ 'border-r border-gray-200': dayIndex < 6 }"
                    @edit="(post) => emit('edit', post)"
                    @create="() => { calendarStore.selectDate(day.date); emit('create'); }"
                    @dragover="handleDragOver"
                    @drop="(event) => handleDrop(day.date, event)"
                />
            </div>
        </div>
    </div>
</template>
