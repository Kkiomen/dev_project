<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/composables/useToast';
import { usePostsStore } from '@/stores/posts';
import { useCalendarStore } from '@/stores/calendar';
import { useCalendarEventsStore } from '@/stores/calendarEvents';
import { useBrandsStore } from '@/stores/brands';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import CalendarView from '@/components/calendar/CalendarView.vue';
import CalendarToolbar from '@/components/calendar/CalendarToolbar.vue';
import CalendarEventModal from '@/components/calendar/CalendarEventModal.vue';
import CalendarDayModal from '@/components/calendar/CalendarDayModal.vue';

const { t } = useI18n();
const toast = useToast();
const router = useRouter();
const postsStore = usePostsStore();
const calendarStore = useCalendarStore();
const calendarEventsStore = useCalendarEventsStore();
const brandsStore = useBrandsStore();

const loading = ref(true);
const showEventModal = ref(false);
const editingEvent = ref(null);
const showMobileMenu = ref(false);
const showDayModal = ref(false);
const dayModalData = ref({ date: null, posts: [], events: [] });

// Filtered posts based on calendar filters
const filteredPosts = computed(() => {
    const { itemType, status } = calendarStore.filters;

    // If showing only events, return empty object
    if (itemType === 'events') {
        return {};
    }

    const posts = postsStore.calendarPosts;

    // If no status filter, return all posts
    if (!status) {
        return posts;
    }

    // Filter posts by status
    const filtered = {};
    for (const [date, datePosts] of Object.entries(posts)) {
        const filteredDatePosts = datePosts.filter(post => post.status === status);
        if (filteredDatePosts.length > 0) {
            filtered[date] = filteredDatePosts;
        }
    }
    return filtered;
});

// Filtered events based on calendar filters
const filteredEvents = computed(() => {
    const { itemType } = calendarStore.filters;

    // If showing only posts, return empty object
    if (itemType === 'posts') {
        return {};
    }

    return calendarEventsStore.calendarEvents;
});

const fetchCalendarData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            postsStore.fetchCalendarPosts(
                calendarStore.monthStart,
                calendarStore.monthEnd
            ),
            calendarEventsStore.fetchCalendarEvents(
                calendarStore.monthStart,
                calendarStore.monthEnd
            ),
        ]);
    } catch (error) {
        console.error('Failed to fetch calendar data:', error);
    } finally {
        loading.value = false;
    }
};

// Subscribe to WebSocket events for real-time calendar updates
const subscribeToCalendarEvents = () => {
    const brandId = brandsStore.currentBrand?.id;
    if (brandId && window.Echo) {
        window.Echo.private(`brand.${brandId}`)
            .listen('.post.created', (e) => {
                postsStore.addCalendarPost(e.post);
                toast.success(t('calendar.newPostCreated'));
            });
    }
};

const unsubscribeFromCalendarEvents = () => {
    const brandId = brandsStore.currentBrand?.id;
    if (brandId && window.Echo) {
        window.Echo.leave(`brand.${brandId}`);
    }
};

onMounted(() => {
    fetchCalendarData();
    subscribeToCalendarEvents();
});

onUnmounted(() => {
    unsubscribeFromCalendarEvents();
});

watch(
    () => [calendarStore.monthStart, calendarStore.monthEnd],
    fetchCalendarData
);

const handleCreatePost = () => {
    showMobileMenu.value = false;
    const date = calendarStore.selectedDate || new Date().toISOString().split('T')[0];
    router.push({
        name: 'post.create',
        query: { date },
    });
};

const handleEditPost = (post) => {
    if (!post?.id) {
        console.error('Cannot edit post: missing post ID', post);
        return;
    }
    router.push({ name: 'post.edit', params: { postId: post.id } });
};

const handleReschedule = async (item, newDate) => {
    try {
        if (item.type === 'event') {
            // Reschedule event
            const startsAt = new Date(newDate);
            const originalTime = new Date(item.starts_at);
            startsAt.setHours(originalTime.getHours(), originalTime.getMinutes(), 0, 0);
            await calendarEventsStore.rescheduleEvent(item.id, startsAt.toISOString());
            toast.success(t('calendarEvents.messages.updated'));
        } else {
            // Reschedule post
            const scheduledAt = new Date(newDate);
            scheduledAt.setHours(12, 0, 0, 0);
            await postsStore.reschedulePost(item.id, scheduledAt.toISOString());
        }
        await fetchCalendarData();
    } catch (error) {
        console.error('Failed to reschedule:', error);
        toast.error(t('common.error'));
    }
};

// Event handlers
const handleCreateEvent = () => {
    showMobileMenu.value = false;
    editingEvent.value = null;
    showEventModal.value = true;
};

const handleEditEvent = (event) => {
    editingEvent.value = event;
    showEventModal.value = true;
};

const handleSaveEvent = async (data) => {
    try {
        if (editingEvent.value) {
            await calendarEventsStore.updateEvent(editingEvent.value.id, data);
            toast.success(t('calendarEvents.messages.updated'));
        } else {
            await calendarEventsStore.createEvent(data);
            toast.success(t('calendarEvents.messages.created'));
        }
        showEventModal.value = false;
        await fetchCalendarData();
    } catch (error) {
        console.error('Failed to save event:', error);
        toast.error(t('common.error'));
    }
};

const handleDeleteEvent = async (eventId) => {
    try {
        await calendarEventsStore.deleteEvent(eventId);
        toast.success(t('calendarEvents.messages.deleted'));
        showEventModal.value = false;
        await fetchCalendarData();
    } catch (error) {
        console.error('Failed to delete event:', error);
        toast.error(t('common.error'));
    }
};

// Day modal handlers (mobile)
const handleOpenDayModal = (data) => {
    // Apply filters to day modal data
    const { itemType, status } = calendarStore.filters;

    let posts = data.posts || [];
    let events = data.events || [];

    // Filter by item type
    if (itemType === 'events') {
        posts = [];
    } else if (itemType === 'posts') {
        events = [];
    }

    // Filter posts by status
    if (status && posts.length > 0) {
        posts = posts.filter(post => post.status === status);
    }

    dayModalData.value = {
        date: data.date,
        posts,
        events,
    };
    showDayModal.value = true;
};

const handleDayModalCreatePost = () => {
    showDayModal.value = false;
    handleCreatePost();
};

const handleDayModalCreateEvent = () => {
    showDayModal.value = false;
    handleCreateEvent();
};

const handleDayModalEditPost = (post) => {
    showDayModal.value = false;
    handleEditPost(post);
};

const handleDayModalEditEvent = (event) => {
    showDayModal.value = false;
    handleEditEvent(event);
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 sm:py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <RouterLink
                        :to="{ name: 'dashboard' }"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>
                    <h1 class="text-lg sm:text-xl font-semibold text-gray-900">
                        {{ t('calendar.title') }}
                    </h1>
                </div>

                <!-- Desktop buttons -->
                <div class="hidden md:flex items-center space-x-3">
                    <RouterLink :to="{ name: 'post.verify' }">
                        <Button variant="secondary" class="flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ t('calendar.verifyPosts') }}</span>
                        </Button>
                    </RouterLink>
                    <RouterLink :to="{ name: 'approval-tokens' }">
                        <Button variant="secondary">
                            {{ t('approval.tokens') }}
                        </Button>
                    </RouterLink>
                    <Button variant="secondary" @click="handleCreateEvent">
                        {{ t('calendarEvents.addEvent') }}
                    </Button>
                    <Button @click="handleCreatePost">
                        {{ t('posts.create') }}
                    </Button>
                </div>

                <!-- Mobile menu button -->
                <div class="flex md:hidden items-center space-x-2">
                    <Button size="sm" @click="handleCreatePost">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </Button>
                    <button
                        @click="showMobileMenu = !showMobileMenu"
                        class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile menu dropdown -->
            <div
                v-if="showMobileMenu"
                class="md:hidden mt-3 pt-3 border-t border-gray-200 space-y-2"
            >
                <button
                    @click="handleCreateEvent"
                    class="w-full flex items-center space-x-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ t('calendarEvents.addEvent') }}</span>
                </button>
                <RouterLink
                    :to="{ name: 'post.verify' }"
                    class="w-full flex items-center space-x-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg"
                    @click="showMobileMenu = false"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ t('calendar.verifyPosts') }}</span>
                </RouterLink>
                <RouterLink
                    :to="{ name: 'approval-tokens' }"
                    class="w-full flex items-center space-x-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg"
                    @click="showMobileMenu = false"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    <span>{{ t('approval.tokens') }}</span>
                </RouterLink>
            </div>
        </div>

        <!-- Toolbar -->
        <CalendarToolbar @create-event="handleCreateEvent" />

        <!-- Content -->
        <div class="p-2 sm:p-4 lg:p-6">
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>
            <CalendarView
                v-else
                :posts="filteredPosts"
                :events="filteredEvents"
                @edit="handleEditPost"
                @edit-event="handleEditEvent"
                @reschedule="handleReschedule"
                @create="handleCreatePost"
                @create-event="handleCreateEvent"
                @open-day-modal="handleOpenDayModal"
            />
        </div>

        <!-- Event Modal -->
        <CalendarEventModal
            :show="showEventModal"
            :event="editingEvent"
            :initial-date="calendarStore.selectedDate"
            @close="showEventModal = false"
            @save="handleSaveEvent"
            @delete="handleDeleteEvent"
        />

        <!-- Mobile Day Modal -->
        <CalendarDayModal
            :show="showDayModal"
            :date="dayModalData.date"
            :posts="dayModalData.posts"
            :events="dayModalData.events"
            @close="showDayModal = false"
            @create-post="handleDayModalCreatePost"
            @create-event="handleDayModalCreateEvent"
            @edit-post="handleDayModalEditPost"
            @edit-event="handleDayModalEditEvent"
        />

        <!-- Mobile menu backdrop -->
        <div
            v-if="showMobileMenu"
            class="fixed inset-0 z-40 md:hidden"
            @click="showMobileMenu = false"
        />
    </div>
</template>
