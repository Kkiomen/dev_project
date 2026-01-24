<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useApprovalStore } from '@/stores/approval';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import PreviewPanel from '@/components/preview/PreviewPanel.vue';
import FeedbackModal from '@/components/approval/FeedbackModal.vue';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const router = useRouter();
const approvalStore = useApprovalStore();

const loading = ref(true);
const error = ref(null);
const selectedPost = ref(null);
const showFeedbackModal = ref(false);
const submitting = ref(false);

const isValid = computed(() => !!approvalStore.clientInfo);

const fetchData = async () => {
    loading.value = true;
    error.value = null;
    try {
        await approvalStore.validateToken(props.token);
        await approvalStore.fetchPendingPosts(props.token);
        if (approvalStore.pendingPosts.length > 0) {
            selectedPost.value = approvalStore.pendingPosts[0];
        }
    } catch (e) {
        error.value = e.response?.data?.message || t('approval.invalidToken');
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

const selectPost = (post) => {
    selectedPost.value = post;
};

const handleApprove = async () => {
    if (!selectedPost.value) return;
    submitting.value = true;
    try {
        await approvalStore.submitApproval(props.token, selectedPost.value.id, true);
        if (approvalStore.pendingPosts.length > 0) {
            selectedPost.value = approvalStore.pendingPosts[0];
        } else {
            selectedPost.value = null;
        }
    } catch (e) {
        console.error('Failed to approve:', e);
    } finally {
        submitting.value = false;
    }
};

const handleRequestChanges = () => {
    showFeedbackModal.value = true;
};

const handleSubmitFeedback = async (notes) => {
    if (!selectedPost.value) return;
    submitting.value = true;
    try {
        await approvalStore.submitApproval(props.token, selectedPost.value.id, false, notes);
        showFeedbackModal.value = false;
        if (approvalStore.pendingPosts.length > 0) {
            selectedPost.value = approvalStore.pendingPosts[0];
        } else {
            selectedPost.value = null;
        }
    } catch (e) {
        console.error('Failed to submit feedback:', e);
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">
                            {{ t('approval.clientTitle') }}
                        </h1>
                        <p v-if="approvalStore.clientInfo" class="text-sm text-gray-500">
                            {{ approvalStore.clientInfo.clientName }}
                        </p>
                    </div>
                    <div v-if="approvalStore.pendingPosts.length > 0" class="text-sm text-gray-500">
                        {{ t('approval.pendingCount', { count: approvalStore.pendingPosts.length }) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Error -->
        <div v-else-if="error" class="max-w-md mx-auto py-24 px-6 text-center">
            <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">
                {{ t('approval.invalidTokenTitle') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500">{{ error }}</p>
        </div>

        <!-- Content -->
        <div v-else-if="isValid" class="max-w-7xl mx-auto py-6 px-6">
            <!-- No pending posts -->
            <div v-if="approvalStore.pendingPosts.length === 0" class="text-center py-24">
                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-lg font-medium text-gray-900">
                    {{ t('approval.allApproved') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('approval.allApprovedDescription') }}
                </p>
            </div>

            <!-- Posts list and preview -->
            <div v-else class="flex gap-6">
                <!-- Left: Posts list -->
                <div class="w-1/3">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">
                        {{ t('approval.postsToReview') }}
                    </h2>
                    <div class="space-y-3">
                        <button
                            v-for="post in approvalStore.pendingPosts"
                            :key="post.id"
                            @click="selectPost(post)"
                            class="w-full text-left bg-white rounded-lg border p-4 transition-all"
                            :class="selectedPost?.id === post.id
                                ? 'border-blue-500 ring-2 ring-blue-200'
                                : 'border-gray-200 hover:border-gray-300'"
                        >
                            <div class="flex items-start space-x-3">
                                <div
                                    v-if="post.first_media_url"
                                    class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0"
                                >
                                    <img
                                        :src="post.first_media_url"
                                        :alt="post.title"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-medium text-gray-900 truncate">
                                        {{ post.title }}
                                    </h3>
                                    <p class="text-sm text-gray-500 line-clamp-2">
                                        {{ post.main_caption }}
                                    </p>
                                    <div class="mt-2 flex items-center space-x-2">
                                        <span
                                            v-for="platform in post.enabled_platforms"
                                            :key="platform"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                            :class="{
                                                'bg-blue-100 text-blue-800': platform === 'facebook',
                                                'bg-pink-100 text-pink-800': platform === 'instagram',
                                                'bg-red-100 text-red-800': platform === 'youtube',
                                            }"
                                        >
                                            {{ platform }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Right: Preview and actions -->
                <div class="flex-1">
                    <div v-if="selectedPost" class="bg-white rounded-lg border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">
                                {{ selectedPost.title }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ t('approval.scheduledFor') }}: {{ selectedPost.scheduled_at ? new Date(selectedPost.scheduled_at).toLocaleString() : t('posts.notScheduled') }}
                            </p>
                        </div>

                        <div class="p-6">
                            <PreviewPanel
                                :title="selectedPost.title"
                                :caption="selectedPost.main_caption"
                                :media="selectedPost.media || []"
                                :platform-posts="selectedPost.platform_posts || []"
                            />
                        </div>

                        <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                            <Button
                                variant="secondary"
                                :loading="submitting"
                                @click="handleRequestChanges"
                            >
                                {{ t('approval.requestChanges') }}
                            </Button>
                            <Button
                                :loading="submitting"
                                @click="handleApprove"
                            >
                                {{ t('approval.approve') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Modal -->
        <FeedbackModal
            v-if="showFeedbackModal"
            @submit="handleSubmitFeedback"
            @close="showFeedbackModal = false"
        />
    </div>
</template>
