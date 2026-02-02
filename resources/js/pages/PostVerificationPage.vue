<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePostsStore } from '@/stores/posts';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import Toast from '@/components/common/Toast.vue';
import PostSwipeCard from '@/components/verification/PostSwipeCard.vue';
import PostAiChat from '@/components/verification/PostAiChat.vue';

const { t } = useI18n();
const router = useRouter();
const postsStore = usePostsStore();
const toast = useToast();

const loading = ref(true);
const posts = ref([]);
const currentIndex = ref(0);
const isAnimating = ref(false);
const swipeDirection = ref(null); // 'left', 'right', null
const showAiChat = ref(false);
const editingPostId = ref(null); // Track which post is being edited

// Current post
const currentPost = computed(() => posts.value[currentIndex.value] || null);
const hasMorePosts = computed(() => currentIndex.value < posts.value.length - 1);
const hasPreviousPosts = computed(() => currentIndex.value > 0);
const progress = computed(() => {
    if (posts.value.length === 0) return 0;
    return Math.round((currentIndex.value / posts.value.length) * 100);
});

// Stats
const stats = computed(() => {
    const approved = posts.value.filter(p => p._verified === true).length;
    const rejected = posts.value.filter(p => p._verified === false).length;
    const pending = posts.value.length - approved - rejected;
    return { approved, rejected, pending, total: posts.value.length };
});

const fetchPosts = async () => {
    loading.value = true;
    try {
        await postsStore.fetchPendingApproval({ per_page: 100 });
        posts.value = (postsStore.posts || []).map(p => ({
            ...p,
            _verified: null, // null = pending, true = approved, false = rejected
        }));
        currentIndex.value = 0;
    } catch (error) {
        console.error('Failed to fetch posts:', error);
        toast.error(t('verification.errors.fetchFailed'));
    } finally {
        loading.value = false;
    }
};

// Refresh current post when window regains focus (after editing in new tab)
const handleWindowFocus = async () => {
    if (!editingPostId.value) return;

    const postId = editingPostId.value;
    editingPostId.value = null;

    try {
        const updatedPost = await postsStore.fetchPost(postId);
        if (updatedPost) {
            const index = posts.value.findIndex(p => p.id === postId);
            if (index !== -1) {
                const verified = posts.value[index]._verified;
                posts.value[index] = {
                    ...updatedPost,
                    _verified: verified,
                };
                toast.success(t('verification.postUpdatedFromEditor'));
            }
        }
    } catch (error) {
        console.error('Failed to refresh post:', error);
    }
};

onMounted(() => {
    fetchPosts();
    window.addEventListener('focus', handleWindowFocus);
});

// Swipe handlers
const handleSwipe = async (direction) => {
    if (isAnimating.value || !currentPost.value) return;

    isAnimating.value = true;
    swipeDirection.value = direction;

    const post = currentPost.value;

    try {
        if (direction === 'right') {
            // Approve
            await postsStore.approvePost(post.id);
            post._verified = true;
            toast.success(t('verification.approved'));
        } else {
            // Reject - just mark locally, don't reject on server yet
            post._verified = false;
            toast.info(t('verification.rejected'));
        }
    } catch (error) {
        console.error('Failed to process post:', error);
        toast.error(t('verification.errors.processFailed'));
    }

    // Move to next post after animation
    setTimeout(() => {
        if (hasMorePosts.value) {
            currentIndex.value++;
        }
        swipeDirection.value = null;
        isAnimating.value = false;
    }, 300);
};

const handleApprove = () => handleSwipe('right');
const handleReject = () => handleSwipe('left');

const handlePrevious = () => {
    if (hasPreviousPosts.value && !isAnimating.value) {
        currentIndex.value--;
    }
};

const handleSkip = () => {
    if (hasMorePosts.value && !isAnimating.value) {
        currentIndex.value++;
    }
};

const handleEdit = () => {
    if (currentPost.value) {
        editingPostId.value = currentPost.value.id;
        const route = router.resolve({ name: 'post.edit', params: { postId: currentPost.value.id } });
        window.open(route.href, '_blank');
    }
};

const toggleAiChat = () => {
    showAiChat.value = !showAiChat.value;
};

// Handle AI modifications
const handleAiModification = async (modifiedContent) => {
    if (!currentPost.value) return;

    try {
        await postsStore.updatePost(currentPost.value.id, {
            main_caption: modifiedContent.caption,
            title: modifiedContent.title || currentPost.value.title,
        });

        // Update local post
        const post = posts.value[currentIndex.value];
        if (post) {
            post.main_caption = modifiedContent.caption;
            if (modifiedContent.title) {
                post.title = modifiedContent.title;
            }
        }

        toast.success(t('verification.postUpdated'));
    } catch (error) {
        console.error('Failed to update post:', error);
        toast.error(t('verification.errors.updateFailed'));
    }
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    if (showAiChat.value) return; // Don't handle when chat is open

    switch (e.key) {
        case 'ArrowLeft':
            handleReject();
            break;
        case 'ArrowRight':
            handleApprove();
            break;
        case 'ArrowUp':
            handlePrevious();
            break;
        case 'ArrowDown':
        case ' ':
            e.preventDefault();
            handleSkip();
            break;
        case 'e':
            handleEdit();
            break;
        case 'a':
            toggleAiChat();
            break;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('focus', handleWindowFocus);
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
        <!-- Header -->
        <div class="bg-gray-800/50 backdrop-blur-sm border-b border-gray-700 px-6 py-3">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button
                        @click="router.push({ name: 'calendar' })"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </button>
                    <h1 class="text-xl font-bold text-white">
                        {{ t('verification.title') }}
                    </h1>
                </div>

                <!-- Stats -->
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-2 text-green-400">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">{{ stats.approved }}</span>
                    </div>
                    <div class="flex items-center space-x-2 text-red-400">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">{{ stats.rejected }}</span>
                    </div>
                    <div class="flex items-center space-x-2 text-gray-400">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">{{ stats.pending }}</span>
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="max-w-6xl mx-auto mt-3">
                <div class="h-1 bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-300"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>
                <p class="text-xs text-gray-500 mt-1 text-center">
                    {{ currentIndex + 1 }} / {{ posts.length }}
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-32">
            <LoadingSpinner size="lg" class="text-white" />
        </div>

        <!-- No posts -->
        <div v-else-if="posts.length === 0" class="flex flex-col items-center justify-center py-32 px-4">
            <div class="w-24 h-24 bg-gray-800 rounded-full flex items-center justify-center mb-6">
                <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">
                {{ t('verification.noPosts') }}
            </h2>
            <p class="text-gray-400 text-center max-w-md mb-6">
                {{ t('verification.noPostsDescription') }}
            </p>
            <Button @click="router.push({ name: 'calendar' })">
                {{ t('verification.backToCalendar') }}
            </Button>
        </div>

        <!-- All done -->
        <div v-else-if="!currentPost" class="flex flex-col items-center justify-center py-32 px-4">
            <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mb-6">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">
                {{ t('verification.allDone') }}
            </h2>
            <p class="text-gray-400 text-center max-w-md mb-6">
                {{ t('verification.allDoneDescription', { approved: stats.approved, rejected: stats.rejected }) }}
            </p>
            <div class="flex space-x-4">
                <Button variant="secondary" @click="fetchPosts">
                    {{ t('verification.startOver') }}
                </Button>
                <Button @click="router.push({ name: 'calendar' })">
                    {{ t('verification.backToCalendar') }}
                </Button>
            </div>
        </div>

        <!-- Swipe area -->
        <div v-else class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex gap-6">
                <!-- Card area -->
                <div class="flex-1 flex flex-col items-center">
                    <!-- Swipe card -->
                    <div class="relative w-full max-w-xl">
                        <PostSwipeCard
                            :post="currentPost"
                            :swipe-direction="swipeDirection"
                            :is-animating="isAnimating"
                            @swipe="handleSwipe"
                        />
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center justify-center space-x-6 mt-4">
                        <!-- Reject -->
                        <button
                            @click="handleReject"
                            :disabled="isAnimating"
                            class="w-14 h-14 bg-red-500/20 hover:bg-red-500/40 border-2 border-red-500 rounded-full flex items-center justify-center text-red-500 transition-all transform hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        >
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>

                        <!-- Previous -->
                        <button
                            @click="handlePrevious"
                            :disabled="!hasPreviousPosts || isAnimating"
                            class="w-10 h-10 bg-gray-700/50 hover:bg-gray-700 border border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                        </button>

                        <!-- Edit -->
                        <button
                            @click="handleEdit"
                            :disabled="isAnimating"
                            class="w-10 h-10 bg-gray-700/50 hover:bg-gray-700 border border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>

                        <!-- AI Chat -->
                        <button
                            @click="toggleAiChat"
                            :class="[
                                'w-10 h-10 rounded-full flex items-center justify-center transition-all',
                                showAiChat
                                    ? 'bg-purple-500 text-white'
                                    : 'bg-gray-700/50 hover:bg-gray-700 border border-gray-600 text-gray-400 hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </button>

                        <!-- Approve -->
                        <button
                            @click="handleApprove"
                            :disabled="isAnimating"
                            class="w-14 h-14 bg-green-500/20 hover:bg-green-500/40 border-2 border-green-500 rounded-full flex items-center justify-center text-green-500 transition-all transform hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        >
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Keyboard hints -->
                    <div class="flex items-center justify-center space-x-6 mt-3 text-xs text-gray-500">
                        <span class="flex items-center space-x-1">
                            <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400">←</kbd>
                            <span>{{ t('verification.hints.reject') }}</span>
                        </span>
                        <span class="flex items-center space-x-1">
                            <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400">→</kbd>
                            <span>{{ t('verification.hints.approve') }}</span>
                        </span>
                        <span class="flex items-center space-x-1">
                            <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400">A</kbd>
                            <span>{{ t('verification.hints.ai') }}</span>
                        </span>
                        <span class="flex items-center space-x-1">
                            <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400">E</kbd>
                            <span>{{ t('verification.hints.edit') }}</span>
                        </span>
                    </div>
                </div>

                <!-- AI Chat panel -->
                <transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 translate-x-4"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 translate-x-4"
                >
                    <div v-if="showAiChat" class="w-96">
                        <PostAiChat
                            :post="currentPost"
                            @close="showAiChat = false"
                            @modify="handleAiModification"
                        />
                    </div>
                </transition>
            </div>
        </div>

        <Toast />
    </div>
</template>
