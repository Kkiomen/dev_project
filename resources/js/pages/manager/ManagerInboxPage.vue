<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const activeTab = ref('comments');
const platformFilter = ref('');
const sentimentFilter = ref('');
const readFilter = ref('');
const replyingTo = ref(null);
const replyText = ref('');
const replyLoading = ref(false);

const platforms = ['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];
const sentiments = ['positive', 'neutral', 'negative'];

const tabs = ['comments', 'messages'];

const commentsLoading = computed(() => managerStore.commentsLoading);
const messagesLoading = computed(() => managerStore.messagesLoading);
const loading = computed(() => activeTab.value === 'comments' ? commentsLoading.value : messagesLoading.value);

const filteredComments = computed(() => {
    let items = managerStore.comments || [];
    if (platformFilter.value) {
        items = items.filter(c => c.platform === platformFilter.value);
    }
    if (sentimentFilter.value) {
        items = items.filter(c => c.sentiment === sentimentFilter.value);
    }
    return items;
});

const filteredMessages = computed(() => {
    let items = managerStore.messages || [];
    if (platformFilter.value) {
        items = items.filter(m => m.platform === platformFilter.value);
    }
    if (readFilter.value === 'unread') {
        items = items.filter(m => !m.is_read);
    } else if (readFilter.value === 'read') {
        items = items.filter(m => m.is_read);
    }
    return items;
});

const commentsMeta = computed(() => managerStore.commentsMeta);
const messagesMeta = computed(() => managerStore.messagesMeta);

const hasMoreComments = computed(() => {
    if (!commentsMeta.value) return false;
    return commentsMeta.value.current_page < commentsMeta.value.last_page;
});

const hasMoreMessages = computed(() => {
    if (!messagesMeta.value) return false;
    return messagesMeta.value.current_page < messagesMeta.value.last_page;
});

const platformColors = {
    instagram: 'bg-pink-500',
    facebook: 'bg-blue-600',
    tiktok: 'bg-gray-100 text-black',
    linkedin: 'bg-blue-700',
    x: 'bg-gray-700',
    youtube: 'bg-red-600',
};

const sentimentBadgeClasses = {
    positive: 'bg-emerald-500/10 text-emerald-400',
    neutral: 'bg-gray-500/10 text-gray-400',
    negative: 'bg-red-500/10 text-red-400',
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return t('manager.inbox.justNow');
    if (diffMins < 60) return t('manager.inbox.minutesAgo', { count: diffMins });
    if (diffHours < 24) return t('manager.inbox.hoursAgo', { count: diffHours });
    if (diffDays < 7) return t('manager.inbox.daysAgo', { count: diffDays });

    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const truncateText = (text, maxLength = 150) => {
    if (!text) return '';
    return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
};

const getAvatarColor = (name) => {
    if (!name) return 'bg-gray-600';
    const colors = [
        'bg-pink-500', 'bg-purple-500', 'bg-indigo-500', 'bg-blue-500',
        'bg-cyan-500', 'bg-teal-500', 'bg-emerald-500', 'bg-amber-500',
    ];
    const idx = name.charCodeAt(0) % colors.length;
    return colors[idx];
};

const startReply = (itemId) => {
    replyingTo.value = itemId;
    replyText.value = '';
};

const cancelReply = () => {
    replyingTo.value = null;
    replyText.value = '';
};

const sendReply = async (commentId) => {
    if (!replyText.value.trim()) return;

    replyLoading.value = true;
    try {
        await managerStore.replyToComment(commentId, { text: replyText.value });
        toast.success(t('manager.inbox.replySent'));
        cancelReply();
    } catch (error) {
        toast.error(t('manager.inbox.replyError'));
    } finally {
        replyLoading.value = false;
    }
};

const hideComment = async (commentId) => {
    try {
        await managerStore.hideComment(commentId);
        toast.success(t('manager.inbox.commentHidden'));
    } catch (error) {
        toast.error(t('manager.inbox.actionError'));
    }
};

const flagComment = async (commentId) => {
    try {
        await managerStore.flagComment(commentId);
        toast.success(t('manager.inbox.commentFlagged'));
    } catch (error) {
        toast.error(t('manager.inbox.actionError'));
    }
};

const markAsRead = async (messageId) => {
    try {
        await managerStore.markMessageAsRead(messageId);
    } catch (error) {
        toast.error(t('manager.inbox.actionError'));
    }
};

const loadComments = async (page = 1) => {
    try {
        await managerStore.fetchComments({
            platform: platformFilter.value || undefined,
            sentiment: sentimentFilter.value || undefined,
            page,
        });
    } catch (error) {
        toast.error(t('manager.inbox.loadError'));
    }
};

const loadMessages = async (page = 1) => {
    try {
        await managerStore.fetchMessages({
            platform: platformFilter.value || undefined,
            is_read: readFilter.value === 'read' ? true : readFilter.value === 'unread' ? false : undefined,
            page,
        });
    } catch (error) {
        toast.error(t('manager.inbox.loadError'));
    }
};

const loadMoreComments = () => {
    if (hasMoreComments.value && commentsMeta.value) {
        loadComments(commentsMeta.value.current_page + 1);
    }
};

const loadMoreMessages = () => {
    if (hasMoreMessages.value && messagesMeta.value) {
        loadMessages(messagesMeta.value.current_page + 1);
    }
};

const loadData = () => {
    if (activeTab.value === 'comments') {
        loadComments();
    } else {
        loadMessages();
    }
};

onMounted(() => {
    loadData();
});

watch(activeTab, () => {
    platformFilter.value = '';
    sentimentFilter.value = '';
    readFilter.value = '';
    replyingTo.value = null;
    replyText.value = '';
    loadData();
});

watch([platformFilter, sentimentFilter, readFilter], () => {
    loadData();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">{{ t('manager.inbox.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.inbox.subtitle') }}</p>
        </div>

        <!-- Tab Switcher -->
        <div class="flex items-center gap-1 bg-gray-900 rounded-lg p-1 mb-6 w-fit">
            <button
                v-for="tab in tabs"
                :key="tab"
                @click="activeTab = tab"
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
                :class="activeTab === tab
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-400 hover:text-gray-200'"
            >
                {{ t(`manager.inbox.tabs.${tab}`) }}
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <!-- Platform Filter -->
            <select
                v-model="platformFilter"
                class="rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
                <option value="">{{ t('manager.inbox.filterPlatform') }}</option>
                <option v-for="p in platforms" :key="p" :value="p">
                    {{ p.charAt(0).toUpperCase() + p.slice(1) }}
                </option>
            </select>

            <!-- Sentiment Filter (comments only) -->
            <select
                v-if="activeTab === 'comments'"
                v-model="sentimentFilter"
                class="rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
                <option value="">{{ t('manager.inbox.filterSentiment') }}</option>
                <option v-for="s in sentiments" :key="s" :value="s">
                    {{ t(`manager.inbox.sentiment.${s}`) }}
                </option>
            </select>

            <!-- Read/Unread Filter (messages only) -->
            <select
                v-if="activeTab === 'messages'"
                v-model="readFilter"
                class="rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
                <option value="">{{ t('manager.inbox.filterRead') }}</option>
                <option value="unread">{{ t('manager.inbox.unread') }}</option>
                <option value="read">{{ t('manager.inbox.read') }}</option>
            </select>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-16">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Comments Tab -->
        <template v-else-if="activeTab === 'comments'">
            <!-- Empty State -->
            <div
                v-if="filteredComments.length === 0"
                class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center"
            >
                <div class="w-16 h-16 rounded-full bg-indigo-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.inbox.emptyCommentsTitle') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.inbox.emptyCommentsDescription') }}</p>
            </div>

            <!-- Comments List -->
            <div v-else class="space-y-3">
                <div
                    v-for="comment in filteredComments"
                    :key="comment.id"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4 transition-colors hover:border-gray-700"
                >
                    <div class="flex items-start gap-3">
                        <!-- Avatar -->
                        <span
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0"
                            :class="getAvatarColor(comment.author_handle)"
                        >
                            {{ (comment.author_handle || '?')[0].toUpperCase() }}
                        </span>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-sm font-medium text-white">
                                    {{ comment.author_handle || '--' }}
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="platformColors[comment.platform] || 'bg-gray-600'"
                                >
                                    {{ (comment.platform || '?')[0].toUpperCase() }}
                                </span>
                                <span
                                    v-if="comment.sentiment"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="sentimentBadgeClasses[comment.sentiment] || 'bg-gray-700 text-gray-300'"
                                >
                                    {{ t(`manager.inbox.sentiment.${comment.sentiment}`) }}
                                </span>
                                <span class="text-xs text-gray-500 ml-auto shrink-0">
                                    {{ formatTime(comment.posted_at || comment.created_at) }}
                                </span>
                            </div>

                            <p class="text-sm text-gray-300 leading-relaxed mb-3">
                                {{ comment.text || comment.content }}
                            </p>

                            <!-- Inline Reply Form -->
                            <div v-if="replyingTo === comment.id" class="space-y-2 mb-3">
                                <textarea
                                    v-model="replyText"
                                    :placeholder="t('manager.inbox.replyPlaceholder')"
                                    rows="2"
                                    class="w-full rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-200 placeholder-gray-500 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                />
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="sendReply(comment.id)"
                                        :disabled="replyLoading || !replyText.trim()"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50"
                                    >
                                        {{ t('manager.inbox.send') }}
                                    </button>
                                    <button
                                        @click="cancelReply"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-400 bg-gray-800 hover:bg-gray-700 transition-colors"
                                    >
                                        {{ t('manager.inbox.cancel') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div v-else class="flex items-center gap-2">
                                <button
                                    @click="startReply(comment.id)"
                                    class="px-3 py-1 rounded-lg text-xs font-medium text-gray-400 bg-gray-800 hover:bg-gray-700 hover:text-gray-200 transition-colors"
                                >
                                    {{ t('manager.inbox.reply') }}
                                </button>
                                <button
                                    @click="hideComment(comment.id)"
                                    class="px-3 py-1 rounded-lg text-xs font-medium text-gray-400 bg-gray-800 hover:bg-gray-700 hover:text-gray-200 transition-colors"
                                >
                                    {{ t('manager.inbox.hide') }}
                                </button>
                                <button
                                    @click="flagComment(comment.id)"
                                    class="px-3 py-1 rounded-lg text-xs font-medium text-gray-400 bg-gray-800 hover:bg-red-900/30 hover:text-red-400 transition-colors"
                                >
                                    {{ t('manager.inbox.flag') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Load More -->
                <div v-if="hasMoreComments" class="flex justify-center pt-4">
                    <button
                        @click="loadMoreComments"
                        class="px-6 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                    >
                        {{ t('manager.inbox.loadMore') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Messages Tab -->
        <template v-else-if="activeTab === 'messages'">
            <!-- Empty State -->
            <div
                v-if="filteredMessages.length === 0"
                class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center"
            >
                <div class="w-16 h-16 rounded-full bg-pink-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3M2.25 18.75h19.5" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.inbox.emptyMessagesTitle') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.inbox.emptyMessagesDescription') }}</p>
            </div>

            <!-- Messages List -->
            <div v-else class="space-y-3">
                <button
                    v-for="message in filteredMessages"
                    :key="message.id"
                    @click="markAsRead(message.id)"
                    class="w-full text-left rounded-xl bg-gray-900 border border-gray-800 p-4 transition-colors hover:border-gray-700"
                    :class="{ 'border-indigo-500/30 bg-gray-900/80': !message.is_read }"
                >
                    <div class="flex items-start gap-3">
                        <!-- Avatar + Unread dot -->
                        <div class="relative shrink-0">
                            <span
                                class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white"
                                :class="getAvatarColor(message.from_handle)"
                            >
                                {{ (message.from_handle || '?')[0].toUpperCase() }}
                            </span>
                            <span
                                v-if="!message.is_read"
                                class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-indigo-500 border-2 border-gray-900"
                            />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-sm font-medium" :class="message.is_read ? 'text-gray-300' : 'text-white'">
                                    {{ message.from_handle || '--' }}
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="platformColors[message.platform] || 'bg-gray-600'"
                                >
                                    {{ (message.platform || '?')[0].toUpperCase() }}
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="message.direction === 'inbound'
                                        ? 'bg-blue-500/10 text-blue-400'
                                        : 'bg-gray-500/10 text-gray-400'"
                                >
                                    {{ message.direction === 'inbound' ? t('manager.inbox.inbound') : t('manager.inbox.outbound') }}
                                </span>
                                <span class="text-xs text-gray-500 ml-auto shrink-0">
                                    {{ formatTime(message.sent_at || message.created_at) }}
                                </span>
                            </div>

                            <p class="text-sm leading-relaxed" :class="message.is_read ? 'text-gray-400' : 'text-gray-300'">
                                {{ truncateText(message.text || message.content) }}
                            </p>
                        </div>
                    </div>
                </button>

                <!-- Load More -->
                <div v-if="hasMoreMessages" class="flex justify-center pt-4">
                    <button
                        @click="loadMoreMessages"
                        class="px-6 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                    >
                        {{ t('manager.inbox.loadMore') }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
