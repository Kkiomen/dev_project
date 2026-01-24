<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/composables/useToast';
import { usePostsStore } from '@/stores/posts';
import { useCalendarStore } from '@/stores/calendar';
import { useBrandsStore } from '@/stores/brands';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import CalendarView from '@/components/calendar/CalendarView.vue';
import CalendarToolbar from '@/components/calendar/CalendarToolbar.vue';

const { t } = useI18n();
const toast = useToast();
const router = useRouter();
const postsStore = usePostsStore();
const calendarStore = useCalendarStore();
const brandsStore = useBrandsStore();

const loading = ref(true);

const fetchCalendarData = async () => {
    loading.value = true;
    try {
        await postsStore.fetchCalendarPosts(
            calendarStore.monthStart,
            calendarStore.monthEnd
        );
    } catch (error) {
        console.error('Failed to fetch calendar posts:', error);
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

const handleReschedule = async (post, newDate) => {
    try {
        const scheduledAt = new Date(newDate);
        scheduledAt.setHours(12, 0, 0, 0);
        await postsStore.reschedulePost(post.id, scheduledAt.toISOString());
        await fetchCalendarData();
    } catch (error) {
        console.error('Failed to reschedule post:', error);
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <RouterLink
                        :to="{ name: 'dashboard' }"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ t('calendar.title') }}
                    </h1>
                </div>
                <div class="flex items-center space-x-3">
                    <RouterLink :to="{ name: 'approval-tokens' }">
                        <Button variant="secondary">
                            {{ t('approval.tokens') }}
                        </Button>
                    </RouterLink>
                    <Button @click="handleCreatePost">
                        {{ t('posts.create') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <CalendarToolbar />

        <!-- Content -->
        <div class="p-6">
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>
            <CalendarView
                v-else
                :posts="postsStore.calendarPosts"
                @edit="handleEditPost"
                @reschedule="handleReschedule"
                @create="handleCreatePost"
            />
        </div>
    </div>
</template>
