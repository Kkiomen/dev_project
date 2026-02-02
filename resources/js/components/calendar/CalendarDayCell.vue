<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCalendarStore } from '@/stores/calendar';
import CalendarPostCard from './CalendarPostCard.vue';
import CalendarEventCard from './CalendarEventCard.vue';

const props = defineProps({
    day: {
        type: Object,
        required: true,
    },
    posts: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['edit', 'edit-event', 'create', 'create-event']);

const { t } = useI18n();
const calendarStore = useCalendarStore();

const isSelected = computed(() => calendarStore.selectedDate === props.day.date);
const isDragTarget = computed(() => calendarStore.draggedPost !== null);
const isWeekView = computed(() => calendarStore.view === 'week');

// Combine and sort items by time
const sortedItems = computed(() => {
    const items = [];

    // Add posts with type marker
    props.posts.forEach(post => {
        items.push({
            ...post,
            type: 'post',
            sortTime: post.scheduled_time || '23:59',
        });
    });

    // Add events with type marker
    props.events.forEach(event => {
        items.push({
            ...event,
            type: 'event',
            sortTime: event.all_day ? '00:00' : (event.scheduled_time || '23:59'),
        });
    });

    // Sort by time (all-day events first, then by time)
    return items.sort((a, b) => {
        // All-day events come first
        if (a.type === 'event' && a.all_day && !(b.type === 'event' && b.all_day)) return -1;
        if (b.type === 'event' && b.all_day && !(a.type === 'event' && a.all_day)) return 1;
        // Then sort by time
        return a.sortTime.localeCompare(b.sortTime);
    });
});

const totalItems = computed(() => props.posts.length + props.events.length);
const visibleItems = computed(() => sortedItems.value.slice(0, 3));
const hiddenCount = computed(() => Math.max(0, totalItems.value - 3));

// Prevent click handler from interfering with post/event clicks
const clickedOnItem = ref(false);

const handleClick = () => {
    // Don't select date if we just clicked on a post/event
    if (clickedOnItem.value) {
        clickedOnItem.value = false;
        return;
    }
    calendarStore.selectDate(props.day.date);
};

const handleDoubleClick = () => {
    // Don't create if we're clicking on a post/event
    if (clickedOnItem.value) {
        clickedOnItem.value = false;
        return;
    }
    emit('create');
};

const handlePostClick = (post) => {
    clickedOnItem.value = true;
    emit('edit', post);
};

const handleEventClick = (event) => {
    clickedOnItem.value = true;
    emit('edit-event', event);
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

            <!-- Add buttons (visible on hover) -->
            <div v-if="day.isCurrentMonth" class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                    @click.stop="emit('create-event')"
                    class="w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:text-purple-600 hover:bg-purple-100"
                    :title="t('calendarEvents.addEvent')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </button>
                <button
                    @click.stop="emit('create')"
                    class="w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-100"
                    :title="t('posts.create')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Items (posts and events) -->
        <div class="space-y-1">
            <template v-for="item in visibleItems" :key="`${item.type}-${item.id}`">
                <CalendarEventCard
                    v-if="item.type === 'event'"
                    :event="item"
                    @click="handleEventClick(item)"
                />
                <CalendarPostCard
                    v-else
                    :post="item"
                    @click="handlePostClick(item)"
                />
            </template>

            <!-- More indicator -->
            <div
                v-if="hiddenCount > 0"
                class="text-xs text-gray-500 text-center py-1 hover:text-blue-600 cursor-pointer"
                @click.stop="handleClick"
            >
                +{{ hiddenCount }} {{ t('common.more') }}
            </div>
        </div>
    </div>
</template>
