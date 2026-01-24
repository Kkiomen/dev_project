<script setup>
import { ref } from 'vue';
import { useCalendarStore } from '@/stores/calendar';

const props = defineProps({
    post: {
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

const handleDragStart = (event) => {
    isDragging.value = true;
    event.dataTransfer.setData('text/plain', props.post.id);
    calendarStore.startDragging(props.post);
};

const handleDragEnd = () => {
    calendarStore.stopDragging();
    // Reset dragging state after a small delay to allow click to be prevented
    setTimeout(() => {
        isDragging.value = false;
    }, 100);
};

const handleClick = (event) => {
    // Don't emit click if we just finished dragging
    if (isDragging.value) {
        return;
    }

    // Check if mouse moved significantly (drag vs click)
    if (event?.clientX !== undefined) {
        const dx = Math.abs(event.clientX - dragStartPos.value.x);
        const dy = Math.abs(event.clientY - dragStartPos.value.y);
        if (dx > 5 || dy > 5) {
            return;
        }
    }

    emit('click');
};

const statusColors = {
    draft: 'bg-gray-100 border-gray-300',
    pending_approval: 'bg-yellow-100 border-yellow-300',
    approved: 'bg-blue-100 border-blue-300',
    scheduled: 'bg-indigo-100 border-indigo-300',
    published: 'bg-green-100 border-green-300',
    failed: 'bg-red-100 border-red-300',
};
</script>

<template>
    <div
        class="flex items-center space-x-2 px-2 py-1 rounded border cursor-pointer hover:shadow-sm transition-shadow select-none"
        :class="statusColors[post.status] || 'bg-gray-100 border-gray-300'"
        draggable="true"
        @mousedown="handleMouseDown"
        @click.stop="handleClick"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
    >
        <!-- Thumbnail -->
        <div
            v-if="post.first_media_url"
            class="w-6 h-6 rounded overflow-hidden flex-shrink-0"
        >
            <img
                :src="post.first_media_url"
                :alt="post.title"
                class="w-full h-full object-cover"
            />
        </div>

        <!-- Title -->
        <span class="text-xs font-medium truncate flex-1">
            {{ post.title }}
        </span>

        <!-- Time -->
        <span v-if="post.scheduled_time" class="text-xs text-gray-500 flex-shrink-0">
            {{ post.scheduled_time }}
        </span>

        <!-- Platform indicators -->
        <div class="flex -space-x-1">
            <span
                v-for="platform in post.enabled_platforms?.slice(0, 2)"
                :key="platform"
                class="w-4 h-4 rounded-full flex items-center justify-center text-white text-[8px]"
                :class="{
                    'bg-blue-600': platform === 'facebook',
                    'bg-pink-500': platform === 'instagram',
                    'bg-red-600': platform === 'youtube',
                }"
            >
                {{ platform[0].toUpperCase() }}
            </span>
        </div>
    </div>
</template>
