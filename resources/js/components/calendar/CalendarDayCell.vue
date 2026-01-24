<script setup>
import { computed } from 'vue';
import { useCalendarStore } from '@/stores/calendar';
import CalendarPostCard from './CalendarPostCard.vue';

const props = defineProps({
    day: {
        type: Object,
        required: true,
    },
    posts: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['edit', 'create']);

const calendarStore = useCalendarStore();

const isSelected = computed(() => calendarStore.selectedDate === props.day.date);
const isDragTarget = computed(() => calendarStore.draggedPost !== null);
const isWeekView = computed(() => calendarStore.view === 'week');

const handleClick = () => {
    calendarStore.selectDate(props.day.date);
};

const handleDoubleClick = () => {
    emit('create');
};
</script>

<template>
    <div
        class="p-2 transition-colors cursor-pointer group"
        :class="{
            'min-h-[120px]': !isWeekView,
            'min-h-[400px]': isWeekView,
            'bg-gray-50': !day.isCurrentMonth,
            'bg-white': day.isCurrentMonth && !isSelected,
            'bg-blue-50': isSelected,
            'hover:bg-gray-100': day.isCurrentMonth && !isSelected,
            'hover:bg-blue-100': isSelected,
        }"
        @click="handleClick"
        @dblclick="handleDoubleClick"
    >
        <!-- Day header -->
        <div class="flex items-center justify-between mb-2">
            <span
                class="inline-flex items-center justify-center w-7 h-7 text-sm font-medium rounded-full"
                :class="{
                    'text-gray-400': !day.isCurrentMonth,
                    'text-gray-700': day.isCurrentMonth && !day.isToday,
                    'bg-blue-600 text-white': day.isToday,
                }"
            >
                {{ day.day }}
            </span>

            <!-- Add button (visible on hover) -->
            <button
                v-if="day.isCurrentMonth"
                @click.stop="emit('create')"
                class="w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-100 opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        </div>

        <!-- Posts -->
        <div class="space-y-1">
            <CalendarPostCard
                v-for="post in posts.slice(0, 3)"
                :key="post.id"
                :post="post"
                @click.stop="emit('edit', post)"
            />

            <!-- More indicator -->
            <div
                v-if="posts.length > 3"
                class="text-xs text-gray-500 text-center py-1 hover:text-blue-600 cursor-pointer"
                @click.stop="handleClick"
            >
                +{{ posts.length - 3 }} more
            </div>
        </div>
    </div>
</template>
