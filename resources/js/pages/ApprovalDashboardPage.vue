<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePostsStore } from '@/stores/posts';
import { useBrandsStore } from '@/stores/brands';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import ApprovalCard from '@/components/approval/ApprovalCard.vue';

const { t } = useI18n();
const router = useRouter();
const postsStore = usePostsStore();
const brandsStore = useBrandsStore();

const loading = ref(true);
const selectedBrand = ref('all');
const dateFilter = ref('all');
const selectedPosts = ref([]);
const showBatchApproveModal = ref(false);
const showBatchRejectModal = ref(false);

const posts = computed(() => postsStore.posts);
const brands = computed(() => brandsStore.brands);

const fetchPosts = async () => {
    loading.value = true;
    try {
        const params = {};

        if (selectedBrand.value !== 'all') {
            params.brand_id = selectedBrand.value;
        }

        if (dateFilter.value === 'week') {
            const now = new Date();
            const weekEnd = new Date(now);
            weekEnd.setDate(now.getDate() + 7);
            params.start = now.toISOString().split('T')[0];
            params.end = weekEnd.toISOString().split('T')[0];
        } else if (dateFilter.value === 'month') {
            const now = new Date();
            const monthEnd = new Date(now);
            monthEnd.setMonth(now.getMonth() + 1);
            params.start = now.toISOString().split('T')[0];
            params.end = monthEnd.toISOString().split('T')[0];
        }

        await postsStore.fetchPendingApproval(params);
    } catch (error) {
        console.error('Failed to fetch pending posts:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await brandsStore.fetchBrands();
    await fetchPosts();
});

watch([selectedBrand, dateFilter], fetchPosts);

const togglePostSelection = (postId) => {
    const index = selectedPosts.value.indexOf(postId);
    if (index === -1) {
        selectedPosts.value.push(postId);
    } else {
        selectedPosts.value.splice(index, 1);
    }
};

const selectAll = () => {
    if (selectedPosts.value.length === posts.value.length) {
        selectedPosts.value = [];
    } else {
        selectedPosts.value = posts.value.map(p => p.id);
    }
};

const handleApprove = async (post) => {
    try {
        await postsStore.approvePost(post.id);
        selectedPosts.value = selectedPosts.value.filter(id => id !== post.id);
    } catch (error) {
        console.error('Failed to approve post:', error);
    }
};

const handleReject = async (post) => {
    try {
        await postsStore.rejectPost(post.id);
        selectedPosts.value = selectedPosts.value.filter(id => id !== post.id);
    } catch (error) {
        console.error('Failed to reject post:', error);
    }
};

const handleEdit = (post) => {
    router.push({ name: 'post.edit', params: { postId: post.id } });
};

const handleBatchApprove = async () => {
    try {
        await postsStore.batchApprove(selectedPosts.value);
        selectedPosts.value = [];
        showBatchApproveModal.value = false;
    } catch (error) {
        console.error('Failed to batch approve:', error);
    }
};

const handleBatchReject = async () => {
    try {
        await postsStore.batchReject(selectedPosts.value);
        selectedPosts.value = [];
        showBatchRejectModal.value = false;
    } catch (error) {
        console.error('Failed to batch reject:', error);
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">
                            {{ t('approval.dashboard.title') }}
                        </h1>
                        <p class="text-sm text-gray-500">
                            {{ t('approval.dashboard.subtitle') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <RouterLink :to="{ name: 'calendar' }">
                        <Button variant="secondary">
                            {{ t('calendar.title') }}
                        </Button>
                    </RouterLink>
                </div>
            </div>
        </div>

        <!-- Filters & Batch Actions -->
        <div class="bg-white border-b border-gray-100 px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <!-- Brand Filter -->
                    <select
                        v-model="selectedBrand"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="all">{{ t('approval.dashboard.allBrands') }}</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                            {{ brand.name }}
                        </option>
                    </select>

                    <!-- Date Filter -->
                    <select
                        v-model="dateFilter"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="all">{{ t('approval.dashboard.allTime') }}</option>
                        <option value="week">{{ t('approval.dashboard.thisWeek') }}</option>
                        <option value="month">{{ t('approval.dashboard.thisMonth') }}</option>
                    </select>
                </div>

                <!-- Batch Actions -->
                <div v-if="selectedPosts.length > 0" class="flex items-center space-x-3">
                    <span class="text-sm text-gray-500">
                        {{ selectedPosts.length }} {{ t('common.selected') }}
                    </span>
                    <Button variant="secondary" size="sm" @click="showBatchRejectModal = true">
                        {{ t('approval.dashboard.rejectSelected') }}
                    </Button>
                    <Button size="sm" @click="showBatchApproveModal = true">
                        {{ t('approval.dashboard.approveSelected') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-16">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty State -->
            <div v-else-if="posts.length === 0" class="text-center py-16">
                <svg class="w-16 h-16 mx-auto text-green-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    {{ t('approval.dashboard.noPending') }}
                </h3>
                <p class="text-gray-500">
                    {{ t('approval.dashboard.noPendingDescription') }}
                </p>
                <RouterLink :to="{ name: 'calendar' }" class="mt-4 inline-block">
                    <Button>
                        {{ t('calendar.title') }}
                    </Button>
                </RouterLink>
            </div>

            <!-- Posts Grid -->
            <div v-else>
                <!-- Select All -->
                <div class="flex items-center justify-between mb-4">
                    <button
                        @click="selectAll"
                        class="text-sm text-blue-600 hover:text-blue-700"
                    >
                        {{ selectedPosts.length === posts.length ? t('common.deselectAll') : t('common.selectAll') }}
                    </button>
                    <span class="text-sm text-gray-500">
                        {{ posts.length }} {{ t('approval.pending') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <ApprovalCard
                        v-for="post in posts"
                        :key="post.id"
                        :post="post"
                        :selected="selectedPosts.includes(post.id)"
                        @toggle-select="togglePostSelection(post.id)"
                        @approve="handleApprove(post)"
                        @reject="handleReject(post)"
                        @edit="handleEdit(post)"
                    />
                </div>
            </div>
        </div>

        <!-- Batch Approve Modal -->
        <ConfirmModal
            v-if="showBatchApproveModal"
            :title="t('approval.dashboard.confirmBatchApprove', { count: selectedPosts.length })"
            :message="t('approval.dashboard.confirmBatchApproveMessage', { count: selectedPosts.length })"
            :confirmText="t('approval.approve')"
            :cancelText="t('common.cancel')"
            @confirm="handleBatchApprove"
            @cancel="showBatchApproveModal = false"
        />

        <!-- Batch Reject Modal -->
        <ConfirmModal
            v-if="showBatchRejectModal"
            :title="t('approval.dashboard.confirmBatchReject', { count: selectedPosts.length })"
            :message="t('approval.dashboard.confirmBatchRejectMessage', { count: selectedPosts.length })"
            :confirmText="t('approval.requestChanges')"
            :cancelText="t('common.cancel')"
            confirmVariant="danger"
            @confirm="handleBatchReject"
            @cancel="showBatchRejectModal = false"
        />
    </div>
</template>
