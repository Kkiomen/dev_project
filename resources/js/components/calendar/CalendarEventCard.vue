<script setup>
import { ref } from 'vue';
import { useCalendarStore } from '@/stores/calendar';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['click']);

const calendarStore = useCalendarStore();

// Track if we're dragging to prevent click from firing after drag
const isDragging = ref(false);
const dragStartPos = ref({ x: 0, y: 0 });

const handleMouseDown = (event) => {
    dragStartPos.value = { x: event.clientX, y: event.clientY };
    isDragging.value = false;
};

const handleDragStart = (e) => {
    isDragging.value = true;
    e.dataTransfer.setData('text/plain', `event:${props.event.id}`);
    e.dataTransfer.effectAllowed = 'move';
    calendarStore.startDragging({ ...props.event, type: 'event' });
};

const handleDragEnd = () => {
    calendarStore.stopDragging();
    // Reset dragging state after a small delay to allow click to be prevented
    setTimeout(() => {
        isDragging.value = false;
    }, 100);
};

const handleClick = (e) => {
    // Don't emit click if we just finished dragging
    if (isDragging.value) {
        return;
    }

    // Check if mouse moved significantly (drag vs click)
    if (e?.clientX !== undefined) {
        const dx = Math.abs(e.clientX - dragStartPos.value.x);
        const dy = Math.abs(e.clientY - dragStartPos.value.y);
        if (dx > 5 || dy > 5) {
            return;
        }
    }

    emit('click');
};

const eventTypeIcons = {
    meeting: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    birthday: 'M21 15.999h-5.5l-1.5-3-3 7-2.5-4H3',
    reminder: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    other: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
};
</script>

<template>
    <div
        class="flex items-center space-x-2 px-2 py-1 rounded cursor-pointer hover:shadow-sm transition-shadow select-none border-l-4"
        :style="{ borderLeftColor: event.color, backgroundColor: `${event.color}15` }"
        draggable="true"
        @mousedown="handleMouseDown"
        @click.stop="handleClick"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
    >
        <!-- Event type icon -->
        <div class="flex-shrink-0">
            <svg
                class="w-4 h-4"
                :style="{ color: event.color }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="eventTypeIcons[event.event_type] || eventTypeIcons.other"
                />
            </svg>
        </div>

        <!-- Title -->
        <span class="text-xs font-medium truncate flex-1 text-gray-800">
            {{ event.title }}
        </span>

        <!-- Time (if not all day) -->
        <span v-if="event.scheduled_time && !event.all_day" class="text-xs text-gray-500 flex-shrink-0">
            {{ event.scheduled_time }}
        </span>
    </div>
</template>
