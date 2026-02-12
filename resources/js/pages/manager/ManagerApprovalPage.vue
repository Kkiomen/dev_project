<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const activeFilter = ref('pending');
const replyingTo = ref(null);
const replyAction = ref(null);
const approvalNotes = ref('');
const actionLoading = ref(false);

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

const platformColors = {
    instagram: 'bg-pink-500',
    facebook: 'bg-blue-600',
    tiktok: 'bg-gray-100 text-black',
    linkedin: 'bg-blue-700',
    x: 'bg-gray-700',
    youtube: 'bg-red-600',
};

const statusBadgeClasses = {
    pending: 'bg-amber-500/10 text-amber-400',
    approved: 'bg-emerald-500/10 text-emerald-400',
    rejected: 'bg-red-500/10 text-red-400',
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

const startAction = (postId, action) => {
    replyingTo.value = postId;
    replyAction.value = action;
    approvalNotes.value = '';
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
            await managerStore.approveScheduledPost(replyingTo.value, { notes: approvalNotes.value });
            toast.success(t('manager.approval.approvedSuccess'));
        } else {
            await managerStore.rejectScheduledPost(replyingTo.value, { notes: approvalNotes.value });
            toast.success(t('manager.approval.rejectedSuccess'));
        }
        cancelAction();
    } catch (error) {
        toast.error(t('manager.approval.actionError'));
    } finally {
        actionLoading.value = false;
    }
};

const loadPosts = async () => {
    try {
        await managerStore.fetchScheduledPosts({ approval_status: activeFilter.value === 'all' ? undefined : activeFilter.value });
    } catch (error) {
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
        <div v-if="loading" class="flex items-center justify-center py-16">
            <LoadingSpinner size="lg" />
        </div>

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
                class="rounded-xl bg-gray-900 border border-gray-800 p-5 flex flex-col gap-4 transition-colors hover:border-gray-700"
            >
                <!-- Card Header: Platform + Status -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white"
                            :class="platformColors[post.platform] || 'bg-gray-600'"
                        >
                            {{ (post.platform || '?')[0].toUpperCase() }}
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

                <!-- Post Text Preview -->
                <p class="text-sm text-gray-300 leading-relaxed min-h-[3rem]">
                    {{ truncateText(post.content || post.text) }}
                </p>

                <!-- Scheduled Time -->
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span>{{ t('manager.approval.scheduledFor') }} {{ formatDate(post.scheduled_at) }}</span>
                </div>

                <!-- Action Buttons -->
                <div v-if="post.approval_status === 'pending'" class="pt-2 border-t border-gray-800">
                    <!-- Inline reply form -->
                    <div v-if="replyingTo === post.id" class="space-y-3">
                        <textarea
                            v-model="approvalNotes"
                            :placeholder="t('manager.approval.notesPlaceholder')"
                            rows="3"
                            class="w-full rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-200 placeholder-gray-500 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                        />
                        <div class="flex items-center gap-2">
                            <button
                                @click="submitAction"
                                :disabled="actionLoading"
                                class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                                :class="replyAction === 'approve'
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-500'
                                    : 'bg-red-600 text-white hover:bg-red-500'"
                            >
                                {{ t('manager.approval.submit') }}
                            </button>
                            <button
                                @click="cancelAction"
                                class="px-4 py-1.5 rounded-lg text-sm font-medium text-gray-400 bg-gray-800 hover:bg-gray-700 transition-colors"
                            >
                                {{ t('manager.approval.cancel') }}
                            </button>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div v-else class="flex items-center gap-2">
                        <button
                            @click="startAction(post.id, 'approve')"
                            class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-emerald-600/10 text-emerald-400 hover:bg-emerald-600/20 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            {{ t('manager.approval.approveBtn') }}
                        </button>
                        <button
                            @click="startAction(post.id, 'reject')"
                            class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-red-600/10 text-red-400 hover:bg-red-600/20 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            {{ t('manager.approval.rejectBtn') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
