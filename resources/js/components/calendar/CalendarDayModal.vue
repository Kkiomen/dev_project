<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    date: {
        type: String,
        default: null,
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

const emit = defineEmits(['close', 'create-post', 'create-event', 'edit-post', 'edit-event']);

const { t, locale } = useI18n();

// Format date for display
const formattedDate = computed(() => {
    if (!props.date) return '';
    const date = new Date(props.date);
    return date.toLocaleDateString(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
});

// Check if date is today
const isToday = computed(() => {
    if (!props.date) return false;
    const today = new Date().toISOString().split('T')[0];
    return props.date === today;
});

// Combine and organize items by hour
const itemsByHour = computed(() => {
    const hours = {};

    // Initialize all-day section
    hours['all-day'] = [];

    // Add events
    props.events.forEach(event => {
        if (event.all_day) {
            hours['all-day'].push({
                ...event,
                itemType: 'event',
            });
        } else {
            const hour = event.scheduled_time ? event.scheduled_time.substring(0, 2) : '00';
            if (!hours[hour]) hours[hour] = [];
            hours[hour].push({
                ...event,
                itemType: 'event',
            });
        }
    });

    // Add posts
    props.posts.forEach(post => {
        const hour = post.scheduled_time ? post.scheduled_time.substring(0, 2) : '12';
        if (!hours[hour]) hours[hour] = [];
        hours[hour].push({
            ...post,
            itemType: 'post',
        });
    });

    // Sort items within each hour by time
    Object.keys(hours).forEach(hour => {
        hours[hour].sort((a, b) => {
            const timeA = a.scheduled_time || '00:00';
            const timeB = b.scheduled_time || '00:00';
            return timeA.localeCompare(timeB);
        });
    });

    return hours;
});

// Get sorted hour keys
const sortedHours = computed(() => {
    const hourKeys = Object.keys(itemsByHour.value).filter(h => itemsByHour.value[h].length > 0);
    return hourKeys.sort((a, b) => {
        if (a === 'all-day') return -1;
        if (b === 'all-day') return 1;
        return a.localeCompare(b);
    });
});

// Check if there are any items
const hasItems = computed(() => {
    return props.posts.length > 0 || props.events.length > 0;
});

// Format hour for display
const formatHour = (hour) => {
    if (hour === 'all-day') return t('calendar.allDay');
    const h = parseInt(hour, 10);
    return `${h.toString().padStart(2, '0')}:00`;
};

// Get status color for posts
const getPostStatusColor = (status) => {
    const colors = {
        draft: 'bg-gray-400',
        pending_approval: 'bg-yellow-500',
        approved: 'bg-blue-500',
        scheduled: 'bg-indigo-500',
        published: 'bg-green-500',
    };
    return colors[status] || 'bg-gray-400';
};

// Get event type icon
const getEventIcon = (eventType) => {
    const icons = {
        meeting: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        birthday: 'M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z',
        reminder: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        other: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    };
    return icons[eventType] || icons.other;
};

const handleItemClick = (item) => {
    if (item.itemType === 'event') {
        emit('edit-event', item);
    } else {
        emit('edit-post', item);
    }
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 md:hidden"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="emit('close')"
                />

                <!-- Modal -->
                <div class="absolute inset-x-0 bottom-0 max-h-[85vh] bg-white rounded-t-2xl shadow-xl flex flex-col">
                    <!-- Handle bar -->
                    <div class="flex justify-center py-2">
                        <div class="w-10 h-1 bg-gray-300 rounded-full" />
                    </div>

                    <!-- Header -->
                    <div class="px-4 pb-3 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    {{ formattedDate }}
                                </h2>
                                <span
                                    v-if="isToday"
                                    class="text-xs text-blue-600 font-medium"
                                >
                                    {{ t('calendar.today') }}
                                </span>
                            </div>
                            <button
                                @click="emit('close')"
                                class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex gap-2 mt-3">
                            <Button
                                variant="secondary"
                                size="sm"
                                class="flex-1 justify-center"
                                @click="emit('create-event')"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                {{ t('calendarEvents.addEvent') }}
                            </Button>
                            <Button
                                size="sm"
                                class="flex-1 justify-center"
                                @click="emit('create-post')"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ t('posts.create') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Content - Timeline -->
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="hasItems" class="py-2">
                            <div
                                v-for="hour in sortedHours"
                                :key="hour"
                                class="px-4"
                            >
                                <!-- Hour header -->
                                <div class="flex items-center py-2">
                                    <span class="text-xs font-medium text-gray-500 w-14">
                                        {{ formatHour(hour) }}
                                    </span>
                                    <div class="flex-1 h-px bg-gray-200" />
                                </div>

                                <!-- Items for this hour -->
                                <div class="space-y-2 pl-14 pb-2">
                                    <button
                                        v-for="item in itemsByHour[hour]"
                                        :key="`${item.itemType}-${item.id}`"
                                        class="w-full text-left rounded-lg p-3 transition-colors"
                                        :class="item.itemType === 'event' ? 'bg-gray-50 hover:bg-gray-100' : 'bg-blue-50 hover:bg-blue-100'"
                                        @click="handleItemClick(item)"
                                    >
                                        <div class="flex items-start gap-3">
                                            <!-- Icon/Color indicator -->
                                            <div
                                                v-if="item.itemType === 'event'"
                                                class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                                :style="{ backgroundColor: item.color + '20' }"
                                            >
                                                <svg
                                                    class="w-4 h-4"
                                                    :style="{ color: item.color }"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        :d="getEventIcon(item.event_type)"
                                                    />
                                                </svg>
                                            </div>
                                            <div
                                                v-else
                                                class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                                :class="getPostStatusColor(item.status)"
                                            >
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </div>

                                            <!-- Content -->
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-gray-900 truncate">
                                                    {{ item.title || item.content?.substring(0, 50) || t('posts.noCaption') }}
                                                </p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span
                                                        v-if="item.scheduled_time && !item.all_day"
                                                        class="text-xs text-gray-500"
                                                    >
                                                        {{ item.scheduled_time }}
                                                    </span>
                                                    <span
                                                        v-if="item.itemType === 'event'"
                                                        class="text-xs px-1.5 py-0.5 rounded-full"
                                                        :style="{ backgroundColor: item.color + '20', color: item.color }"
                                                    >
                                                        {{ t(`calendarEvents.eventTypes.${item.event_type}`) }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="text-xs text-gray-500"
                                                    >
                                                        {{ t(`posts.status.${item.status}`) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Arrow -->
                                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="flex flex-col items-center justify-center py-12 px-4 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 text-sm">
                                {{ t('calendar.noItemsForDay') }}
                            </p>
                            <p class="text-gray-400 text-xs mt-1">
                                {{ t('calendar.clickButtonsAbove') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
