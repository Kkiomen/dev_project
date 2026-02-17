<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import SkeletonLoader from '@/components/common/SkeletonLoader.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();
const { confirm: confirmDialog } = useConfirm();

const activeFilter = ref('pending');
const replyingTo = ref(null);
const replyAction = ref(null);
const approvalNotes = ref('');
const actionLoading = ref(false);
const detailPost = ref(null);

const filters = ['all', 'pending', 'approved', 'rejected'];

const loading = computed(() => managerStore.scheduledPostsLoading);

const filteredPosts = computed(() => {
    const posts = managerStore.scheduledPosts || [];
    if (activeFilter.value === 'all') return posts;
    return posts.filter(p => p.approval_status === activeFilter.value);
});

const pendingCount = computed(() => {
    return (managerStore.scheduledPosts || []).filter(p => p.approval_status === 'pending').length;
});

const approvedCount = computed(() => {
    return (managerStore.scheduledPosts || []).filter(p => p.approval_status === 'approved').length;
});

const rejectedCount = computed(() => {
    return (managerStore.scheduledPosts || []).filter(p => p.approval_status === 'rejected').length;
});

const platformIcons = {
    instagram: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>`,
    facebook: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>`,
    tiktok: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>`,
    linkedin: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>`,
    x: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>`,
    youtube: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>`,
};

const platformColors = {
    instagram: 'bg-gradient-to-br from-purple-600 to-pink-500',
    facebook: 'bg-blue-600',
    tiktok: 'bg-black',
    linkedin: 'bg-blue-700',
    x: 'bg-gray-800',
    youtube: 'bg-red-600',
};

const statusBadgeClasses = {
    pending: 'bg-amber-500/10 text-amber-400',
    approved: 'bg-emerald-500/10 text-emerald-400',
    rejected: 'bg-red-500/10 text-red-400',
};

const getPostCaption = (post) => {
    return post.social_post?.main_caption || '';
};

const getPostTitle = (post) => {
    return post.social_post?.title || '';
};

const getPostHashtags = (post) => {
    return post.social_post?.settings?.hashtags || [];
};

const getPostImageUrl = (post) => {
    const assets = post.social_post?.generated_assets || [];
    const img = assets.find(a => a.type === 'image' && a.status === 'completed' && a.url);
    return img?.url || post.social_post?.first_media_url || null;
};

const truncateText = (text, maxLength = 120) => {
    if (!text) return '';
    return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const openDetail = (post) => {
    detailPost.value = post;
};

const closeDetail = () => {
    detailPost.value = null;
};

const submitFromDetail = (action) => {
    replyingTo.value = detailPost.value.id;
    replyAction.value = action;
    submitAction();
};

const startAction = (postId, action, notes = null) => {
    replyingTo.value = postId;
    replyAction.value = action;
    if (notes === null) approvalNotes.value = '';
};

const cancelAction = () => {
    replyingTo.value = null;
    replyAction.value = null;
    approvalNotes.value = '';
};

const submitAction = async () => {
    if (replyAction.value === 'reject' && !approvalNotes.value.trim()) {
        toast.error(t('manager.approval.notesRequired'));
        return;
    }

    actionLoading.value = true;

    try {
        if (replyAction.value === 'approve') {
            await managerStore.approveScheduledPost(replyingTo.value, approvalNotes.value || null);
            toast.success(t('manager.approval.approvedSuccess'));
        } else {
            await managerStore.rejectScheduledPost(replyingTo.value, approvalNotes.value);
            toast.success(t('manager.approval.rejectedSuccess'));
        }
        cancelAction();
        closeDetail();
    } catch {
        const errorKey = replyAction.value === 'approve' ? 'approveError' : 'rejectError';
        toast.error(t(`manager.approval.${errorKey}`));
    } finally {
        actionLoading.value = false;
    }
};

const confirmDelete = async (postId) => {
    const confirmed = await confirmDialog({
        title: t('common.deleteConfirmTitle'),
        message: t('manager.approval.deleteConfirm'),
        confirmText: t('common.delete'),
        variant: 'danger',
    });
    if (!confirmed) return;

    try {
        await managerStore.deleteScheduledPost(postId);
        toast.success(t('manager.approval.deleteSuccess'));
        closeDetail();
    } catch {
        toast.error(t('manager.approval.deleteError'));
    }
};

const loadPosts = async () => {
    try {
        await managerStore.fetchScheduledPosts({ approval_status: activeFilter.value === 'all' ? undefined : activeFilter.value });
    } catch {
        toast.error(t('manager.approval.loadError'));
    }
};

onMounted(() => {
    loadPosts();
});

watch(activeFilter, () => {
    loadPosts();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">{{ t('manager.approval.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.approval.subtitle') }}</p>
        </div>

        <!-- Filter Tabs -->
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <button
                v-for="filter in filters"
                :key="filter"
                @click="activeFilter = filter"
                class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors"
                :class="activeFilter === filter
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-800 text-gray-400 hover:text-gray-200 hover:bg-gray-700'"
            >
                {{ t(`manager.approval.filters.${filter}`) }}
            </button>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ t('manager.approval.stats.pending') }}
                </p>
                <p class="mt-2 text-2xl font-bold text-amber-400">{{ pendingCount }}</p>
            </div>
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ t('manager.approval.stats.approvedToday') }}
                </p>
                <p class="mt-2 text-2xl font-bold text-emerald-400">{{ approvedCount }}</p>
            </div>
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ t('manager.approval.stats.rejected') }}
                </p>
                <p class="mt-2 text-2xl font-bold text-red-400">{{ rejectedCount }}</p>
            </div>
        </div>

        <!-- Loading State -->
        <SkeletonLoader v-if="loading" variant="card-grid" :count="6" />

        <!-- Empty State -->
        <div
            v-else-if="filteredPosts.length === 0"
            class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center"
        >
            <div class="w-16 h-16 rounded-full bg-emerald-500/10 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.approval.emptyTitle') }}</h3>
            <p class="text-sm text-gray-400 max-w-md">{{ t('manager.approval.emptyDescription') }}</p>
        </div>

        <!-- Post Cards Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="post in filteredPosts"
                :key="post.id"
                class="rounded-xl bg-gray-900 border border-gray-800 flex flex-col transition-colors hover:border-gray-700 overflow-hidden cursor-pointer"
                @click="openDetail(post)"
            >
                <!-- Post Image -->
                <div v-if="getPostImageUrl(post)" class="relative">
                    <img
                        :src="getPostImageUrl(post)"
                        :alt="getPostTitle(post)"
                        class="w-full h-48 object-cover"
                    />
                    <!-- Platform badge on image -->
                    <span
                        class="absolute top-3 left-3 w-8 h-8 rounded-full flex items-center justify-center text-white shadow-lg"
                        :class="platformColors[post.platform] || 'bg-gray-600'"
                    >
                        <span class="w-4 h-4" v-html="platformIcons[post.platform] || ''"></span>
                    </span>
                    <!-- Status badge on image -->
                    <span
                        class="absolute top-3 right-3 px-2.5 py-0.5 rounded-full text-xs font-medium backdrop-blur-sm"
                        :class="statusBadgeClasses[post.approval_status] || 'bg-gray-700 text-gray-300'"
                    >
                        {{ t(`manager.approval.filters.${post.approval_status}`) }}
                    </span>
                </div>

                <div class="p-5 flex flex-col gap-3 flex-1">
                    <!-- Card Header (only when no image) -->
                    <div v-if="!getPostImageUrl(post)" class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span
                                class="w-8 h-8 rounded-full flex items-center justify-center text-white"
                                :class="platformColors[post.platform] || 'bg-gray-600'"
                            >
                                <span class="w-4 h-4" v-html="platformIcons[post.platform] || ''"></span>
                            </span>
                            <span class="text-sm font-medium text-gray-300 capitalize">{{ post.platform }}</span>
                        </div>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="statusBadgeClasses[post.approval_status] || 'bg-gray-700 text-gray-300'"
                        >
                            {{ t(`manager.approval.filters.${post.approval_status}`) }}
                        </span>
                    </div>

                    <!-- Post Title -->
                    <h3 v-if="getPostTitle(post)" class="text-sm font-semibold text-white leading-snug">
                        {{ getPostTitle(post) }}
                    </h3>

                    <!-- Post Caption Preview -->
                    <p class="text-sm text-gray-400 leading-relaxed">
                        {{ truncateText(getPostCaption(post)) || t('manager.approval.noContent') }}
                    </p>

                    <!-- Scheduled Time -->
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 mt-auto pt-1">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ t('manager.approval.scheduledFor') }} {{ formatDate(post.scheduled_at) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <Teleport to="body">
            <div
                v-if="detailPost"
                class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 bg-black/70 backdrop-blur-sm overflow-y-auto"
                @click.self="closeDetail"
            >
                <div class="w-full max-w-2xl my-8 rounded-2xl bg-gray-900 border border-gray-800 shadow-2xl overflow-hidden">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                        <div class="flex items-center gap-3">
                            <span
                                class="w-9 h-9 rounded-full flex items-center justify-center text-white"
                                :class="platformColors[detailPost.platform] || 'bg-gray-600'"
                            >
                                <span class="w-4 h-4" v-html="platformIcons[detailPost.platform] || ''"></span>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-white capitalize">{{ detailPost.platform }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(detailPost.scheduled_at) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="statusBadgeClasses[detailPost.approval_status] || 'bg-gray-700 text-gray-300'"
                            >
                                {{ t(`manager.approval.filters.${detailPost.approval_status}`) }}
                            </span>
                            <button
                                @click="closeDetail"
                                class="p-1.5 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Image -->
                    <img
                        v-if="getPostImageUrl(detailPost)"
                        :src="getPostImageUrl(detailPost)"
                        :alt="getPostTitle(detailPost)"
                        class="w-full max-h-96 object-cover"
                    />

                    <!-- Content -->
                    <div class="px-6 py-5 space-y-4">
                        <!-- Title -->
                        <h2 v-if="getPostTitle(detailPost)" class="text-lg font-bold text-white leading-snug">
                            {{ getPostTitle(detailPost) }}
                        </h2>

                        <!-- Full Caption -->
                        <p class="text-sm text-gray-300 leading-relaxed whitespace-pre-line">{{ getPostCaption(detailPost) }}</p>

                        <!-- Hashtags -->
                        <div v-if="getPostHashtags(detailPost).length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in getPostHashtags(detailPost)"
                                :key="tag"
                                class="px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 text-xs"
                            >
                                #{{ tag }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div v-if="detailPost.approval_status === 'pending'" class="px-6 pb-5 space-y-3">
                        <div class="border-t border-gray-800 pt-4">
                            <!-- Notes input -->
                            <textarea
                                v-model="approvalNotes"
                                :placeholder="t('manager.approval.notesPlaceholder')"
                                rows="3"
                                class="w-full rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-200 placeholder-gray-500 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                            />
                            <div class="flex items-center gap-2 mt-3">
                                <button
                                    @click="submitFromDetail('approve')"
                                    :disabled="actionLoading"
                                    class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-500 transition-colors disabled:opacity-50"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ t('manager.approval.approveBtn') }}
                                </button>
                                <button
                                    @click="submitFromDetail('reject')"
                                    :disabled="actionLoading"
                                    class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-500 transition-colors disabled:opacity-50"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                    {{ t('manager.approval.rejectBtn') }}
                                </button>
                                <button
                                    @click="confirmDelete(detailPost.id)"
                                    class="p-2.5 rounded-lg text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                                    :title="t('manager.approval.deleteBtn')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Non-pending footer -->
                    <div v-else class="px-6 pb-5">
                        <div class="border-t border-gray-800 pt-4 flex items-center justify-between">
                            <div v-if="detailPost.approval_notes" class="text-xs text-gray-500">
                                <span class="font-medium text-gray-400">{{ t('manager.approval.notes') }}:</span>
                                {{ detailPost.approval_notes }}
                            </div>
                            <button
                                @click="confirmDelete(detailPost.id)"
                                class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-red-400 transition-colors ml-auto"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                {{ t('manager.approval.deleteBtn') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>
