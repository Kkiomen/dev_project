<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const router = useRouter();
const managerStore = useManagerStore();
const toast = useToast();

const activeFilter = ref('all');
const searchQuery = ref('');
const sortBy = ref('newest');

const statusFilters = ['all', 'draft', 'pending', 'approved', 'scheduled', 'published'];

const statusColors = {
    draft: 'bg-gray-500/20 text-gray-300',
    pending_approval: 'bg-yellow-500/20 text-yellow-400',
    approved: 'bg-blue-500/20 text-blue-400',
    scheduled: 'bg-purple-500/20 text-purple-400',
    published: 'bg-emerald-500/20 text-emerald-400',
    failed: 'bg-red-500/20 text-red-400',
};

const platformIcons = {
    instagram: 'IG',
    facebook: 'FB',
    tiktok: 'TT',
    linkedin: 'LI',
    x: 'X',
    youtube: 'YT',
};

const posts = computed(() => {
    return managerStore.scheduledPosts.map(sp => ({
        id: sp.id,
        title: sp.social_post?.title || sp.content?.substring(0, 50) || '',
        main_caption: sp.social_post?.main_caption || sp.content || '',
        status: sp.approval_status === 'pending' ? 'pending_approval' : sp.approval_status,
        scheduled_at: sp.scheduled_at,
        created_at: sp.created_at,
        platforms: [sp.platform],
        thumbnail_url: sp.social_post?.thumbnail_url || null,
    }));
});

const filteredPosts = computed(() => {
    let result = [...posts.value];

    if (activeFilter.value !== 'all') {
        const filterMap = { pending: 'pending_approval' };
        const status = filterMap[activeFilter.value] || activeFilter.value;
        result = result.filter(p => p.status === status);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(p =>
            p.title?.toLowerCase().includes(q) ||
            p.main_caption?.toLowerCase().includes(q)
        );
    }

    if (sortBy.value === 'oldest') {
        result.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    } else if (sortBy.value === 'scheduled') {
        result.sort((a, b) => {
            if (!a.scheduled_at) return 1;
            if (!b.scheduled_at) return -1;
            return new Date(a.scheduled_at) - new Date(b.scheduled_at);
        });
    } else {
        result.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    }

    return result;
});

const statusCounts = computed(() => {
    const counts = { all: posts.value.length };
    for (const post of posts.value) {
        const key = post.status === 'pending_approval' ? 'pending' : post.status;
        counts[key] = (counts[key] || 0) + 1;
    }
    return counts;
});

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const truncate = (text, max = 120) => {
    if (!text || text.length <= max) return text;
    return text.substring(0, max) + '...';
};

const handleCreatePost = () => {
    router.push({ name: 'manager.postEditor' });
};

const handleEditPost = (post) => {
    router.push({ name: 'manager.postEditor', params: { id: post.id } });
};

const handleDeletePost = async (post) => {
    try {
        await managerStore.deleteScheduledPost(post.id);
        toast.success(t('manager.content.deleted'));
    } catch {
        toast.error(t('manager.content.deleteError'));
    }
};

onMounted(async () => {
    await managerStore.fetchScheduledPosts();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('manager.content.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('manager.content.subtitle') }}</p>
            </div>
            <button
                @click="handleCreatePost"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ t('manager.content.createPost') }}
            </button>
        </div>

        <!-- Filters bar -->
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <!-- Status tabs -->
            <div class="flex gap-1 overflow-x-auto pb-1 flex-1">
                <button
                    v-for="filter in statusFilters"
                    :key="filter"
                    @click="activeFilter = filter"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors flex items-center gap-1.5"
                    :class="activeFilter === filter ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-gray-200'"
                >
                    {{ t(`manager.content.filters.${filter}`) }}
                    <span
                        v-if="statusCounts[filter]"
                        class="px-1.5 py-0.5 text-[10px] rounded-full"
                        :class="activeFilter === filter ? 'bg-white/20' : 'bg-gray-700'"
                    >
                        {{ statusCounts[filter] }}
                    </span>
                </button>
            </div>

            <!-- Search & Sort -->
            <div class="flex gap-2">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('manager.content.search')"
                        class="pl-9 pr-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none w-48"
                    />
                </div>
                <select
                    v-model="sortBy"
                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                >
                    <option value="newest">{{ t('manager.content.sortOptions.newest') }}</option>
                    <option value="oldest">{{ t('manager.content.sortOptions.oldest') }}</option>
                    <option value="scheduled">{{ t('manager.content.sortOptions.scheduled') }}</option>
                </select>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="managerStore.scheduledPostsLoading" class="flex items-center justify-center py-12">
            <LoadingSpinner />
        </div>

        <!-- Posts grid -->
        <div v-else-if="filteredPosts.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div
                v-for="post in filteredPosts"
                :key="post.id"
                class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden hover:border-gray-700 transition-colors group"
            >
                <!-- Thumbnail area -->
                <div class="aspect-[16/9] bg-gray-800 relative overflow-hidden">
                    <img
                        v-if="post.thumbnail_url"
                        :src="post.thumbnail_url"
                        :alt="post.title"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5" />
                        </svg>
                    </div>

                    <!-- Status badge -->
                    <div class="absolute top-2 right-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium" :class="statusColors[post.status] || statusColors.draft">
                            {{ post.status?.replace('_', ' ') }}
                        </span>
                    </div>

                    <!-- Hover overlay -->
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <button
                            @click="handleEditPost(post)"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white text-gray-900 hover:bg-gray-100 transition"
                        >
                            {{ t('manager.content.postCard.edit') }}
                        </button>
                        <button
                            @click="handleDeletePost(post)"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-500 text-white hover:bg-red-400 transition"
                        >
                            {{ t('manager.content.postCard.delete') }}
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-4">
                    <h3 v-if="post.title" class="text-sm font-medium text-white mb-1 line-clamp-1">{{ post.title }}</h3>
                    <p class="text-xs text-gray-400 mb-3 line-clamp-2">{{ truncate(post.main_caption) }}</p>

                    <!-- Platforms -->
                    <div class="flex items-center justify-between">
                        <div class="flex gap-1">
                            <span
                                v-for="platform in (post.platforms || [])"
                                :key="platform"
                                class="w-6 h-6 rounded bg-gray-800 flex items-center justify-center text-[9px] font-bold text-gray-400"
                            >
                                {{ platformIcons[platform] || platform.charAt(0).toUpperCase() }}
                            </span>
                        </div>
                        <span class="text-[10px] text-gray-500">
                            {{ post.scheduled_at ? t('manager.content.postCard.scheduledFor') + ' ' + formatDate(post.scheduled_at) : formatDate(post.created_at) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 rounded-full bg-indigo-500/10 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.content.noContent') }}</h3>
            <p class="text-sm text-gray-400 max-w-md mb-6">{{ t('manager.content.noContentDescription') }}</p>
            <button
                @click="handleCreatePost"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ t('manager.content.createPost') }}
            </button>
        </div>
    </div>
</template>
